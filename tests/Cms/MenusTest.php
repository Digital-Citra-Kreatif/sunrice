<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'route' => '/{slug}']);
    $this->main = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
});

function page(string $title, bool $publish = true): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle(test()->pages, ['title' => $title]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

it('resolves links to entries and addresses, nested', function () {
    $about = page('About');
    $team = page('Team');
    app(SaveMenu::class)->handle($this->main, [
        ['entry_id' => $about->entry_id, 'children' => [
            ['entry_id' => $team->entry_id, 'labels' => ['en' => 'Our team']],
        ]],
        ['url' => 'https://example.com/shop', 'labels' => ['en' => 'Shop'], 'new_tab' => true],
    ]);

    expect(app(Menus::class)->get('main'))->toBe([
        ['label' => 'About', 'url' => url('/about'), 'new_tab' => false, 'active' => false, 'active_trail' => false, 'children' => [
            ['label' => 'Our team', 'url' => url('/team'), 'new_tab' => false, 'active' => false, 'active_trail' => false, 'children' => []],
        ]],
        ['label' => 'Shop', 'url' => 'https://example.com/shop', 'new_tab' => true, 'active' => false, 'active_trail' => false, 'children' => []],
    ])->and(Content::menu('nope'))->toBe([]);
});

it('leaves out entries that aren\'t on the site, with what\'s under them', function () {
    $draft = page('Draft', publish: false);
    $gone = page('Gone');
    app(SaveMenu::class)->handle($this->main, [
        ['entry_id' => $draft->entry_id, 'children' => [['url' => '/x', 'labels' => ['en' => 'Under a draft']]]],
        ['entry_id' => $gone->entry_id],
        ['url' => '/contact', 'labels' => ['en' => 'Contact']],
    ]);
    $gone->entry->delete();

    expect(array_column(app(Menus::class)->get('main'), 'label'))->toBe(['Contact']);
});

it('uses each language\'s label and address', function () {
    $about = page('About');
    $tentang = app(CreateTranslation::class)->handle($about->entry, 'id');
    app(SaveDraft::class)->handle($tentang, ['title' => 'Tentang', 'slug' => 'tentang']);
    app(PublishEntry::class)->handle($tentang);
    app(SaveMenu::class)->handle($this->main, [
        ['entry_id' => $about->entry_id],
        ['url' => '/contact', 'labels' => ['en' => 'Contact', 'id' => 'Kontak']],
        ['url' => '/faq', 'labels' => ['en' => 'FAQ']],
    ]);

    expect(array_map(fn (array $item) => [$item['label'], $item['url']], app(Menus::class)->get('main', 'id')))->toBe([
        ['Tentang', url('/id/tentang')],
        ['Kontak', '/contact'],
        ['FAQ', '/faq'],
    ]);
});

it('refuses unsafe addresses, unlabelled ones, unknown entries and a fourth level', function (array $items, string $error) {
    expect(fn () => app(SaveMenu::class)->handle($this->main, $items))->toThrow(function (ValidationException $e) use ($error) {
        expect($e->errors())->toHaveKey($error);
    });
})->with([
    [[['url' => 'javascript:alert(1)', 'labels' => ['en' => 'X']]], 'items.0.url'],
    [[['url' => '/x']], 'items.0.labels.en'],
    [[['entry_id' => 999]], 'items.0.entry_id'],
    [[[]], 'items.0.entry_id'],
    [[['entry_id' => 1, 'url' => '/x', 'labels' => ['en' => 'X']]], 'items.0.entry_id'],
    [[['term_id' => 999]], 'items.0.term_id'],
    [[['url' => '/a', 'labels' => ['en' => 'A'], 'children' => [['url' => '/b', 'labels' => ['en' => 'B'], 'children' => [
        ['url' => '/c', 'labels' => ['en' => 'C'], 'children' => [['url' => '/d', 'labels' => ['en' => 'D']]]],
    ]]]]], 'items.0.children.0.children.0.children'],
]);

it('edits menus in the admin', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $about = page('About');
    app(SaveMenu::class)->handle($this->main, [['entry_id' => $about->entry_id]]);
    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view'));

    get('/admin/cms/menus/main/links')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Menus/Edit', shouldExist: false)
        ->where('items.0.target', ['title' => 'About', 'kind' => 'Pages', 'live' => true])
        ->where('can.update', false));
    put('/admin/cms/menus/main/links', ['items' => []])->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view', 'cms.menus.update'));
    put('/admin/cms/menus/main/links', ['items' => [['url' => '/contact', 'labels' => ['en' => 'Contact']]]])->assertSessionHasNoErrors();
    expect(array_column(app(Menus::class)->get('main'), 'label'))->toBe(['Contact']);
});

it('links to term pages and listing pages, in each language', function () {
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
    app(SaveTerm::class)->handle($tags, ['title' => 'Berita', 'locale' => 'id'], $news);
    $this->pages->update(['listing_route' => '/pages']);
    app(SaveMenu::class)->handle($this->main, [['term_id' => $news->id], ['collection_id' => $this->pages->id, 'labels' => ['en' => 'All pages']]]);

    expect(array_map(fn (array $item) => [$item['label'], $item['url']], app(Menus::class)->get('main')))->toBe([['News', url('/tags/news')], ['All pages', url('/pages')]])
        ->and(app(Menus::class)->get('main', 'id')[0]['url'])->toBe(url('/id/tags/berita'));

    $news->delete();
    $this->pages->update(['listing_route' => null]);
    app(Menus::class)->flush();
    expect(app(Menus::class)->get('main'))->toBe([]);
});

it('links to a listing page with filters, in each language', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $this->pages->update(['listing_route' => '/pages']);
    app(SaveMenu::class)->handle($this->main, [
        ['collection_id' => $this->pages->id, 'labels' => ['en' => 'For sale', 'id' => 'Dijual'], 'query' => '?listing_type=for-sale&city[]=jakarta'],
        ['collection_id' => $this->pages->id, 'labels' => ['en' => 'All']],
    ]);

    expect(app(Menus::class)->get('main')[0]['url'])->toBe(url('/pages').'?listing_type=for-sale&city%5B0%5D=jakarta')
        ->and(app(Menus::class)->get('main', 'id')[0])->toMatchArray(['label' => 'Dijual', 'url' => url('/id/pages').'?listing_type=for-sale&city%5B0%5D=jakarta']);

    // Shown: the filtered link on the filtered page, the plain one on its trail; the plain one alone on the page without filters.
    $this->app->instance('request', Request::create('/pages', 'GET', ['listing_type' => 'for-sale', 'city' => ['jakarta'], 'page' => '2']));
    app(Menus::class)->flush();
    expect(array_column(app(Menus::class)->get('main'), 'active'))->toBe([true, false])
        ->and(array_column(app(Menus::class)->get('main'), 'active_trail'))->toBe([true, true]);
    $this->app->instance('request', Request::create('/pages'));
    expect(array_column(app(Menus::class)->get('main'), 'active'))->toBe([false, true]);

    // The editor gets them back; only listing links take them, as a query string.
    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view'));
    get('/admin/cms/menus/main/links')->assertInertia(fn (Assert $page) => $page->where('items.0.query', 'listing_type=for-sale&city%5B0%5D=jakarta')->where('items.1.query', null));
    expect(fn () => app(SaveMenu::class)->handle($this->main, [['url' => '/x', 'labels' => ['en' => 'X'], 'query' => 'a=1']]))->toThrow(ValidationException::class, 'Only a link to a listing page')
        ->and(fn () => app(SaveMenu::class)->handle($this->main, [['collection_id' => $this->pages->id, 'query' => 'a=1#top']]))->toThrow(ValidationException::class, 'listing_type=for-sale');
});

it('marks the page shown and the links leading to it', function () {
    $about = page('About');
    $team = page('Team');
    app(SaveMenu::class)->handle($this->main, [
        ['entry_id' => $about->entry_id, 'children' => [['entry_id' => $team->entry_id]]],
        ['url' => '/contact', 'labels' => ['en' => 'Contact']],
    ]);

    $this->app->instance('request', Request::create('/team/?ref=x'));
    $menu = app(Menus::class)->get('main');
    expect([$menu[0]['active'], $menu[0]['active_trail'], $menu[0]['children'][0]['active'], $menu[1]['active_trail']])->toBe([false, true, true, false]);

    $this->app->instance('request', Request::create('/contact'));
    expect(app(Menus::class)->get('main')[1]['active'])->toBeTrue();
});

it('marks a link with a query string only when the address has its values, ahead of the one without', function () {
    $this->pages->update(['listing_route' => '/pages']);
    app(SaveMenu::class)->handle($this->main, [
        ['collection_id' => $this->pages->id, 'labels' => ['en' => 'Pages'], 'children' => [
            ['url' => '/pages?type=sale', 'labels' => ['en' => 'For sale']],
            ['url' => '/pages?type=rent', 'labels' => ['en' => 'For rent']],
        ]],
    ]);
    $marks = function (string $address): array {
        app()->instance('request', Request::create($address));
        $menu = app(Menus::class)->get('main');

        return [$menu[0]['active'], $menu[0]['active_trail'], $menu[0]['children'][0]['active'], $menu[0]['children'][1]['active']];
    };

    expect($marks('/pages'))->toBe([true, true, false, false])
        ->and($marks('/pages?type=sale&page=2'))->toBe([false, true, true, false])
        ->and($marks('/pages?type=other'))->toBe([true, true, false, false]);
});

it('puts the listing page and the parent entries of the entry shown on the trail', function () {
    $this->pages->update(['listing_route' => '/pages', 'hierarchical' => true]);
    $services = page('Services');
    $design = page('Design');
    $design->entry->update(['parent_id' => $services->entry_id]);
    app(SaveMenu::class)->handle($this->main, [
        ['collection_id' => $this->pages->id, 'labels' => ['en' => 'Pages']],
        ['entry_id' => $services->entry_id],
        ['url' => '/contact', 'labels' => ['en' => 'Contact']],
    ]);

    $this->app->instance('request', Request::create('/design'));
    (new Page(Page::ENTRY, 'en', 'cms.entry', entry: $design->fresh()))->share();
    $menu = app(Menus::class)->get('main');
    expect(array_map(fn (array $item) => [$item['active'], $item['active_trail']], $menu))->toBe([[false, true], [false, true], [false, false]]);
});

it('links untranslated pages at the language\'s address, or the main page with the fallback off', function () {
    $about = page('About');
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
    app(SaveMenu::class)->handle($this->main, [['entry_id' => $about->entry_id], ['term_id' => $news->id]]);

    expect(array_map(fn (array $item) => [$item['label'], $item['url']], app(Menus::class)->get('main', 'id')))->toBe([
        ['About', url('/id/about')],
        ['News', url('/id/tags/news')],
    ]);

    config(['sunrice-cms.site.fallback' => false]);
    app(Menus::class)->flush();
    app(SiteCache::class)->changed('test');
    expect(array_column(app(Menus::class)->get('main', 'id'), 'url'))->toBe([url('/about'), url('/tags/news')]);
});

it('lists the menus once, under Content, each opening its links', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view'));

    get('/admin/cms/menus')->assertOk()->assertInertia(function (Assert $page) {
        $items = collect($page->toArray()['props']['navigation'])->pluck('items')->flatten(1)->keyBy('key');

        expect($items['resources.menus']['label'])->toBe('Menus')
            ->and($items->keys()->filter(fn (string $key) => str_contains($key, 'menus'))->values()->all())->toBe(['resources.menus']);
        $page->where('rows.data.0.key', 'main')->where('rows.data.0.url', url('/admin/cms/menus/main/links'));
    });
    expect(app(Panel::class)->getPermissions()['cms.menus.view']->label)->toBe('View Menus');
});

it('replaces a nested menu\'s links when saved again, without duplicating them', function () {
    $tree = [
        ['url' => '/properties', 'labels' => ['en' => 'Properties'], 'children' => [
            ['url' => '/for-sale', 'labels' => ['en' => 'For sale']],
            ['url' => '/for-rent', 'labels' => ['en' => 'For rent'], 'children' => [
                ['url' => '/villas', 'labels' => ['en' => 'Villas']],
            ]],
        ]],
        ['url' => '/team', 'labels' => ['en' => 'Team']],
        ['url' => '/about', 'labels' => ['en' => 'About'], 'children' => [
            ['url' => '/story', 'labels' => ['en' => 'Our story']],
        ]],
        ['url' => '/contact', 'labels' => ['en' => 'Contact']],
    ];
    app(SaveMenu::class)->handle($this->main, $tree);
    app(SaveMenu::class)->handle($this->main->fresh(), $tree);
    app(SaveMenu::class)->handle($this->main->fresh(), $tree);

    expect($this->main->items()->count())->toBe(8)
        ->and(array_column(app(Menus::class)->get('main'), 'label'))->toBe(['Properties', 'Team', 'About', 'Contact']);
});

it('fills a new menu\'s handle from its title, and keeps it on the edit form, with the way to its links', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view', 'cms.menus.create', 'cms.menus.update'));

    get('/admin/cms/menus/create')->assertInertia(fn (Assert $page) => $page
        ->where('sections.0.fields.1.name', 'handle')->where('sections.0.fields.1.slugFrom', 'title'));
    post('/admin/cms/menus', ['title' => 'Footer', 'handle' => 'Footer Links'])
        ->assertSessionHasErrors(['handle' => 'Use lowercase letters, digits and underscores, starting with a letter: try "footer_links".']);

    get('/admin/cms/menus/main/edit')->assertInertia(fn (Assert $page) => $page
        ->where('sections.0.fields.1.name', 'handle')->where('values.handle', 'main')
        ->where('aside.0.title', 'Links')->where('aside.0.links.0.url', '/admin/cms/menus/main/links'));
    put('/admin/cms/menus/main', ['title' => 'Main menu', 'handle' => 'main'])->assertSessionHasNoErrors();
    expect($this->main->fresh()?->only(['title', 'handle']))->toBe(['title' => 'Main menu', 'handle' => 'main']);
});
