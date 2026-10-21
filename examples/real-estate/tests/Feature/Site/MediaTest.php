<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Tests\Support\LuminaSite;

it('renders gallery photos with their sizes, dimensions and the library alt text', function (): void {
    Storage::fake('public');
    config(['sunrice-admin.media.queue' => false]);
    $site = LuminaSite::build();
    $photo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('pool.jpg', 2400, 1600), ['alt' => 'The infinity pool at dusk']);
    $property = $site->entry('properties/villa-samudra');
    $property = app(SaveDraft::class)->handle($property, ['data' => ['gallery' => [$photo->id]], 'version' => $property->version]);
    app(PublishEntry::class)->handle($property);

    $html = $this->get('/properties/villa-samudra')->assertOk()->getContent();

    expect($html)
        ->toContain('alt="The infinity pool at dusk"')
        ->toContain('srcset="'.e($photo->fresh()->srcset()).'"')
        ->toContain('width="2400" height="1600"')
        ->toContain('fetchpriority="high"')                       // the first photo isn't lazy
        ->toContain('<meta property="og:image"');                  // the collection's SEO image from the gallery
    $this->get('/properties')->assertSee('alt="The infinity pool at dusk"', false)->assertSee('loading="lazy"', false);
});

it('gives the gallery lightbox the alt text in the page\'s language', function (): void {
    Storage::fake('public');
    config(['sunrice-admin.media.queue' => false]);
    $site = LuminaSite::build();
    $photo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('pool.jpg', 2400, 1600), ['alt' => 'The infinity pool at dusk']);
    $photo->forceFill(['translations' => ['id' => ['alt' => 'Kolam renang saat senja']]])->save();
    $property = $site->entry('properties/villa-samudra');
    $property = app(SaveDraft::class)->handle($property, ['data' => ['gallery' => [$photo->id]], 'version' => $property->version]);
    app(PublishEntry::class)->handle($property);

    // The lightbox's items are JSON in an attribute.
    expect($this->get('/id/properti/vila-samudra')->assertOk()->getContent())->toContain('&quot;alt&quot;:&quot;Kolam renang saat senja&quot;')
        ->and($this->get('/properties/villa-samudra')->getContent())->toContain('&quot;alt&quot;:&quot;The infinity pool at dusk&quot;');
});
