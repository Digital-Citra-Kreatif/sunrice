<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    Storage::fake('public');
    usePanel();
});

/** Signed in with the panel and these media permissions ("view", "upload" …). */
function mediaUser(string ...$abilities): void
{
    actingAs(adminUser(Panel::ACCESS, ...array_map(fn ($a) => "sunrice.media.{$a}", $abilities)));
}

function mediaFile(string $name = 'photo.jpg', ?MediaFolder $folder = null): Media
{
    return app(UploadMedia::class)->handle(UploadedFile::fake()->image($name, 400, 300), ['folder_id' => $folder?->id]);
}

it('adds the Media page and its permissions, and can leave them out', function () {
    expect(array_keys(app(Panel::class)->getPermissions()))->toContain('sunrice.media.view', 'sunrice.media.upload', 'sunrice.media.update', 'sunrice.media.delete');

    mediaUser();
    get('/admin/media')->assertForbidden();
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('navigation', []));

    mediaUser('view');
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('navigation.0.items.0.url', 'media'));
    get('/admin/media')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Media/Index', shouldExist: false)
        ->where('can', ['upload' => false, 'update' => false, 'delete' => false]));
    post('/admin/media', ['file' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();

    $panel = Panel::make()->mediaLibrary(false)->aiAccess(false);
    $panel->boot();
    expect(array_keys($panel->getPermissions()))->not->toContain('sunrice.media.view');
});

it('browses folders, searches everywhere and filters by type', function () {
    $trips = MediaFolder::query()->create(['name' => 'Trips']);
    $bali = MediaFolder::query()->create(['name' => 'Bali', 'parent_id' => $trips->id]);
    mediaFile('logo.png');
    mediaFile('beach.jpg', $bali);
    app(UploadMedia::class)->handle(UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf'));
    mediaUser('view');

    get('/admin/media')->assertInertia(fn (Assert $page) => $page
        ->where('folders', [['id' => $trips->id, 'name' => 'Trips']])
        ->where('media.total', 2)
        ->where('tree', [['id' => $trips->id, 'name' => 'Trips', 'depth' => 0], ['id' => $bali->id, 'name' => 'Bali', 'depth' => 1]]));

    get("/admin/media?folder={$bali->id}")->assertInertia(fn (Assert $page) => $page
        ->where('path', [['id' => $trips->id, 'name' => 'Trips'], ['id' => $bali->id, 'name' => 'Bali']])
        ->where('media.total', 1)
        ->where('media.data.0.title', 'beach')
        ->where('media.data.0.thumbnail', fn ($url) => str_contains($url, '-thumbnail.jpg')));

    get('/admin/media?search=BEA')->assertInertia(fn (Assert $page) => $page->where('media.total', 1)->where('folders', []));
    get('/admin/media?search=%25')->assertInertia(fn (Assert $page) => $page->where('media.total', 0));
    get('/admin/media?type=document')->assertInertia(fn (Assert $page) => $page->where('media.data.0.filename', 'menu.pdf')->where('media.total', 1));
    get('/admin/media?type=image')->assertInertia(fn (Assert $page) => $page->where('media.total', 1));
    get('/admin/media?type=nope')->assertSessionHasErrors('type');
});

it('uploads into a folder, edits details and replaces the file', function () {
    $folder = MediaFolder::query()->create(['name' => 'Logos']);
    mediaUser('view', 'upload');
    post('/admin/media', ['file' => UploadedFile::fake()->image('Logo.png', 200, 200), 'folder_id' => $folder->id])
        ->assertRedirect()->assertSessionHasNoErrors();
    $media = Media::query()->sole();
    expect($media->folder_id)->toBe($folder->id)
        ->and($media->title)->toBe('Logo')
        ->and($media->uploaded_by)->toBe((string) auth()->id());

    // A title and alternative text may come with the file; empty ones mean none.
    post('/admin/media', ['file' => UploadedFile::fake()->image('sun.jpg', 20, 20), 'title' => ' The sun ', 'alt' => 'A sun over the sea'])->assertSessionHasNoErrors();
    expect(Media::query()->latest('id')->first())->title->toBe('The sun')->alt->toBe('A sun over the sea');
    post('/admin/media', ['file' => UploadedFile::fake()->image('moon.jpg', 20, 20), 'title' => '', 'alt' => ''])->assertSessionHasNoErrors();
    expect(Media::query()->latest('id')->first())->title->toBe('moon')->alt->toBeNull();
    post('/admin/media', ['file' => UploadedFile::fake()->image('x.jpg', 20, 20), 'title' => str_repeat('x', 251)])->assertSessionHasErrors('title');
    post('/admin/media', ['file' => UploadedFile::fake()->image('x.jpg', 20, 20), 'alt' => ['no']])->assertSessionHasErrors('alt');

    post('/admin/media', ['file' => UploadedFile::fake()->createWithContent('x.php', '<?php')])->assertSessionHasErrors('file');
    put("/admin/media/{$media->id}", ['title' => 'Logo'])->assertForbidden();

    mediaUser('view', 'update');
    put("/admin/media/{$media->id}", ['title' => ' Our logo ', 'alt' => 'A sun', 'caption' => '', 'folder_id' => 999])->assertSessionHas('success');
    expect($media->fresh())->title->toBe('Our logo')->alt->toBe('A sun')->caption->toBeNull()->folder_id->toBe($folder->id);

    post("/admin/media/{$media->id}/replace", ['file' => UploadedFile::fake()->image('new.png', 50, 50)])->assertSessionHas('success', 'File replaced.');
    expect($media->fresh())->version->toBe(2)->width->toBe(50);
});

it('trashes, restores and deletes for good, one or many', function () {
    $one = mediaFile('one.jpg');
    $two = mediaFile('two.jpg');
    mediaUser('view', 'update');
    delete("/admin/media/{$one->id}")->assertForbidden();
    post('/admin/media/bulk', ['action' => 'trash', 'ids' => [$one->id]])->assertForbidden();

    mediaUser('view', 'delete');
    delete("/admin/media/{$one->id}")->assertSessionHas('success', 'Moved to the trash.');
    get('/admin/media?trash=1')->assertInertia(fn (Assert $page) => $page->where('media.total', 1)->where('media.data.0.trashed', true));
    post("/admin/media/{$one->id}/restore")->assertSessionHas('success', 'Restored.');
    delete("/admin/media/{$one->id}/force")->assertNotFound(); // only from the trash

    post('/admin/media/bulk', ['action' => 'trash', 'ids' => [$one->id, $two->id]])->assertSessionHas('success', '2 files moved to the trash.');
    post('/admin/media/bulk', ['action' => 'delete', 'ids' => [$one->id, $two->id, 999]])->assertSessionHas('success', '2 files deleted for good.');
    expect(Media::withTrashed()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('organises folders: create, rename, move files, delete without losing anything', function () {
    mediaUser('view', 'update');
    post('/admin/media/folders', ['name' => 'Trips'])->assertSessionHas('success', 'Folder "Trips" created.');
    post('/admin/media/folders', ['name' => 'Trips'])->assertSessionHasErrors(['name' => 'There is already a folder called "Trips" here.']);
    $trips = MediaFolder::query()->sole();
    post('/admin/media/folders', ['name' => 'Bali', 'parent_id' => $trips->id]);
    $bali = MediaFolder::query()->where('name', 'Bali')->sole();
    put("/admin/media/folders/{$bali->id}", ['name' => 'Lombok'])->assertSessionHas('success');
    expect($bali->fresh()->name)->toBe('Lombok');

    $photo = mediaFile('photo.jpg');
    post('/admin/media/bulk', ['action' => 'move', 'ids' => [$photo->id], 'folder_id' => $trips->id])->assertSessionHas('success', '1 file moved.');
    expect($photo->fresh()->folder_id)->toBe($trips->id);

    delete("/admin/media/folders/{$trips->id}")->assertRedirect('/admin/media');
    expect($photo->fresh()->folder_id)->toBeNull()
        ->and($bali->fresh()->parent_id)->toBeNull()
        ->and(MediaFolder::query()->count())->toBe(1);

    mediaUser('view', 'upload');
    post('/admin/media/folders', ['name' => 'Nope'])->assertForbidden();
});
