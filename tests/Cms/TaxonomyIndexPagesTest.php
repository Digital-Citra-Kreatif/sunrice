<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\DeleteBlueprint;
use Sunrice\Cms\Actions\Blueprints\RenameBlueprint;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Taxonomies\SaveIndexPage;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\TaxonomyListing;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Site\RouteConflicts;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Support\Seo;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/** After a change made outside the CMS's models (an attachment's columns): the site reads its routes again. */
function indexPagesChanged(): void
{
    app(SiteCache::class)->changed('test');
    app()->forgetInstance(Site::class);
}

beforeEach(function () {
    // The CMS's pages are bundled: there's no page file to look for.
    config(['sunrice-cms.locales' => ['en', 'id'], 'app.name' => 'Acme', 'inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]]]]);
    Blueprint::query()->create(['handle' => 'index', 'title' => 'Index', 'fields' => [
        ['handle' => 'intro', 'type' => 'text', 'translatable' => true],
        ['handle' => 'body', 'type' => 'rich_text', 'translatable' => true],
    ]]);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'translations' => ['id' => ['title' => 'Label']]]);
    $this->books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'blueprint' => 'post', 'route' => '/books/{slug}', 'listing_route' => '/books']);
    $this->films = Collection::query()->create(['handle' => 'films', 'title' => 'Films', 'blueprint' => 'post', 'route' => '/films/{slug}']);
    $this->tags->collections()->attach([$this->books->id, $this->films->id]);
    $this->featured = app(SaveTerm::class)->handle($this->tags, ['title' => 'Featured']);
    $this->scifi = app(SaveTerm::class)->handle($this->tags, ['title' => 'Sci-fi']);
    $publish = fn (Collection $c, string $title, array $terms) => app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($c, ['title' => $title, 'data' => ['tags' => $terms]]));
    $publish($this->books, 'Dune', [$this->featured->id]);
    $publish($this->films, 'Alien', [$this->featured->id, $this->scifi->id]);
    // Its routes are read again once changed (each test's requests share the app).
    app()->forgetInstance(Site::class);
});

it('has no shared index page until switched on, then lists every term with its live entries', function () {
    get('/tags')->assertNotFound();
    expect($this->tags->indexUrl('en'))->toBeNull();

    $this->tags->update(['index_route' => '/tags']);
    $old = app(SaveTerm::class)->handle($this->tags, ['title' => 'Old']);
    app(TrashTerm::class)->handle($old);
    app()->forgetInstance(Site::class);

    get('/tags')->assertOk()->assertViewIs('sunrice-cms::taxonomy-index')
        ->assertSee('<h1>Tags</h1>', false)
        // Without shared term pages: the first collection's term page.
        ->assertSeeInOrder(['<a href="'.url('/books/tags/featured').'">Featured</a>', '<span>(2)</span>'], false)
        ->assertSeeInOrder(['Sci-fi</a>', '<span>(1)</span>'], false)
        ->assertDontSee('Old')
        ->assertViewHas('collection', null)
        ->assertViewHas('terms', fn ($terms) => $terms->pluck('entries_count', 'id')->all() === [$this->featured->id => 2, $this->scifi->id => 1]);
    get('/id/tags')->assertOk()->assertSee('<h1>Label</h1>', false);
    expect($this->tags->indexUrl('en'))->toBe(url('/tags'));

    // Its own URL in a language; the one it had there before redirects.
    $this->tags->setOwnRoute('id', '/label', 'index_route')->save();
    app()->forgetInstance(Site::class);
    get('/id/label')->assertOk()->assertSee('<h1>Label</h1>', false);
    get('/id/tags')->assertRedirect(url('/id/label'));
});

it('gives each collection an index of the terms its entries have, linking to its term pages', function () {
    get('/books/tags')->assertOk()
        ->assertSeeInOrder(['<a href="'.url('/books/tags/featured').'">Featured</a>', '<span>(1)</span>'], false)
        ->assertDontSee('Sci-fi')
        ->assertViewHas('collection', fn ($c) => $c->is($this->books));
    // No listing page: under the collection's handle.
    get('/films/tags')->assertOk()->assertSee(url('/films/tags/featured'))->assertSeeInOrder(['Sci-fi</a>', '<span>(1)</span>'], false);
    expect($this->tags->indexUrl('en', $this->books))->toBe(url('/books/tags'))
        ->and($this->tags->indexUrl('id', $this->books))->toBe(url('/id/books/tags'));

    // Without term pages in the collection: the terms' usual pages.
    $this->tags->collections()->updateExistingPivot($this->books->id, ['term_pages' => false]);
    indexPagesChanged();
    get('/books/tags')->assertOk()->assertSeeInOrder(['<a href="'.url('/films/tags/featured').'">Featured</a>', '<span>(1)</span>'], false);

    // Switched off, and its own URLs per language.
    $this->tags->collections()->updateExistingPivot($this->films->id, ['index_page' => false]);
    $this->tags->collections()->updateExistingPivot($this->books->id, ['index_route' => '/reading/tags', 'index_routes' => ['id' => '/bacaan/label']]);
    indexPagesChanged();
    get('/films/tags')->assertNotFound();
    get('/books/tags')->assertNotFound();
    get('/reading/tags')->assertOk()->assertSee('Featured');
    get('/id/bacaan/label')->assertOk()->assertSee('<h1>Label</h1>', false);
    get('/id/reading/tags')->assertRedirect(url('/id/bacaan/label'));
    expect($this->tags->indexUrl('en', $this->films))->toBeNull();
});

it('gives templates the page\'s values and tries the most specific template first', function () {
    $this->tags->update(['index_route' => '/tags', 'index_blueprint' => 'index']);
    app(SaveIndexPage::class)->handle($this->tags, null, ['intro' => 'Every tag', 'body' => '<p>All <strong>of them</strong></p>']);
    app(SaveIndexPage::class)->handle($this->tags, $this->books, ['intro' => 'Tags of books']);
    app(SaveIndexPage::class)->handle($this->tags, null, ['intro' => 'Semua label'], 'id');

    get('/tags')->assertOk()->assertSee('<p>Every tag</p>', false)->assertSee('<div><p>All <strong>of them</strong></p></div>', false);
    get('/books/tags')->assertOk()->assertSee('<p>Tags of books</p>', false)->assertDontSee('Every tag');
    get('/id/tags')->assertOk()->assertSee('Semua label');

    $dir = resource_path('views/cms');
    File::ensureDirectoryExists("{$dir}/collections/books/tags");
    File::ensureDirectoryExists("{$dir}/taxonomies/tags");
    try {
        File::put("{$dir}/taxonomies/index.blade.php", 'Any index of {{ $taxonomy->handle }}');
        get('/tags')->assertOk()->assertSee('Any index of tags');
        File::put("{$dir}/taxonomies/tags/index.blade.php", 'Tags index: {{ $values[\'intro\'] }} {!! $values[\'body\'] !!} @foreach ($terms as $term){{ $term->title() }}={{ $term->entries_count }};@endforeach {{ implode(\',\', $richText) }} {{ $collection?->handle }}');
        get('/tags')->assertOk()->assertSee('Tags index: Every tag <p>All <strong>of them</strong></p> Featured=2;Sci-fi=1; body', false);
        get('/books/tags')->assertOk()->assertSee('Tags index: Tags of books', false)->assertSee('Featured=1; body books', false);
        File::put("{$dir}/collections/books/tags/index.blade.php", 'Books tags index');
        get('/books/tags')->assertOk()->assertSee('Books tags index');
        get('/films/tags')->assertOk()->assertSee('Tags index:');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('marks the page for layouts: body classes and its other languages', function () {
    $this->tags->update(['index_route' => '/tags']);
    $shared = new Page(Page::TAXONOMY, 'en', 'sunrice-cms::taxonomy-index', taxonomy: $this->tags);
    $books = new Page(Page::TAXONOMY, 'en', 'sunrice-cms::taxonomy-index', collection: $this->books, taxonomy: $this->tags);

    expect($shared->classes())->toContain('page-taxonomy', 'taxonomy-tags')->not->toContain('collection-books')
        ->and($books->classes())->toContain('page-taxonomy', 'taxonomy-tags', 'collection-books')
        ->and($shared->localeUrls())->toBe(['en' => url('/tags'), 'id' => url('/id/tags')])
        ->and($books->localeUrls())->toBe(['en' => url('/books/tags'), 'id' => url('/id/books/tags')]);
});

it('edits an index page\'s values per page and language in the admin', function () {
    $this->tags->update(['index_route' => '/tags', 'index_blueprint' => 'index']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update', 'cms.taxonomies.update'));

    get('/admin/cms/taxonomies/tags/index-page/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Cms/TaxonomyIndex/Edit')
        ->where('taxonomy.handle', 'tags')
        ->where('collection', null)
        ->where('url', url('/tags'))
        ->where('fields.0.handle', 'intro')
        ->where('pages', [['collection' => null, 'title' => 'Shared index page'], ['collection' => 'books', 'title' => 'Books'], ['collection' => 'films', 'title' => 'Films']])
        ->where('version', null)
        ->where('can.update', true));

    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'en', 'data' => ['intro' => 'Every tag'], 'seo' => ['title' => 'All the tags']])->assertSessionHasNoErrors();
    put('/admin/cms/taxonomies/tags/index-page', ['collection' => 'books', 'locale' => 'id', 'data' => ['intro' => 'Label buku']])->assertSessionHasNoErrors();
    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'en', 'data' => [], 'seo' => ['title' => str_repeat('a', 300)]])->assertSessionHasErrors('seo.title');
    // Someone else saved since version 1 was opened.
    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'en', 'data' => ['intro' => 'Again'], 'version' => 1])->assertSessionHasNoErrors();
    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'en', 'data' => ['intro' => 'Mine'], 'version' => 1])->assertSessionHasErrors('version');

    get('/admin/cms/taxonomies/tags/index-page/edit?collection=books&locale=id')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('collection.handle', 'books')
        ->where('url', url('/id/books/tags'))
        ->where('values.intro', 'Label buku')
        ->where('translated', ['en', 'id']));
    get('/admin/cms/taxonomies/tags/index-page/edit')->assertInertia(fn (Assert $page) => $page->where('values.intro', 'Again')->where('seo.title', 'All the tags')->where('version', 2)->missing('values.seo'));
    expect(TaxonomyListing::query()->count())->toBe(2);

    // Only pages that are on.
    $this->tags->collections()->updateExistingPivot($this->films->id, ['index_page' => false]);
    indexPagesChanged();
    get('/admin/cms/taxonomies/tags/index-page/edit?collection=films')->assertNotFound();
    put('/admin/cms/taxonomies/tags/index-page', ['collection' => 'films', 'data' => []])->assertNotFound();
    get('/admin/cms/taxonomies/tags/index-page/edit?collection=nothing')->assertNotFound();
    $this->tags->update(['index_route' => null]);
    get('/admin/cms/taxonomies/tags/index-page/edit')->assertNotFound();
    expect(fn () => app(SaveIndexPage::class)->handle($this->tags, null, []))->toThrow(ValidationException::class);

    // The terms list links to each.
    get('/admin/cms/taxonomies/tags/terms')->assertInertia(fn (Assert $page) => $page
        ->where('taxonomy.indexPages', [['title' => 'Books', 'collection' => 'books', 'url' => route('sunrice.admin.cms.taxonomies.index-page.edit', ['taxonomy' => 'tags', 'collection' => 'books'])]]));
});

it('guards the index page editor with the taxonomy\'s terms permissions', function () {
    $this->tags->update(['index_route' => '/tags']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view'));
    get('/admin/cms/taxonomies/tags/index-page/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.update', false));
    put('/admin/cms/taxonomies/tags/index-page', ['data' => []])->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get('/admin/cms/taxonomies/tags/index-page/edit')->assertForbidden();
});

it('gives index pages SEO of their own, with canonical and alternate URLs', function () {
    $this->tags->update(['index_route' => '/tags']);
    app(SaveIndexPage::class)->handle($this->tags, null, [], 'en', seo: ['title' => 'All the tags', 'description' => 'Browse them']);

    get('/tags')->assertOk()
        ->assertSee('<title>All the tags | Acme</title>', false)
        ->assertSee('<meta name="description" content="Browse them">', false)
        ->assertSee('<link rel="canonical" href="'.url('/tags').'">', false)
        ->assertSee('<link rel="alternate" hreflang="id" href="'.url('/id/tags').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/tags').'">', false)
        ->assertSee('"@type":"CollectionPage"', false);
    get('/id/tags')->assertOk()->assertSee('<title>Label | Acme</title>', false);
    get('/books/tags')->assertOk()
        ->assertSee('<title>Tags | Acme</title>', false)
        ->assertSee('<link rel="canonical" href="'.url('/books/tags').'">', false)
        ->assertSee('<link rel="alternate" hreflang="id" href="'.url('/id/books/tags').'">', false)
        // Breadcrumbs: the collection's listing page, then the index.
        ->assertSee('"name":"Books","item":"'.url('/books').'"', false);

    expect(Seo::forTaxonomyIndex($this->tags->fresh() ?? $this->tags, null, 'en')['page_title'])->toBe('All the tags');
});

it('lists index pages in the sitemap in every language, but those that ask not to be indexed', function () {
    $this->tags->update(['index_route' => '/tags']);
    $this->tags->collections()->updateExistingPivot($this->films->id, ['index_page' => false]);
    indexPagesChanged();

    get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.url('/tags').'</loc>', false)
        ->assertSee('<loc>'.url('/id/tags').'</loc>', false)
        ->assertSee('<loc>'.url('/books/tags').'</loc>', false)
        ->assertSee('<loc>'.url('/id/books/tags').'</loc>', false)
        ->assertSee('<xhtml:link rel="alternate" hreflang="id" href="'.url('/id/books/tags').'"/>', false)
        ->assertDontSee('<loc>'.url('/films/tags').'</loc>', false);

    app(SaveIndexPage::class)->handle($this->tags, null, [], 'id', seo: ['noindex' => true]);
    get('/sitemap.xml')->assertOk()->assertSee('<loc>'.url('/tags').'</loc>', false)->assertDontSee('<loc>'.url('/id/tags').'</loc>', false);
});

it('links menu items to the shared index page or a collection\'s', function () {
    $this->tags->update(['index_route' => '/tags']);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [
        ['taxonomy_id' => $this->tags->id],
        ['taxonomy_id' => $this->tags->id, 'collection_id' => $this->books->id, 'labels' => ['en' => 'Book tags']],
        ['collection_id' => $this->books->id],
    ]);
    expect(app(Menus::class)->get('main'))->sequence(
        fn ($link) => $link->toMatchArray(['label' => 'Tags', 'url' => url('/tags')]),
        fn ($link) => $link->toMatchArray(['label' => 'Book tags', 'url' => url('/books/tags')]),
        fn ($link) => $link->toMatchArray(['label' => 'Books', 'url' => url('/books')]),
    );
    // Its title in a language comes before the main language's label.
    expect(array_column(app(Menus::class)->get('main', 'id'), 'label'))->toBe(['Label', 'Label', 'Books'])
        ->and(array_column(app(Menus::class)->get('main', 'id'), 'url'))->toBe([url('/id/tags'), url('/id/books/tags'), url('/id/books')]);

    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view', 'cms.menus.update'));
    get('/admin/cms/menus/main/links')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('indexes.0.pages', [['collection' => null, 'title' => 'Shared index (all collections)'], ['collection' => $this->books->id, 'title' => 'Books'], ['collection' => $this->films->id, 'title' => 'Films']])
        ->where('items.0.taxonomy_id', $this->tags->id)
        ->where('items.0.target.kind', 'Tags · Index page')
        ->where('items.1.target.kind', 'Tags · Books index')
        ->where('items.1.target.live', true));

    // Only pages that are on; one target at a time; no filters.
    $this->tags->collections()->updateExistingPivot($this->films->id, ['index_page' => false]);
    expect(fn () => app(SaveMenu::class)->handle($menu, [['taxonomy_id' => $this->tags->id, 'collection_id' => $this->films->id]]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveMenu::class)->handle($menu, [['taxonomy_id' => $this->tags->id, 'term_id' => $this->featured->id]]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveMenu::class)->handle($menu, [['taxonomy_id' => $this->tags->id, 'url' => '/x', 'labels' => ['en' => 'X']]]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveMenu::class)->handle($menu, [['taxonomy_id' => $this->tags->id, 'collection_id' => $this->books->id, 'query' => 'a=b']]))->toThrow(ValidationException::class);
    $this->tags->update(['index_route' => null]);
    expect(fn () => app(SaveMenu::class)->handle($menu, [['taxonomy_id' => $this->tags->id]]))->toThrow(ValidationException::class);
    // A link to a page switched off since is left out.
    app(Menus::class)->flush();
    expect(array_column(app(Menus::class)->get('main'), 'url'))->toBe([url('/books/tags'), url('/books')]);
});

it('refuses index URLs a listing page or another index page has', function () {
    $this->tags->update(['index_route' => '/tags']);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);

    expect(RouteConflicts::find($topics, 'index_route', 'en', '/tags'))->toBe('Tags')
        ->and(RouteConflicts::find($topics, 'index_route', 'en', '/books'))->toBe('Books')
        ->and(RouteConflicts::find($topics, 'index_route', 'en', '/books/tags'))->toBe('Books · Tags')
        ->and(RouteConflicts::find($this->films, 'listing_route', 'en', '/tags'))->toBe('Tags')
        ->and(RouteConflicts::find($this->films, 'listing_route', 'id', '/books/tags'))->toBe('Books · Tags')
        ->and(RouteConflicts::forIndexPage($this->films, $this->tags, 'en', '/books/tags'))->toBe('Books · Tags')
        ->and(RouteConflicts::forIndexPage($this->films, $this->tags, 'en', '/tags'))->toBe('Tags')
        ->and(RouteConflicts::forIndexPage($this->books, $this->tags, 'en', '/books/tags'))->toBeNull();

    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.update', 'cms.collections.view', 'cms.collections.update'));
    put('/admin/cms/taxonomies/topics', ['title' => 'Topics', 'index' => true, 'index_route' => '/books'])->assertSessionHasErrors('index_route');
    put('/admin/cms/taxonomies/topics', ['title' => 'Topics', 'index' => true, 'index_route' => '/tags'])->assertSessionHasErrors('index_route');
    $films = (string) $this->films->id;
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'index' => true, 'index_route' => '/tags', 'collections' => [(string) $this->books->id, $films], "index_page_{$this->books->id}" => true, "index_page_{$films}" => true, "index_route_{$films}" => '/books/tags'])
        ->assertSessionHasErrors("index_route_{$films}");
    put('/admin/cms/collections/films', ['title' => 'Films', 'listing' => true, 'listing_route' => '/tags'])->assertSessionHasErrors('listing_route');
});

it('edits the index pages on the taxonomy form', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.update'));
    $books = (string) $this->books->id;
    $films = (string) $this->films->id;
    get('/admin/cms/taxonomies/tags/edit')->assertInertia(fn (Assert $page) => expect($page->toArray()['props']['values'])
        ->toMatchArray(['index' => false, 'index_route' => null, "index_page_{$books}" => true, "index_route_{$books}" => null]));

    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books, $films], 'index' => true, 'index_route' => '', 'index_route_id' => '/label', 'index_blueprint' => 'index',
        "index_page_{$books}" => true, "index_route_{$books}" => '/reading/tags', "index_route_{$books}_id" => '/bacaan/label', "index_page_{$films}" => false])->assertSessionHasNoErrors();
    $tags = $this->tags->fresh();
    expect($tags?->index_route)->toBe('/tags')
        ->and($tags?->ownRoute('id', 'index_route'))->toBe('/label')
        ->and($tags?->index_blueprint)->toBe('index');
    $pivots = DB::table('sunrice_collection_taxonomy')->where('taxonomy_id', $this->tags->id)->get()->keyBy('collection_id');
    expect($pivots[$this->books->id]->index_route)->toBe('/reading/tags')
        ->and(json_decode((string) $pivots[$this->books->id]->index_routes, true))->toBe(['id' => '/bacaan/label'])
        ->and((bool) $pivots[$this->films->id]->index_page)->toBeFalse();
    indexPagesChanged();
    get('/tags')->assertOk();
    get('/id/label')->assertOk();
    get('/reading/tags')->assertOk();
    get('/films/tags')->assertNotFound();

    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books], "index_page_{$books}" => true, "index_route_{$books}" => '/Bad Path'])->assertSessionHasErrors("index_route_{$books}");
    // Off: no shared index page.
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books, $films], 'index' => false])->assertSessionHasNoErrors();
    expect($this->tags->fresh()?->index_route)->toBeNull();
});

it('counts the index blueprint as used, and follows it when renamed', function () {
    $this->tags->update(['index_blueprint' => 'index']);
    expect(app(DeleteBlueprint::class)->usedBy(Blueprint::query()->where('handle', 'index')->firstOrFail()))->toBe(['the index pages of "Tags"']);

    app(RenameBlueprint::class)->handle(Blueprint::query()->where('handle', 'index')->firstOrFail(), 'tag_index');
    expect($this->tags->fresh()?->index_blueprint)->toBe('tag_index');
});

it('carries index pages and their values in the structure', function () {
    $this->tags->update(['index_route' => '/tags', 'index_blueprint' => 'index']);
    $this->tags->setOwnRoute('id', '/label', 'index_route')->save();
    $this->tags->collections()->updateExistingPivot($this->films->id, ['index_page' => false]);
    $this->tags->collections()->updateExistingPivot($this->books->id, ['index_route' => '/reading/tags', 'index_routes' => ['id' => '/bacaan/label']]);
    app(SaveIndexPage::class)->handle($this->tags, null, ['intro' => 'Every tag'], seo: ['title' => 'All the tags']);
    app(SaveIndexPage::class)->handle($this->tags, $this->books, ['intro' => 'Tags of books']);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['taxonomy_id' => $this->tags->id], ['taxonomy_id' => $this->tags->id, 'collection_id' => $this->books->id]]);

    $structure = json_decode((string) json_encode(app(StructureExporter::class)->export()), true);
    $tags = collect($structure['taxonomies'])->firstWhere('handle', 'tags');
    expect($tags)->toMatchArray(['index_route' => '/tags', 'index_blueprint' => 'index', 'routes' => ['id' => ['index_route' => '/label']]])
        ->and($tags['listings']['en'])->toMatchArray(['intro' => 'Every tag', 'seo' => ['title' => 'All the tags', 'description' => null, 'image' => null, 'noindex' => false]])
        ->and($tags['collection_pages']['books'])->toMatchArray(['index' => true, 'index_route' => '/reading/tags', 'index_routes' => ['id' => '/bacaan/label']])
        ->and($tags['collection_pages']['books']['listings']['en']['intro'])->toBe('Tags of books')
        ->and($tags['collection_pages']['films'])->toMatchArray(['index' => false])
        ->and($structure['menus'][0]['items'][0])->toMatchArray(['index' => 'tags'])
        ->and($structure['menus'][0]['items'][1])->toMatchArray(['index' => 'tags', 'collection' => 'books']);

    // Gone, then imported back.
    TaxonomyListing::query()->delete();
    $menu->items()->delete();
    $this->tags->update(['index_route' => null, 'index_blueprint' => null, 'routes' => null]);
    DB::table('sunrice_collection_taxonomy')->update(['index_page' => true, 'index_route' => null, 'index_routes' => null]);
    app(StructureImporter::class)->import($structure);
    indexPagesChanged();

    $again = json_decode((string) json_encode(app(StructureExporter::class)->export()), true);
    expect(collect($again['taxonomies'])->firstWhere('handle', 'tags'))->toEqual($tags)
        ->and($again['menus'])->toEqual($structure['menus']);
    get('/reading/tags')->assertOk()->assertSee('Tags of books');
    get('/id/bacaan/label')->assertOk();

    // A collection's index page may not take another page's address.
    $clash = $structure;
    $clash['taxonomies'][0]['collection_pages']['films'] = ['pages' => true, 'index' => true, 'index_route' => '/tags'];
    expect(fn () => app(StructureImporter::class)->import($clash))->toThrow(Exception::class);
});

it('switches index pages off for attachments made before them', function () {
    $migration = require dirname(__DIR__, 2).'/packages/cms/database/migrations/2026_02_01_000029_add_index_pages_to_sunrice_taxonomies.php';
    $migration->down();
    $migration->up();

    expect(DB::table('sunrice_collection_taxonomy')->pluck('index_page')->map(fn ($on) => (bool) $on)->all())->toBe([false, false]);
    $other = Collection::query()->create(['handle' => 'games', 'title' => 'Games']);
    $this->tags->collections()->attach($other->id);
    expect((bool) DB::table('sunrice_collection_taxonomy')->where('collection_id', $other->id)->value('index_page'))->toBeTrue();
});

it('counts a collection\'s terms from its live translations, not entry by entry', function () {
    $publish = fn (Collection $c, string $title, array $terms) => app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($c, ['title' => $title, 'data' => ['tags' => $terms]]));
    $publish($this->books, 'Solaris', [$this->featured->id, $this->scifi->id]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    get('/books/tags')->assertOk()
        ->assertViewHas('terms', fn ($terms) => $terms->pluck('entries_count', 'id')->all() === [$this->featured->id => 2, $this->scifi->id => 1]);
    $sitemap = get('/sitemap.xml')->assertOk();
    DB::disableQueryLog();
    $sitemap->assertSee(url('/books/tags/sci-fi'))->assertSee(url('/films/tags/sci-fi'));

    // The collection is picked in the translations' subquery (by its index), never on each entry of each term.
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'sunrice_entry_term')
        && preg_match('/sunrice_entries\.collection_id = \?|sunrice_entry_term\.term_id and collection_id = \?/', str_replace(['"', '`'], '', $sql)) === 1)->all())->toBe([]);
});
