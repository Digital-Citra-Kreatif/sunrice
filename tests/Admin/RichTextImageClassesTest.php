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

it('keeps the image styles in config, alongside the presets, and drops any other class', function () {
    config(['sunrice-admin.rich_text.image_classes' => ['rounded' => 'Rounded', 'shadow' => 'Shadow']]);

    expect(RichTextImageClasses::allowed())->toContain('rounded', 'shadow', 'sunrice-img-w-50')
        ->and(RichText::sanitize('<img src="/a.jpg" class="sunrice-img sunrice-img-w-50 rounded evil shadow">'))->toContain('class="sunrice-img sunrice-img-w-50 rounded shadow"')
        ->and(RichText::sanitize('<img src="/a.jpg" class="shadow">'))->toContain('class="shadow"')
        ->and(RichText::sanitize('<img src="/a.jpg" class="hero">'))->not->toContain('class=')
        // Only on images.
        ->and(RichText::sanitize('<p class="rounded">x</p>'))->toBe('<p>x</p>');
});

it('picks up config changes, though the sanitiser is built once', function () {
    config(['sunrice-admin.rich_text.image_classes' => ['rounded' => 'Rounded']]);
    expect(RichText::sanitize('<img src="/a.jpg" class="rounded">'))->toContain('class="rounded"');

    // Taken out of config: dropped the next time the text is saved.
    config(['sunrice-admin.rich_text.image_classes' => []]);
    expect(RichText::sanitize('<img src="/a.jpg" class="rounded">'))->not->toContain('class=');
});

it('ignores configured classes that are not CSS class names or are the presets\'', function () {
    config(['sunrice-admin.rich_text.image_classes' => [
        'rounded' => 'Rounded', '1st' => 'Digit', 'a b' => 'Space', 'x"y' => 'Quote', '-dash' => 'Dash', 'sunrice-img-w-33' => 'Preset',
        'framed' => '', 'tilted' => ['not a label'], 7 => 'polaroid', 8 => 'not valid', 'Wide_one-2' => 'Wide',
    ]]);

    expect(RichTextImageClasses::styles())->toBe(['rounded' => 'Rounded', 'framed' => 'framed', 'tilted' => 'tilted', 'polaroid' => 'polaroid', 'Wide_one-2' => 'Wide'])
        ->and(RichText::sanitize('<img src="/a.jpg" class="1st sunrice-img-w-33 polaroid">'))->toContain('class="polaroid"');

    config(['sunrice-admin.rich_text.image_classes' => 'rounded']);
    expect(RichTextImageClasses::styles())->toBe([]);
    config(['sunrice-admin.rich_text.image_classes' => null]);
    expect(RichTextImageClasses::styles())->toBe([]);
});

it('offers no styles by default', function () {
    expect(RichTextImageClasses::styles())->toBe([])
        ->and(RichText::make('body')->toArray())->toMatchArray(['imageStyles' => []]);
});

it('hands the image styles to the editor in the field\'s props, labels translated, and none without images', function () {
    config(['sunrice-admin.rich_text.image_classes' => ['rounded' => 'Rounded', 'shadow' => 'Shadow']]);
    app()->setLocale('id');
    app('translator')->addLines(['*.Rounded' => 'Membulat'], 'id', '*');

    expect(RichText::make('body')->toArray()['imageStyles'])->toBe([['class' => 'rounded', 'label' => 'Membulat'], ['class' => 'shadow', 'label' => 'Shadow']])
        ->and(RichText::make('body')->images(false)->toArray()['imageStyles'])->toBe([]);
});

it('keeps a captioned image\'s figure, its presets on the figure and its styles on the image', function () {
    config(['sunrice-admin.rich_text.image_classes' => ['rounded' => 'Rounded']]);
    $html = '<figure class="sunrice-img sunrice-img-w-50 sunrice-img-left"><img src="/media/a.jpg" alt="A saw" class="rounded" data-media-id="7"><figcaption>The <strong>old</strong> saw, <em>1920</em> <a href="https://example.com/saw">source</a></figcaption></figure>';

    expect(RichText::sanitize($html))->toBe(
        '<figure class="sunrice-img sunrice-img-w-50 sunrice-img-left"><img src="/media/a.jpg" alt="A saw" class="rounded" data-media-id="7" />'
        .'<figcaption>The <strong>old</strong> saw, <em>1920</em> <a href="https://example.com/saw" rel="noopener noreferrer">source</a></figcaption></figure>'
    )
        // Saved again: the same.
        ->and(RichText::sanitize(RichText::sanitize($html)))->toBe(RichText::sanitize($html));
});

it('holds a figure\'s classes to the image classes and its caption to inline formatting', function () {
    $sanitized = RichText::sanitize('<figure class="sunrice-img evil sunrice-img-right" style="position:fixed" onclick="x()"><img src="/a.jpg" class="sunrice-img-w-33 junk">'
        .'<figcaption class="huge" onmouseover="x()">Caption <script>alert(1)</script><img src=x onerror="alert(1)"><a href="javascript:alert(1)">bad</a> <iframe src="https://example.com"></iframe></figcaption></figure>');

    expect($sanitized)->toContain('<figure class="sunrice-img sunrice-img-right"><img src="/a.jpg" />')
        ->toContain('<figcaption>Caption ')
        ->not->toContain('evil')->not->toContain('junk')->not->toContain('huge')->not->toContain('style')
        ->not->toContain('onclick')->not->toContain('onmouseover')->not->toContain('onerror')->not->toContain('script')
        ->not->toContain('javascript')->not->toContain('iframe')
        // A figure without an allowed class is a plain figure.
        ->and(RichText::sanitize('<figure class="wp-block-image"><img src="/a.jpg"><figcaption>Hi</figcaption></figure>'))
        ->toBe('<figure><img src="/a.jpg" /><figcaption>Hi</figcaption></figure>');
});
