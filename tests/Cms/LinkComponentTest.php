<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\actingAs;

function linkTag(mixed $link, string $attributes = '', string $slot = ''): string
{
    $tag = $slot === ''
        ? '<x-sunrice-cms::link :link="$link" '.$attributes.' />'
        : '<x-sunrice-cms::link :link="$link" '.$attributes.'>'.$slot.'</x-sunrice-cms::link>';

    return trim(Blade::render($tag, ['link' => $link]));
}

it('prints an entry link at its address in the page\'s language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'route' => '/{slug}']);
    $about = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($pages, ['title' => 'About']));
    $tentang = app(CreateTranslation::class)->handle($about->entry, 'id');
    app(SaveDraft::class)->handle($tentang, ['title' => 'Tentang', 'slug' => 'tentang']);
    app(PublishEntry::class)->handle($tentang);
    $schema = Schema::make([Field::make('cta', 'link')]);
    $data = $schema->normalize(['cta' => ['type' => 'entry', 'entry_id' => $about->entry_id]]);

    expect(linkTag($schema->hydrate($data, new HydrationContext('en'))['cta'], 'class="button"'))
        ->toBe('<a href="'.url('/about').'" class="button">About</a>')
        ->and(linkTag($schema->hydrate($data, new HydrationContext('id'))['cta'], 'class="button"'))
        ->toBe('<a href="'.url('/id/tentang').'" class="button">Tentang</a>');
});

it('prints the editor\'s text, else the page\'s title, else the address', function () {
    $link = ['type' => 'entry', 'url' => '/about', 'label' => 'Read more', 'title' => 'About', 'new_tab' => false, 'rel' => null];

    expect(linkTag($link))->toBe('<a href="/about">Read more</a>')
        ->and(linkTag([...$link, 'label' => null]))->toBe('<a href="/about">About</a>')
        ->and(linkTag([...$link, 'label' => ' ', 'title' => null]))->toBe('<a href="/about">/about</a>')
        ->and(linkTag($link, slot: '<span>Go</span>'))->toBe('<a href="/about"><span>Go</span></a>');
});

it('opens in a new tab with the link\'s rel, adding a rel of the template\'s', function () {
    $link = ['type' => 'url', 'url' => 'https://example.com', 'label' => 'Shop', 'title' => null, 'new_tab' => true, 'rel' => 'sponsored noopener noreferrer'];

    expect(linkTag($link))->toBe('<a href="https://example.com" target="_blank" rel="sponsored noopener noreferrer">Shop</a>')
        ->and(linkTag($link, 'rel="external noopener"'))->toBe('<a href="https://example.com" target="_blank" rel="sponsored noopener noreferrer external">Shop</a>')
        ->and(linkTag([...$link, 'new_tab' => false, 'rel' => null], 'rel="external"'))->toBe('<a href="https://example.com" rel="external">Shop</a>')
        ->and(linkTag($link, 'target="_self"'))->toContain('target="_self"')->not->toContain('_blank');
});

it('prints nothing without an address', function () {
    expect(linkTag(null))->toBe('')
        ->and(linkTag([]))->toBe('')
        ->and(linkTag('/about'))->toBe('')
        ->and(linkTag(['type' => 'entry', 'url' => null, 'label' => 'Soon', 'title' => 'Soon', 'new_tab' => false, 'rel' => null]))->toBe('')
        ->and(linkTag(['type' => 'url', 'url' => '  ', 'label' => 'Empty', 'new_tab' => false]))->toBe('');
});

it('escapes what it prints and keeps the link\'s own address', function () {
    $link = ['type' => 'url', 'url' => '/a?x=1&y="2"', 'label' => '<b>Hi</b>', 'title' => null, 'new_tab' => false, 'rel' => null];

    expect(linkTag($link, 'href="/elsewhere" data-x="1"'))
        ->toBe('<a href="/a?x=1&amp;y=&quot;2&quot;" data-x="1">&lt;b&gt;Hi&lt;/b&gt;</a>');
});
