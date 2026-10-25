<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\TrashMedia;
use Sunrice\Admin\Actions\Media\UpdateMedia;
use Sunrice\Admin\Actions\Media\UploadMedia;

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
