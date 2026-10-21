<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => []]);
    $this->articles = Collection::query()->create(['handle' => 'articles', 'title' => 'Artikel', 'blueprint' => 'post', 'listing_route' => '/artikel']);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'post']);
    $this->categories = Taxonomy::query()->create(['handle' => 'categories', 'title' => 'Kategori']);
});

/** @return array<string, list<array{key: string, label: string, url: string, icon: string}>> group => its items */
function sidebarOf(mixed $user): array
{
    return collect(app(Panel::class)->navigationFor($user))
        ->mapWithKeys(fn (array $group) => [(string) $group['group'] => array_map(fn (array $item) => array_intersect_key($item, array_flip(['key', 'label', 'url', 'icon'])), $group['items'])])
        ->all();
}

it('lists each collection under Content and each taxonomy under Taxonomies', function () {
    $sidebar = sidebarOf(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete', 'cms.globals.view'));

    expect(array_slice($sidebar['Content'], 0, 3))->toBe([
        ['key' => 'cms-collection-articles', 'label' => 'Artikel', 'url' => 'cms/collections/articles/entries', 'icon' => 'newspaper'],
        ['key' => 'cms-collection-pages', 'label' => 'Pages', 'url' => 'cms/collections/pages/entries', 'icon' => 'file-text'],
        ['key' => 'resources.globals', 'label' => 'Globals', 'url' => 'cms/globals', 'icon' => 'globe'],
    ])->and($sidebar['Taxonomies'])->toBe([
        ['key' => 'cms-taxonomy-categories', 'label' => 'Kategori', 'url' => 'cms/taxonomies/categories/terms', 'icon' => 'tag'],
    ])->and(array_search('Taxonomies', array_keys($sidebar), true))->toBe(array_search('Content', array_keys($sidebar), true) + 1);

    // Only what the user may see, and renames show at once.
    $this->pages->update(['title' => 'Halaman']);
    expect(array_column(sidebarOf(adminUser(Panel::ACCESS, 'cms.entries.pages.view'))['Content'], 'label'))->toBe(['Halaman'])
        ->and(sidebarOf(adminUser(Panel::ACCESS, 'cms.entries.pages.view')))->not->toHaveKey('Taxonomies');
});

it('lists SEO in the Settings menu after Site, not in the sidebar, with Settings current on it', function () {
    $user = adminUser(Panel::ACCESS, 'cms.settings.site', 'cms.settings.locales');
    $sidebar = sidebarOf($user);

    expect(collect($sidebar)->flatten(1)->pluck('key')->all())->not->toContain('cms-seo')
        ->and(collect(app(Panel::class)->navigationFor($user))->flatMap(fn (array $group) => $group['items'])->firstWhere('key', 'settings')['matches'])->toBe(['cms/seo'])
        ->and(array_column(app(Panel::class)->settingsNavigation($user), 'key'))->toBe(['site', 'cms-seo', 'locales']);

    actingAs($user);
    get('/admin/cms/seo')->assertInertia(fn (AssertableInertia $page) => $page->where('settingsNav.1', ['key' => 'cms-seo', 'label' => 'SEO', 'url' => 'cms/seo']));
    // Without cms.settings.site: neither the page nor its place in the menu.
    expect(array_column(app(Panel::class)->settingsNavigation(adminUser(Panel::ACCESS, 'cms.settings.locales')), 'key'))->toBe(['locales']);
});

it('edits entries and terms under their collection and taxonomy, and redirects the old addresses', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete'));
    $entry = app(CreateEntry::class)->handle($this->articles, ['title' => 'Hello'])->entry_id;
    $term = app(SaveTerm::class)->handle($this->categories, ['title' => 'News'])->id;

    get("/admin/cms/collections/articles/entries/{$entry}/edit")->assertOk();
    get("/admin/cms/entries/{$entry}/edit?locale=en")->assertRedirect("/admin/cms/collections/articles/entries/{$entry}/edit?locale=en");
    get("/admin/cms/collections/pages/entries/{$entry}/edit")->assertRedirect("/admin/cms/collections/articles/entries/{$entry}/edit");
    get("/admin/cms/collections/missing/entries/{$entry}/edit")->assertNotFound();

    get("/admin/cms/taxonomies/categories/terms/{$term}/edit")->assertOk();
    get("/admin/cms/terms/{$term}/edit")->assertRedirect("/admin/cms/taxonomies/categories/terms/{$term}/edit");

    // Opening one under a collection someone may see doesn't let them into another's.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.pages.view'));
    get("/admin/cms/collections/pages/entries/{$entry}/edit")->assertForbidden();
});

it('shows collection and taxonomy titles as written, whatever the panel\'s language', function () {
    Collection::query()->create(['handle' => 'pages_x', 'title' => 'Pages']);
    $dir = sys_get_temp_dir().'/sunrice-lang-'.uniqid();
    mkdir($dir);
    file_put_contents("{$dir}/id.json", json_encode(['Pages' => 'Halaman', 'Content' => 'Konten']));
    app('translator')->getLoader()->addJsonPath($dir);
    $user = adminUser(Panel::ACCESS, 'cms.entries.view');
    app(Preferences::class)->set($user, 'locale', 'id');
    actingAs($user);

    get('/admin')->assertInertia(fn (AssertableInertia $page) => $page->where('navigation', fn ($groups) => collect($groups)->contains(fn ($group) => $group['group'] === 'Konten'
        && collect($group['items'])->contains('label', 'Pages'))));
});
