<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\RichText;
use Sunrice\Admin\Resources\Forms\RichTextLinkAttributes;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/*
 * Rich text links: the rel choices and data-link the sanitiser keeps, and
 * the link sources plugins register for the editor's link menu.
 */

/** A panel with a "product" source: products the user may view (shop.products.view). */
function productLinks(): Panel
{
    $products = [4 => 'Blue mug', 5 => 'Red mug', 6 => 'Teapot'];
    $visible = fn (Authorizable $user): array => $user->can('shop.products.view') ? $products : [];

    return usePanel(fn (Panel $panel) => $panel->richTextLinkSource('product', 'Product',
        fn (string $query, Authorizable $user): array => [
            ...collect($visible($user))->filter(fn (string $title): bool => str_contains(strtolower($title), strtolower($query)))
                ->map(fn (string $title, int $id): array => ['id' => $id, 'title' => $title, 'hint' => 'In stock', 'url' => "/shop/{$id}"])->values()->all(),
            // Malformed items are left out.
            ['id' => 0, 'title' => 'No id', 'url' => '/x'], ['id' => 9, 'title' => 'Bad URL', 'url' => 'javascript:alert(1)'], ['id' => 10, 'title' => 'Other site', 'url' => '//evil.test'], 'junk',
        ],
        fn (array $ids, Authorizable $user): array => collect($visible($user))->only($ids)
            ->map(fn (string $title, int $id): array => ['id' => $id, 'title' => $title, 'url' => "/shop/{$id}"])->values()->all()));
}

it('keeps the editor\'s rel choices and always adds noopener noreferrer', function () {
    expect(RichText::sanitize('<p><a href="/a" rel="nofollow" target="_blank">a</a></p>'))->toBe('<p><a href="/a" rel="nofollow noopener noreferrer" target="_blank">a</a></p>')
        ->and(RichText::sanitize('<p><a href="/a" rel="UGC junk sponsored noopener">a</a></p>'))->toContain('rel="sponsored ugc noopener noreferrer"')
        ->and(RichText::sanitize('<p><a href="/a">a</a> <a title="t" href="/b">b</a></p>'))->toBe('<p><a href="/a" rel="noopener noreferrer">a</a> <a title="t" href="/b" rel="noopener noreferrer">b</a></p>')
        ->and(RichTextLinkAttributes::rel('nofollow nofollow evil'))->toBe('nofollow noopener noreferrer')
        ->and(RichTextLinkAttributes::rel(''))->toBe('noopener noreferrer');
});

it('keeps a well-formed data-link and drops any other', function () {
    expect(RichText::sanitize('<p><a href="/a" data-link="entry:12">a</a></p>'))->toContain('data-link="entry:12"')
        ->and(RichText::sanitize('<p><a href="/a" data-link="entry:12&quot; onclick=&quot;x">a</a><a href="/b" data-link="ENTRY:1">b</a><a href="/c" data-link="entry:">c</a></p>'))->not->toContain('data-link')
        ->and(RichTextLinkAttributes::link(' term:5 '))->toBe('term:5')
        ->and(RichTextLinkAttributes::link('1term:5'))->toBeNull();
});

it('checks link sources\' keys and refuses one twice', function () {
    $search = fn (): array => [];

    expect(fn () => usePanel(fn (Panel $panel) => $panel->richTextLinkSource('Shop.Product', 'Product', $search)))->toThrow(PanelMisconfigured::class, 'lowercase letters')
        ->and(fn () => usePanel(fn (Panel $panel) => $panel->richTextLinkSource('product', 'Product', $search)->richTextLinkSource('product', 'Other', $search)))
        ->toThrow(PanelMisconfigured::class, 'registered twice');
});

it('tells the editor which sources there are', function () {
    productLinks();
    actingAs(adminUser(Panel::ACCESS));

    get('/admin')->assertInertia(fn (Assert $page) => $page->where('sunrice.richTextLinks', [['key' => 'product', 'label' => 'Product']]));
});

it('searches the sources for the link menu, as the signed-in user may see them', function () {
    productLinks();
    actingAs(adminUser(Panel::ACCESS, 'shop.products.view'));

    expect(getJson('/admin/rich-text-links?q=mug')->assertOk()->json('groups'))->toBe([['key' => 'product', 'label' => 'Product', 'items' => [
        ['link' => 'product:4', 'id' => 4, 'title' => 'Blue mug', 'hint' => 'In stock', 'url' => '/shop/4'],
        ['link' => 'product:5', 'id' => 5, 'title' => 'Red mug', 'hint' => 'In stock', 'url' => '/shop/5'],
    ]]])
        // One letter is too few; another source's key lists nothing.
        ->and(getJson('/admin/rich-text-links?q=m')->json('groups'))->toBe([])
        ->and(getJson('/admin/rich-text-links?q=mug&source=entry')->json('groups'))->toBe([])
        ->and(getJson('/admin/rich-text-links/product:6')->json('item'))->toBe(['link' => 'product:6', 'id' => 6, 'title' => 'Teapot', 'hint' => null, 'url' => '/shop/6', 'source' => 'Product'])
        ->and(getJson('/admin/rich-text-links/product:99')->json('item'))->toBeNull()
        ->and(getJson('/admin/rich-text-links/order:6')->json('item'))->toBeNull();

    actingAs(adminUser(Panel::ACCESS));
    expect(getJson('/admin/rich-text-links?q=mug')->json('groups'))->toBe([])
        ->and(getJson('/admin/rich-text-links/product:6')->json('item'))->toBeNull();
});

it('turns away guests and users who may not open the panel', function () {
    productLinks();

    get('/admin/rich-text-links?q=mug')->assertRedirect('/admin/login');
    getJson('/admin/rich-text-links?q=mug')->assertUnauthorized();

    actingAs(adminUser('shop.products.view'));
    getJson('/admin/rich-text-links?q=mug')->assertForbidden();
    getJson('/admin/rich-text-links/product:4')->assertForbidden();
});
