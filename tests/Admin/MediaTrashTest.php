<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\DeleteMedia;
use Sunrice\Admin\Actions\Media\TrashMedia;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Jobs\GenerateImageSizes;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaConfig;
use Sunrice\Admin\Media\MediaFilesNotMoved;
use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['sunrice-admin.media.trash_disk' => 'local']); // the default (tests/Pest.php fakes another)
    usePanel();
});

/** An uploaded image with its sizes (thumbnail, medium, large). */
function trashable(string $name = 'report.jpg'): Media
{
    return app(UploadMedia::class)->handle(UploadedFile::fake()->image($name, 2000, 1200));
}

/** @return list<string> where its files are kept in the trash */
function inTrash(Media $media): array
{
    return array_map(fn (string $path) => "sunrice-media-trash/{$path}", $media->files());
}

it('takes a trashed file and all its sizes offline, and restores every one to its path', function () {
    $media = trashable();
    $files = $media->files();
    expect($files)->toHaveCount(4);
    $contents = array_map(fn (string $path) => Storage::disk('public')->get($path), $files);

    app(TrashMedia::class)->handle($media);
    Storage::disk('public')->assertMissing($files);
    Storage::disk('local')->assertExists(inTrash($media));

    app(TrashMedia::class)->restore(Media::onlyTrashed()->findOrFail($media->id));
    Storage::disk('public')->assertExists($files);
    expect(array_map(fn (string $path) => Storage::disk('public')->get($path), $files))->toBe($contents)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($media->fresh()?->url('medium'))->toBe($media->url('medium')); // the same links
});

it('keeps them on the private local disk by default, or the disk and folder configured', function () {
    $config = require dirname(__DIR__, 2).'/packages/admin/config/sunrice-admin.php';
    expect($config['media']['trash_disk'])->toBe('local')->and($config['media']['trash_directory'])->toBe('sunrice-media-trash');
    config(['sunrice-admin.media.trash_disk' => null, 'sunrice-admin.media.trash_directory' => null]);
    expect(MediaConfig::trashDisk())->toBe('local')->and(MediaConfig::trashDirectory())->toBe('sunrice-media-trash');

    Storage::fake('vault');
    config(['sunrice-admin.media.trash_disk' => 'vault', 'sunrice-admin.media.trash_directory' => 'bin/']);
    $media = trashable();

    $media->delete();
    Storage::disk('public')->assertMissing($media->files());
    Storage::disk('vault')->assertExists(array_map(fn (string $path) => "bin/{$path}", $media->files()));
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('permanently deletes a trashed file from the private disk', function () {
    $media = trashable();
    app(TrashMedia::class)->handle($media);
    Storage::disk('local')->assertExists(inTrash($media));

    app(DeleteMedia::class)->handle(Media::onlyTrashed()->findOrFail($media->id));
    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Media::withTrashed()->count())->toBe(0);
});

it('moves nothing and changes nothing when a file can\'t be moved', function () {
    $media = trashable();
    $files = $media->files();
    // The last size can't be copied: the trash disk already has a directory at its path.
    Storage::disk('local')->makeDirectory(inTrash($media)[3]);

    expect(fn () => app(TrashMedia::class)->handle($media))->toThrow(ValidationException::class, 'report.jpg couldn\'t be moved to the trash');
    Storage::disk('public')->assertExists($files);
    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(Media::query()->find($media->id))->not->toBeNull();

    // Restoring: the same, the other way.
    Storage::disk('local')->deleteDirectory(inTrash($media)[3]);
    $media = Media::query()->findOrFail($media->id);
    $media->delete();
    Storage::disk('public')->makeDirectory($files[3]);
    expect(fn () => Media::onlyTrashed()->findOrFail($media->id)->restore())->toThrow(MediaFilesNotMoved::class);
    Storage::disk('local')->assertExists(inTrash($media));
    expect(array_intersect(Storage::disk('public')->allFiles(), $files))->toBe([])
        ->and(Media::onlyTrashed()->find($media->id))->not->toBeNull();
});

it('trashes and restores from the page and in bulk, showing a placeholder in the trash', function () {
    $one = trashable('one.jpg');
    $two = trashable('two.jpg');
    actingAs(adminUser(Panel::ACCESS, 'sunrice.media.view', 'sunrice.media.delete'));

    delete("/admin/media/{$one->id}")->assertSessionHas('success', 'Moved to the trash.');
    Storage::disk('public')->assertMissing($one->files());
    get('/admin/media?status=trashed')->assertInertia(fn (Assert $page) => $page
        ->where('media.data.0.id', $one->id)->where('media.data.0.url', null)->where('media.data.0.thumbnail', null));

    post('/admin/media/bulk', ['action' => 'trash', 'ids' => [$two->id]]);
    Storage::disk('public')->assertMissing($two->files());
    post('/admin/media/bulk', ['action' => 'restore', 'ids' => [$one->id, $two->id]])->assertSessionHas('success', '2 files restored.');
    Storage::disk('public')->assertExists([...$one->files(), ...$two->files()]);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('warns of broken links on permanently deleting only when the file is used', function () {
    $used = trashable('used.jpg');
    $unused = trashable('unused.jpg');
    app(MediaUsages::class)->sync('shop.product', '7', [$used->id], 'Product: Kettle');
    $used->delete();
    $unused->delete();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.media.view', 'sunrice.media.delete'));

    get('/admin/media?status=trashed')->assertInertia(fn (Assert $page) => $page
        ->where('media.data.0.id', $unused->id)->where('media.data.0.deleteWarning', 'Nothing uses it. This can\'t be undone.')
        ->where('media.data.1.id', $used->id)->where('media.data.1.deleteWarning', 'Pages using it will show a broken link.'));

    config(['sunrice-admin.locale' => 'id']);
    get('/admin/media?status=trashed')->assertInertia(fn (Assert $page) => $page
        ->where('media.data.0.deleteWarning', 'Tidak ada yang menggunakannya. Tindakan ini tidak bisa dibatalkan.'));
});

it('doesn\'t put back on the public disk the sizes a queued job makes for a trashed file', function () {
    Queue::fake();
    config(['sunrice-admin.media.queue' => true]);
    $media = trashable();
    expect($media->sizes)->toBe([]);
    $media->delete();

    (new GenerateImageSizes($media->id))->handle();
    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(Media::withTrashed()->findOrFail($media->id)->sizes)->toBe([]);
});
