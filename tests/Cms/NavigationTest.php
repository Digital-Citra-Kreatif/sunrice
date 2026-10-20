<?php

declare(strict_types=1);

use Sunrice\Admin\Panel;
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
    $sidebar = sidebarOf(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.terms.manage', 'cms.globals.view'));

    expect(array_slice($sidebar['Content'], 0, 3))->toBe([
        ['key' => 'cms-collection-articles', 'label' => 'Artikel', 'url' => 'cms/collections/articles/entries', 'icon' => 'newspaper'],
        ['key' => 'cms-collection-pages', 'label' => 'Pages', 'url' => 'cms/collections/pages/entries', 'icon' => 'file-text'],
        ['key' => 'cms-globals', 'label' => 'Globals', 'url' => 'cms/globals', 'icon' => 'globe'],
    ])->and($sidebar['Taxonomies'])->toBe([
        ['key' => 'cms-taxonomy-categories', 'label' => 'Kategori', 'url' => 'cms/taxonomies/categories/terms', 'icon' => 'tag'],
    ])->and(array_search('Taxonomies', array_keys($sidebar), true))->toBe(array_search('Content', array_keys($sidebar), true) + 1);

    // Only what the user may see, and renames show at once.
    $this->pages->update(['title' => 'Halaman']);
    expect(array_column(sidebarOf(adminUser(Panel::ACCESS, 'cms.entries.pages.view'))['Content'], 'label'))->toBe(['Halaman'])
        ->and(sidebarOf(adminUser(Panel::ACCESS, 'cms.entries.pages.view')))->not->toHaveKey('Taxonomies');
});

it('edits entries and terms under their collection and taxonomy, and redirects the old addresses', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.terms.manage'));
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
