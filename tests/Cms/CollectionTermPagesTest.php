<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Collections\PurgeDeleted;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Taxonomies\AttachCollections;
use Sunrice\Cms\Actions\Terms\DeleteTerm;
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
use Sunrice\Cms\Structure\StructureImportFailed;

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
    $dir = resource_path('views/cms/collections/books/tags');
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/show.blade.php", 'Books tagged {{ $term->title }} in {{ $collection->handle }}');
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
        'books' => ['pages' => true, 'route' => '/reading/{slug}', 'routes' => ['id' => '/bacaan/{slug}'], 'index' => true],
        'films' => ['pages' => false, 'index' => true],
    ]);

    DB::table('sunrice_collection_taxonomy')->update(['term_pages' => true, 'term_route' => null, 'term_routes' => null]);
    app(StructureImporter::class)->import(json_decode((string) json_encode($structure), true));
    expect(DB::table('sunrice_collection_taxonomy')->where('collection_id', $this->books->id)->value('term_route'))->toBe('/reading/{slug}')
        ->and((bool) DB::table('sunrice_collection_taxonomy')->where('collection_id', $this->films->id)->value('term_pages'))->toBeFalse();

    // Term pages at an address another collection's have: refused.
    $clash = json_decode((string) json_encode($structure), true);
    $tags = array_search('tags', array_column($clash['taxonomies'], 'handle'), true);
    $clash['taxonomies'][$tags]['collection_pages']['films'] = ['pages' => true, 'route' => '/reading/{slug}', 'index' => true];
    expect(fn () => app(StructureImporter::class)->import($clash))->toThrow(StructureImportFailed::class, 'has the address /reading/{slug} in en already');
    expect((bool) DB::table('sunrice_collection_taxonomy')->where('collection_id', $this->films->id)->value('term_pages'))->toBeFalse();
});

it('refuses an entry URL a collection\'s term pages have, on a site with one language', function () {
    config(['sunrice-cms.locales' => ['en']]);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.update'));

    put('/admin/cms/collections/films', ['title' => 'Films', 'pages' => true, 'route' => '/books/tags/{slug}'])->assertSessionHasErrors('route');
    expect($this->films->fresh()->route)->toBe('/films/{slug}');
    // Its own URL is no clash with itself.
    put('/admin/cms/collections/films', ['title' => 'Films', 'pages' => true, 'route' => '/films/{slug}'])->assertSessionHasNoErrors();
    get('/books/tags/featured')->assertOk();
});

it('refuses ticking a collection or switching its term pages on where their default URL clashes', function () {
    $cinema = Collection::query()->create(['handle' => 'cinema', 'title' => 'Cinema', 'blueprint' => 'post', 'route' => '/cinema/{slug}', 'listing_route' => '/films']);
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.update'));
    [$books, $films, $c] = [(string) $this->books->id, (string) $this->films->id, (string) $cinema->id];

    // Cinema's default, /films/tags/{slug}, is Films' too.
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books, $films, $c], "term_pages_{$books}" => true, "term_pages_{$films}" => true, "term_pages_{$c}" => true, "index_page_{$books}" => true, "index_page_{$films}" => true, "index_page_{$c}" => false])
        ->assertSessionHasErrors("term_route_{$c}");
    expect($this->tags->collections()->pluck('sunrice_collections.id')->all())->not->toContain($cinema->id);
    // Index pages too: /films/tags.
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books, $films, $c], "term_pages_{$books}" => true, "term_pages_{$films}" => true, "term_pages_{$c}" => false, "index_page_{$books}" => true, "index_page_{$films}" => true, "index_page_{$c}" => true])
        ->assertSessionHasErrors("index_route_{$c}");
    // Ticked with its term pages elsewhere: saved.
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books, $films, $c], "term_pages_{$books}" => true, "term_pages_{$films}" => true, "term_pages_{$c}" => true, "term_route_{$c}" => '/cinema/tags/{slug}', "index_page_{$books}" => true, "index_page_{$films}" => true, "index_page_{$c}" => false])
        ->assertSessionHasNoErrors();
    // Switching Films' off, then Cinema's back to the default, then Films' on again.
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books, $films, $c], "term_pages_{$books}" => true, "term_pages_{$films}" => false, "term_pages_{$c}" => true, "term_route_{$c}" => '', "index_page_{$books}" => true, "index_page_{$films}" => true, "index_page_{$c}" => false])
        ->assertSessionHasNoErrors();
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$books, $films, $c], "term_pages_{$books}" => true, "term_pages_{$films}" => true, "term_pages_{$c}" => true, "index_page_{$books}" => true, "index_page_{$films}" => true, "index_page_{$c}" => false])
        ->assertSessionHasErrors("term_route_{$films}");

    // Attached in code: refused too, naming the clash.
    $this->tags->collections()->detach($cinema->id);
    expect(fn () => app(AttachCollections::class)->handle($this->tags, [$this->books->id, $this->films->id, $cinema->id]))
        ->toThrow(ValidationException::class, 'Films · Tags');
    expect($this->tags->collections()->pluck('sunrice_collections.id')->all())->not->toContain($cinema->id);
});

it('reads the attachments once for the taxonomy\'s form, and writes none when nothing changed', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.update'));
    $attach = function (int $count): void {
        foreach (range(1, $count) as $i) {
            $this->tags->collections()->attach(Collection::query()->create(['handle' => 'more_'.Collection::query()->count(), 'title' => 'More '.Collection::query()->count()])->id);
        }
    };
    $queries = function (callable $request): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        DB::disableQueryLog();

        return array_column(DB::getQueryLog(), 'query');
    };
    $attach(4);
    $edit = $queries(fn () => get('/admin/cms/taxonomies/tags/edit')->assertOk());
    // The attachments read once, not per field, language or collection, and the collections once however often the form is built.
    expect(array_filter($edit, fn (string $sql): bool => str_contains($sql, 'sunrice_collection_taxonomy')))->toHaveCount(1)
        ->and(array_filter($edit, fn (string $sql): bool => str_starts_with($sql, 'select * from "sunrice_collections"')))->toHaveCount(1);

    // Saved as it was: no attachment written.
    $values = [];
    get('/admin/cms/taxonomies/tags/edit')->assertInertia(function (Assert $page) use (&$values) {
        $values = $page->toArray()['props']['values'];
    });
    $saved = $queries(fn () => put('/admin/cms/taxonomies/tags', $values)->assertSessionHasNoErrors());
    expect(array_filter($saved, fn (string $sql): bool => str_starts_with(strtolower($sql), 'update') && str_contains($sql, 'sunrice_collection_taxonomy')))->toBe([]);

    // One change in another language: one write, the other languages' URLs kept.
    $books = $this->books->id;
    $this->tags->collections()->updateExistingPivot($books, ['term_routes' => ['id' => '/buku/tag/{slug}']]);
    $values["term_route_{$books}_id"] = '/buku/label/{slug}';
    $values["index_route_{$books}_id"] = '/buku/label';
    $saved = $queries(fn () => put('/admin/cms/taxonomies/tags', $values)->assertSessionHasNoErrors());
    expect(array_filter($saved, fn (string $sql): bool => str_starts_with(strtolower($sql), 'update') && str_contains($sql, 'sunrice_collection_taxonomy')))->toHaveCount(1);
    $pivot = DB::table('sunrice_collection_taxonomy')->where('collection_id', $books)->first();
    expect(json_decode((string) $pivot->term_routes, true))->toBe(['id' => '/buku/label/{slug}'])
        ->and(json_decode((string) $pivot->index_routes, true))->toBe(['id' => '/buku/label']);
});

it('redirects an old address of a collection\'s term page to that collection\'s', function () {
    $this->tags->collections()->updateExistingPivot($this->films->id, ['term_routes' => ['id' => '/film/tag/{slug}']]);

    get('/id/films/tags/featured')->assertMovedPermanently()->assertRedirect('/id/film/tag/featured');
    get('/id/film/tag/featured')->assertOk()->assertSee('Alien')->assertDontSee('Dune');
});

it('leaves out a menu\'s term link in a collection in the trash, and shows it as not on the site', function () {
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['term_id' => $this->featured->id, 'collection_id' => $this->films->id], ['term_id' => $this->featured->id]]);
    $this->films->delete();
    app(Menus::class)->flush();

    // Not Books' page instead.
    expect(array_column(app(Menus::class)->get('main'), 'url'))->toBe([url('/books/tags/featured')]);
    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view', 'cms.menus.update'));
    get('/admin/cms/menus/main/links')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('items.0.target.live', false)
        ->where('items.1.target.live', true));
});

it('leaves out menu links to a term, taxonomy or collection deleted permanently, never another page instead', function () {
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [
        ['term_id' => $this->featured->id, 'collection_id' => $this->books->id],
        ['taxonomy_id' => $this->tags->id, 'collection_id' => $this->books->id],
        ['term_id' => $this->featured->id, 'collection_id' => $this->films->id],
        ['taxonomy_id' => $this->tags->id, 'collection_id' => $this->films->id],
    ]);
    $urls = function (): array {
        app(Menus::class)->flush();

        return array_column(app(Menus::class)->get('main'), 'url');
    };
    expect($urls())->toBe([url('/books/tags/featured'), url('/books/tags'), url('/films/tags/featured'), url('/films/tags')]);
    $targets = fn (): array => DB::table('sunrice_menu_items')->where('menu_id', $menu->id)->orderBy('sort_order')->get(['term_id', 'taxonomy_id', 'collection_id'])->map(fn ($row) => array_values((array) $row))->all();

    // A collection: its term and index links go, not to the shared pages or another collection's.
    $this->films->delete();
    app(PurgeDeleted::class)->collection($this->films);
    expect($urls())->toBe([url('/books/tags/featured'), url('/books/tags')])
        ->and(array_slice($targets(), 2))->toBe([[null, null, null], [null, null, null]]);

    // A term: not its collection's listing page instead.
    app(DeleteTerm::class)->handle($this->featured, force: true);
    expect($urls())->toBe([url('/books/tags')])
        ->and($targets()[0])->toBe([null, null, null]);

    // A taxonomy: likewise.
    $this->tags->delete();
    app(PurgeDeleted::class)->taxonomy($this->tags);
    expect($urls())->toBe([])
        ->and($targets()[1])->toBe([null, null, null]);
});
