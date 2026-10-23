<?php

declare(strict_types=1);

use Sunrice\Admin\Resources\Forms\RichText;
use Sunrice\Admin\Resources\Forms\RichTextImageClasses;

it('keeps the editor\'s image width and alignment classes', function () {
    $html = '<p><img src="/media/a.jpg" alt="A saw" class="sunrice-img sunrice-img-w-50 sunrice-img-left" data-media-id="7"></p>';

    expect(RichText::sanitize($html))
        ->toContain('class="sunrice-img sunrice-img-w-50 sunrice-img-left"')
        ->toContain('alt="A saw"')->toContain('data-media-id="7"');

    foreach (RichTextImageClasses::allowed() as $class) {
        expect(RichText::sanitize("<img src=\"/a.jpg\" class=\"{$class}\">"))->toContain("class=\"{$class}\"");
    }
});

it('drops any other class and every inline style on images', function () {
    $sanitized = RichText::sanitize('<img src="/a.jpg" class="sunrice-img-w-75 evil sunrice-img-w-33 sunrice-img-right sunrice-img-right" style="position:fixed;inset:0">');

    expect($sanitized)->toContain('class="sunrice-img-w-75 sunrice-img-right"')
        ->not->toContain('evil')->not->toContain('w-33')->not->toContain('style')
        ->and(RichText::sanitize('<img src="/a.jpg" class="hero">'))->not->toContain('class=')
        ->and(RichText::sanitize('<p class="sunrice-img">x</p>'))->toBe('<p>x</p>');
});

it('leaves images without classes as they were', function () {
    expect(RichText::sanitize('<img src="/media/a.jpg" alt="A">'))->toBe('<img src="/media/a.jpg" alt="A" />');
});

it('filters a class attribute down to the allowed values', function () {
    expect(RichTextImageClasses::filter('  sunrice-img   sunrice-img-full x '))->toBe('sunrice-img sunrice-img-full')
        ->and(RichTextImageClasses::filter('x y'))->toBeNull()
        ->and(RichTextImageClasses::filter(''))->toBeNull();
});
