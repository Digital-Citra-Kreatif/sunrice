<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\RouteConflicts;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]]]]);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $this->books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'blueprint' => 'post', 'route' => '/books/{slug}', 'listing_route' => '/books']);
    $this->films = Collection::query()->create(['handle' => 'films', 'title' => 'Films', 'blueprint' => 'post', 'route' => '/films/{slug}']);
    $this->tags->collections()->attach([$this->books->id, $this->films->id]);
    $this->featured = app(SaveTerm::class)->handle($this->tags, ['title' => 'Featured']);
    $publish = fn (Collection $c, string $title) => app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($c, ['title' => $title, 'data' => ['tags' => [$this->featured->id]]]));
    $publish($this->books, 'Dune');
    $publish($this->films, 'Alien');
});

it('gives terms a page in each attached collection, listing only its entries', function () {
    get('/books/tags/featured')->assertOk()->assertSee('<h1>Featured</h1>', false)->assertSee('Dune')->assertDontSee('Alien')
        ->assertSee('<link rel="canonical" href="'.url('/books/tags/featured').'">', false);
    // No listing page: under the collection's handle.
    get('/films/tags/featured')->assertOk()->assertSee('Alien')->assertDontSee('Dune');
    // No shared page until the taxonomy has one.
    get('/tags/featured')->assertNotFound();
    get('/books/tags/nothing')->assertNotFound();

    expect($this->featured->url('en', 'books'))->toBe(url('/books/tags/featured'))
        ->and($this->featured->url('en', $this->films))->toBe(url('/films/tags/featured'))
        // No shared page: the first collection's.
        ->and($this->featured->url('en'))->toBe(url('/books/tags/featured'));

    $sitemap = get('/sitemap.xml')->assertOk();
    $sitemap->assertSee(url('/books/tags/featured'))->assertSee(url('/films/tags/featured'));
});

it('lists every collection\'s entries on the shared page, which links prefer', function () {
    $this->tags->update(['route' => '/tags/{slug}']);

    get('/tags/featured')->assertOk()->assertSee('Dune')->assertSee('Alien');
    expect($this->featured->url('en'))->toBe(url('/tags/featured'))
        ->and($this->featured->url('en', 'books'))->toBe(url('/books/tags/featured'));
});

it('switches a collection\'s term pages off, and takes its own URLs per language', function () {
    $this->tags->collections()->updateExistingPivot($this->films->id, ['term_pages' => false]);
    $this->tags->collections()->updateExistingPivot($this->books->id, ['term_route' => '/reading/{slug}', 'term_routes' => ['id' => '/bacaan/{slug}']]);

    get('/films/tags/featured')->assertNotFound();
    get('/books/tags/featured')->assertNotFound();
    get('/reading/featured')->assertOk()->assertSee('Dune');
    expect($this->featured->url('id', 'books'))->toBe(url('/id/bacaan/featured'));
    get('/sitemap.xml')->assertDontSee('/films/tags/featured');
});

it('tries the collection\'s term template first', function () {
    $dir = resource_path('views/cms/books');
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/tags.blade.php", 'Books tagged {{ $term->title }} in {{ $collection->handle }}');
    try {
        get('/books/tags/featured')->assertOk()->assertSee('Books tagged Featured in books');
        get('/films/tags/featured')->assertOk()->assertDontSee('Books tagged');
    } finally {
        File::deleteDirectory(resource_path('views/cms'));
    }
});

it('offers a menu the listing page, the shared term page and each collection\'s term page', function () {
    $this->tags->update(['route' => '/tags/{slug}']);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [
        ['collection_id' => $this->books->id],
        ['term_id' => $this->featured->id],
        ['term_id' => $this->featured->id, 'collection_id' => $this->books->id],
    ]);
    expect(array_column(app(Menus::class)->get('main'), 'url'))->toBe([url('/books'), url('/tags/featured'), url('/books/tags/featured')]);

    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view', 'cms.menus.update'));
    get('/admin/cms/menus/main/links')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('terms.0.pages', [['collection' => null, 'title' => 'Shared page (all collections)'], ['collection' => $this->books->id, 'title' => 'Books'], ['collection' => $this->films->id, 'title' => 'Films']])
        ->where('items.1.target.kind', 'Tags · Shared page')
        ->where('items.2.target.kind', 'Tags · Books')
        ->where('listings.0.title', 'Books'));
});

it('links a menu item to a term\'s page in one collection', function () {
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['term_id' => $this->featured->id, 'collection_id' => $this->films->id]]);
    expect(app(Menus::class)->get('main')[0]['url'] ?? null)->toBe(url('/films/tags/featured'));

    // Not where its terms have no pages, and no filters on a term page.
    $this->tags->collections()->updateExistingPivot($this->films->id, ['term_pages' => false]);
    expect(fn () => app(SaveMenu::class)->handle($menu, [['term_id' => $this->featured->id, 'collection_id' => $this->films->id]]))->toThrow(ValidationException::class);
    expect(fn () => app(SaveMenu::class)->handle($menu, [['term_id' => $this->featured->id, 'collection_id' => $this->books->id, 'query' => ['sort' => 'title']]]))->toThrow(ValidationException::class);
});

it('refuses URLs another collection, taxonomy or term pages have', function () {
    expect(RouteConflicts::forTermPages($this->films, $this->tags, 'en', '/books/{slug}'))->toBe('Books')
        ->and(RouteConflicts::forTermPages($this->films, $this->tags, 'en', '/books/tags/{slug}'))->toBe('Books · Tags')
        ->and(RouteConflicts::forTermPages($this->books, $this->tags, 'en', '/books/tags/{slug}'))->toBeNull();

    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.update'));
    $films = (string) $this->films->id;
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [(string) $this->books->id, $films], "term_pages_{$this->books->id}" => true, "term_pages_{$films}" => true, "term_route_{$films}" => '/books/tags/{slug}'])
        ->assertSessionHasErrors("term_route_{$films}");
});

it('edits each collection\'s term pages on the taxonomy', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.update'));
    $books = (string) $this->books->id;
    $films = (string) $this->films->id;
    get('/admin/cms/taxonomies/tags/edit')->assertInertia(fn (Assert $page) => expect($page->toArray()['props']['values'])
        ->toMatchArray(["term_pages_{$books}" => true, "term_route_{$books}" => null, 'pages' => false]));

    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books, $films], "term_pages_{$books}" => true, "term_route_{$books}" => '/reading/{slug}', "term_route_{$books}_id" => '/bacaan/{slug}', "term_pages_{$films}" => false])
        ->assertSessionHasNoErrors();
    $pivots = DB::table('sunrice_collection_taxonomy')->where('taxonomy_id', $this->tags->id)->get()->keyBy('collection_id');
    expect((bool) $pivots[$this->books->id]->term_pages)->toBeTrue()
        ->and($pivots[$this->books->id]->term_route)->toBe('/reading/{slug}')
        ->and(json_decode((string) $pivots[$this->books->id]->term_routes, true))->toBe(['id' => '/bacaan/{slug}'])
        ->and((bool) $pivots[$this->films->id]->term_pages)->toBeFalse();
    get('/reading/featured')->assertOk();
    get('/films/tags/featured')->assertNotFound();

    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books], "term_pages_{$books}" => true, "term_route_{$books}" => 'bad'])->assertSessionHasErrors("term_route_{$books}");
});

it('carries each collection\'s term pages in the structure', function () {
    $this->tags->collections()->updateExistingPivot($this->films->id, ['term_pages' => false]);
    $this->tags->collections()->updateExistingPivot($this->books->id, ['term_route' => '/reading/{slug}', 'term_routes' => ['id' => '/bacaan/{slug}']]);
    $structure = app(StructureExporter::class)->export();
    $tags = collect($structure['taxonomies'])->firstWhere('handle', 'tags');
    expect(json_decode((string) json_encode($tags['collection_pages']), true))->toBe([
        'books' => ['pages' => true, 'route' => '/reading/{slug}', 'routes' => ['id' => '/bacaan/{slug}']],
        'films' => ['pages' => false],
    ]);

    DB::table('sunrice_collection_taxonomy')->update(['term_pages' => true, 'term_route' => null, 'term_routes' => null]);
    app(StructureImporter::class)->import(json_decode((string) json_encode($structure), true));
    expect(DB::table('sunrice_collection_taxonomy')->where('collection_id', $this->books->id)->value('term_route'))->toBe('/reading/{slug}')
        ->and((bool) DB::table('sunrice_collection_taxonomy')->where('collection_id', $this->films->id)->value('term_pages'))->toBeFalse();
});
