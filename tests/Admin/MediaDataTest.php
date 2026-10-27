<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\TrashMedia;
use Sunrice\Admin\Actions\Media\UpdateMedia;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;

/*
 * Media::toData(): a plain array for scripts on a page, with the same URLs
 * templates use, and nothing a page shouldn't see.
 */

beforeEach(function () {
    Storage::fake('public');
});

it('gives an image\'s data: its URLs, text, dimensions, sizes and srcset', function () {
    $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('wide.jpg', 2400, 1200), ['title' => 'The shop', 'alt' => 'The shop front']);
    app(UpdateMedia::class)->handle($media, ['caption' => 'Open daily']);

    expect($media->toData())->toBe([
        'id' => $media->id,
        'url' => $media->url(),
        'alt' => 'The shop front',
        'title' => 'The shop',
        'caption' => 'Open daily',
        'mime' => 'image/jpeg',
        'type' => 'image',
        'width' => 2400,
        'height' => 1200,
        'sizes' => [
            'thumbnail' => ['url' => $media->url('thumbnail'), 'width' => 300, 'height' => 300],
            'medium' => ['url' => $media->url('medium'), 'width' => 800, 'height' => 400],
            'large' => ['url' => $media->url('large'), 'width' => 1600, 'height' => 800],
        ],
        'srcset' => $media->srcset(),
    ])->and($media->toData()['srcset'])->toContain('?v=1 800w')
        // Nothing of where it's kept or who uploaded it.
        ->and($media->toData())->not->toHaveKeys(['disk', 'path', 'uploaded_by', 'translations']);
});

it('gives the alt text and caption in a language', function () {
    $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('shop.jpg', 10, 10), ['alt' => 'The shop front']);
    $media->forceFill(['translations' => ['id' => ['alt' => 'Bagian depan toko']]])->save(); // as UpdateMedia keeps them, for the content's languages

    expect($media->toData('id')['alt'])->toBe('Bagian depan toko')
        ->and($media->toData('en')['alt'])->toBe('The shop front');
    app()->setLocale('id');
    expect($media->fresh()?->toData()['alt'])->toBe('Bagian depan toko');
});

it('gives files other than images no dimensions, sizes or srcset', function () {
    $pdf = app(UploadMedia::class)->handle(UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'), ['title' => '']);
    $data = $pdf->toData();

    expect($data['type'])->toBe('document')
        ->and($data['mime'])->toBe('application/pdf')
        ->and($data['url'])->toBe($pdf->url())
        ->and($data['width'])->toBeNull()->and($data['height'])->toBeNull()
        ->and($data['sizes'])->toBe([])->and($data['srcset'])->toBe('');
});

it('gives media in the trash no URL and no sizes: its files are offline', function () {
    $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('gone.jpg', 1200, 800), ['alt' => 'Gone']);
    app(TrashMedia::class)->handle($media);
    $data = $media->fresh()?->toData() ?? [];

    expect($data['url'])->toBeNull()
        ->and($data['sizes'])->toBe([])
        ->and($data['srcset'])->toBe('')
        ->and($data['alt'])->toBe('Gone');
});

it('gives the same data, to the byte, for a list of images, and follows changes to an image and to the sizes config', function () {
    $media = Media::query()->create([
        'disk' => 'public', 'path' => 'media/a.jpg', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'width' => 2400, 'height' => 1600,
        'alt' => 'A', 'translations' => ['id' => ['alt' => 'Sebuah']], 'sizes' => ['large' => 'media/a-large.jpg', 'thumbnail' => 'media/a-thumbnail.jpg', 'medium' => 'media/a-medium.jpg'],
    ]);
    $url = fn (string $path, int $version = 1) => Storage::disk('public')->url($path).'?v='.$version;

    expect(json_encode($media->toData('id')))->toBe(json_encode([
        'id' => $media->id, 'url' => $url('media/a.jpg'), 'alt' => 'Sebuah', 'title' => null, 'caption' => null, 'mime' => 'image/jpeg', 'type' => 'image', 'width' => 2400, 'height' => 1600,
        'sizes' => [
            'thumbnail' => ['url' => $url('media/a-thumbnail.jpg'), 'width' => 300, 'height' => 300],
            'medium' => ['url' => $url('media/a-medium.jpg'), 'width' => 800, 'height' => 533],
            'large' => ['url' => $url('media/a-large.jpg'), 'width' => 1600, 'height' => 1067],
        ],
        'srcset' => $url('media/a-medium.jpg').' 800w, '.$url('media/a-large.jpg').' 1600w, '.$url('media/a.jpg').' 2400w',
    ]));

    // What's read once per image (its sizes, translations) and per config (the sizes) follows changes.
    $media->forceFill(['sizes' => ['medium' => 'media/b-medium.jpg'], 'translations' => ['id' => ['alt' => 'Lain']], 'version' => 2]);
    config(['sunrice-admin.media.image_sizes' => ['medium' => [1200, null, 'fit']]]);
    expect($media->toData('id'))->toMatchArray([
        'alt' => 'Lain',
        'sizes' => ['medium' => ['url' => $url('media/b-medium.jpg', 2), 'width' => 1200, 'height' => 800]],
        'srcset' => $url('media/b-medium.jpg', 2).' 1200w, '.$url('media/a.jpg', 2).' 2400w',
    ])->and($media->thumbnail())->toBe($url('media/b-medium.jpg', 2));
});
