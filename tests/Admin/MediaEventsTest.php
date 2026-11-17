<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Media\DeleteMedia;
use Sunrice\Admin\Actions\Media\ReplaceMedia;
use Sunrice\Admin\Actions\Media\UpdateMedia;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Events\MediaDeleted;
use Sunrice\Admin\Events\MediaDeleting;
use Sunrice\Admin\Events\MediaUpdated;
use Sunrice\Admin\Events\MediaUploaded;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;

/*
 * The media library's events (docs: packages/admin/docs/events.md): fired
 * once from the actions, with the acting user, after the transaction
 * commits; MediaDeleting can refuse.
 */

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['sunrice-admin.media.trash_disk' => 'local']);
    usePanel();
    $this->editor = adminUser(Panel::ACCESS, 'sunrice.media.view', 'sunrice.media.delete');
    actingAs($this->editor);
});

it('fires MediaUploaded and MediaUpdated once, with the acting user', function () {
    $fired = [];
    Event::listen([MediaUploaded::class, MediaUpdated::class], function (object $event) use (&$fired): void {
        $fired[] = $event;
    });

    $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png', 40, 40));
    app(UpdateMedia::class)->handle($media, ['alt' => 'Our logo', 'title' => $media->title]);
    app(UpdateMedia::class)->handle($media, ['alt' => 'Our logo']); // nothing changed
    app(ReplaceMedia::class)->handle($media, UploadedFile::fake()->image('logo.png', 50, 50));

    expect(array_map(fn (object $event): string => class_basename($event), $fired))->toBe(['MediaUploaded', 'MediaUpdated', 'MediaUpdated'])
        ->and($fired[0]->media->is($media))->toBeTrue()
        ->and($fired[0]->user?->getAuthIdentifier())->toBe($this->editor->id)
        ->and($fired[0]->source)->toBe('admin')
        ->and($fired[1]->changed)->toBe(['alt'])
        ->and($fired[2]->changed)->toBe(['file']);
});

it('fires MediaDeleting and MediaDeleted for the trash and for good, from the page too', function () {
    $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('a.png', 10, 10));
    $fired = [];
    Event::listen([MediaDeleting::class, MediaDeleted::class], function (MediaDeleting|MediaDeleted $event) use (&$fired): void {
        $fired[] = class_basename($event).':'.($event->permanent ? 'permanent' : 'trash').':'.$event->source;
    });

    delete("/admin/media/{$media->id}")->assertSessionHas('success', 'Moved to the trash.');
    app(DeleteMedia::class)->handle(Media::onlyTrashed()->findOrFail($media->id));

    expect($fired)->toBe(['MediaDeleting:trash:admin', 'MediaDeleted:trash:admin', 'MediaDeleting:permanent:admin', 'MediaDeleted:permanent:admin']);
});

it('lets a MediaDeleting listener refuse, changing nothing and firing nothing after', function () {
    $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png', 10, 10));
    Event::listen(MediaDeleting::class, function (MediaDeleting $event): void {
        if ($event->media->filename === 'logo.png') {
            throw ValidationException::withMessages(['media' => 'The logo stays.']);
        }
    });
    $deleted = 0;
    Event::listen(MediaDeleted::class, function () use (&$deleted): void {
        $deleted++;
    });

    delete("/admin/media/{$media->id}")->assertSessionHasErrors(['media' => 'The logo stays.']);
    expect(fn () => app(DeleteMedia::class)->handle($media))->toThrow(ValidationException::class, 'The logo stays.');

    expect(Media::query()->find($media->id))->not->toBeNull()
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue()
        ->and($deleted)->toBe(0);
});

it('fires the after events only once the transaction commits, and never for one rolled back', function () {
    $fired = 0;
    Event::listen(MediaUploaded::class, function () use (&$fired): void {
        $fired++;
    });

    DB::beginTransaction();
    app(UploadMedia::class)->handle(UploadedFile::fake()->image('a.png', 10, 10));
    expect($fired)->toBe(0);
    DB::rollBack();
    expect($fired)->toBe(0);

    DB::transaction(function () use (&$fired): void {
        app(UploadMedia::class)->handle(UploadedFile::fake()->image('b.png', 10, 10));
        expect($fired)->toBe(0);
    });
    expect($fired)->toBe(1);
});
