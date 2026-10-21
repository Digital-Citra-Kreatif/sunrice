<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Settings\SwitchMainLanguage;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Structure\ImportReport;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Structure\StructureImportFailed;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/*
 * Translated URLs: a language's own patterns for a collection's entries
 * and listing page and a taxonomy's term pages (/id/buku/{slug} for
 * /books/{slug}), the old addresses redirecting.
 */
beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'book', 'title' => 'Book', 'fields' => [['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]]]]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $this->books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'blueprint' => 'book', 'route' => '/books/{slug}', 'listing_route' => '/books', 'per_page' => 1]);
    $this->news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    app(SaveTermContent::class)->handle($this->news, ['title' => 'Berita', 'slug' => 'berita'], 'id');
    $this->hello = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->books, ['title' => 'Hello', 'data' => ['topics' => [$this->news->id]]]));
    $this->halo = app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($this->hello->entry, 'id'), ['title' => 'Halo', 'slug' => 'halo']));
    // Not translated: the main language's stands in at /id/… (Site::fallback()).
    $this->other = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->books, ['title' => 'Other']));
});

/** Gives the books Indonesian addresses: /id/buku/{slug} and /id/buku. */
function buku(): Collection
{
    $books = Collection::query()->where('handle', 'books')->firstOrFail();
    $books->setOwnRoute('id', '/buku/{slug}')->setOwnRoute('id', '/buku', 'listing_route')->save();
    app()->forgetInstance(Site::class);

    return $books;
}

function fresh(EntryTranslation $translation): EntryTranslation
{
    return EntryTranslation::query()->with('entry.collection', 'entry.translations')->findOrFail($translation->id);
}

it('keeps the main patterns under the prefix for a language without its own', function () {
    expect($this->books->routeIn('id'))->toBe('/books/{slug}')
        ->and($this->books->ownRoute('id'))->toBeNull()
        ->and(fresh($this->halo)->url())->toBe(url('/id/books/halo'))
        ->and($this->books->listingUrl('id'))->toBe(url('/id/books'));
    get('/id/books/halo')->assertOk()->assertSee('Halo');
    get('/id/books')->assertOk();
    get('/id/topics/berita')->assertOk();
});

it('matches a language\'s own patterns under its prefix, and only in that language', function () {
    $books = buku();

    expect($books->routeIn('id'))->toBe('/buku/{slug}')->and($books->routeIn('en'))->toBe('/books/{slug}')
        ->and($books->routeIn('id', 'listing_route'))->toBe('/buku')
        ->and($books->routes)->toBe(['id' => ['route' => '/buku/{slug}', 'listing_route' => '/buku']]);
    get('/id/buku/halo')->assertOk()->assertSee('Halo');
    get('/id/buku/other')->assertOk()->assertSee('Other'); // the main language's, standing in
    get('/id/buku')->assertOk();
    get('/books/hello')->assertOk();
    get('/books')->assertOk();
    get('/buku/hello')->assertNotFound();
    get('/buku')->assertNotFound();
});

it('builds every URL with the language\'s pattern', function () {
    buku();
    $site = app(Site::class);

    expect(fresh($this->halo)->url())->toBe(url('/id/buku/halo'))
        ->and(fresh($this->hello)->url())->toBe(url('/books/hello'))
        ->and($site->urlIn(fresh($this->other), 'id'))->toBe(url('/id/buku/other'))
        ->and(Collection::query()->find($this->books->id)?->listingUrl('id'))->toBe(url('/id/buku'))
        ->and($site->path(fresh($this->halo), 'halo'))->toBe('/id/buku/halo');
});

it('says the language\'s addresses in the switcher, hreflang links, canonical and breadcrumbs', function () {
    buku();

    get('/books/hello')->assertOk()
        ->assertSee('<link rel="alternate" hreflang="id" href="'.url('/id/buku/halo').'">', false);
    get('/id/buku/halo')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/id/buku/halo').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/books/hello').'">', false)
        // Breadcrumbs (structured data) go through the language's listing page.
        ->assertSee(json_encode(url('/id/buku'), JSON_UNESCAPED_SLASHES), false);
    get('/books')->assertOk()->assertSee('<link rel="alternate" hreflang="id" href="'.url('/id/buku').'">', false);

    // The language switcher (Content::localeUrls()): an entry, one standing in, a listing page, a term's page.
    get('/books/hello');
    expect(Content::localeUrls())->toBe(['en' => url('/books/hello'), 'id' => url('/id/buku/halo')]);
    get('/books/other');
    expect(Content::localeUrls())->toBe(['en' => url('/books/other'), 'id' => url('/id/buku/other')]);
    expect((new Page(Page::LISTING, 'en', 'cms.listing', collection: Collection::query()->find($this->books->id), pageNumber: 2))->localeUrls())
        ->toBe(['en' => url('/books?page=2'), 'id' => url('/id/buku?page=2')]);
    $this->topics->setOwnRoute('id', '/topik/{slug}')->save();
    app()->forgetInstance(Site::class);
    get('/topics/news');
    expect(Content::localeUrls())->toBe(['en' => url('/topics/news'), 'id' => url('/id/topik/berita')]);
});

it('pages the listing at the language\'s address', function () {
    buku();

    // One per page: page 2 links back to page 1 and on, at /id/buku.
    get('/id/buku')->assertOk()->assertSee(url('/id/buku').'?page=2', false);
    get('/id/buku?page=2')->assertOk()->assertSee('<link rel="canonical" href="'.url('/id/buku').'?page=2">', false);
});

it('lists the language\'s addresses in the sitemap, with alternates', function () {
    buku();
    $this->topics->setOwnRoute('id', '/topik/{slug}')->save();

    get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.url('/id/buku/halo').'</loc>', false)
        ->assertSee('<loc>'.url('/id/buku').'</loc>', false)
        ->assertSee('<loc>'.url('/id/topik/berita').'</loc>', false)
        ->assertSee('hreflang="id" href="'.url('/id/buku/halo').'"', false)
        ->assertDontSee(url('/id/books/'), false);
});

it('links menus to the language\'s listing page, filters and all', function () {
    buku();
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [
        ['collection_id' => $this->books->id, 'query' => 'topic=news', 'children' => []],
        ['entry_id' => $this->hello->entry_id, 'children' => []],
    ]);
    app()->setLocale('id');

    $urls = array_column(app(Menus::class)->get('main', 'id'), 'url');
    expect($urls)->toBe([url('/id/buku').'?topic=news', url('/id/buku/halo')]);
});

it('gives term pages the language\'s pattern', function () {
    $this->topics->setOwnRoute('id', '/topik/{slug}')->save();
    app()->forgetInstance(Site::class);
    $news = Term::query()->with('translations', 'taxonomy')->findOrFail($this->news->id);

    expect($news->url('id'))->toBe(url('/id/topik/berita'))->and($news->url('en'))->toBe(url('/topics/news'));
    get('/id/topik/berita')->assertOk()->assertSee('Berita')
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/topics/news').'">', false);
    get('/id/topics/berita')->assertRedirect(url('/id/topik/berita'))->assertStatus(301);
    get('/topik/news')->assertNotFound();
});

it('sends the old addresses to the language\'s own ones', function () {
    buku();

    get('/id/books/halo?utm=x')->assertStatus(301)->assertRedirect(url('/id/buku/halo').'?utm=x');
    get('/id/books')->assertStatus(301)->assertRedirect(url('/id/buku'));
    get('/id/books/other')->assertStatus(301)->assertRedirect(url('/id/buku/other'));
    // The main language's slug of a page translated: to its own address.
    get('/id/books/hello')->assertStatus(301)->assertRedirect(url('/id/buku/halo'));
    get('/id/books/nothing')->assertNotFound();

    // Without the fallback, a page not translated has no address in the language.
    config(['sunrice-cms.site.fallback' => false]);
    get('/id/books/other')->assertNotFound();
});

it('redirects from the pattern a language had before when it changes, or goes', function () {
    $books = buku();
    $books->setOwnRoute('id', '/kitab/{slug}')->setOwnRoute('id', '/kitab', 'listing_route')->save();
    app()->forgetInstance(Site::class);

    expect($books->previousRoutes('id'))->toBe(['/buku/{slug}'])->and($books->previousRoutes('id', 'listing_route'))->toBe(['/buku']);
    get('/id/buku/halo')->assertStatus(301)->assertRedirect(url('/id/kitab/halo'));
    get('/id/buku')->assertStatus(301)->assertRedirect(url('/id/kitab'));
    get('/id/books/halo')->assertStatus(301)->assertRedirect(url('/id/kitab/halo'));

    // Back to the main pattern: both earlier ones redirect there; the one in use isn't "previous".
    $books->setOwnRoute('id', null)->setOwnRoute('id', null, 'listing_route')->save();
    app()->forgetInstance(Site::class);
    expect($books->previousRoutes('id'))->toBe(['/kitab/{slug}', '/buku/{slug}'])->and($books->routes)->toBeNull();
    get('/id/kitab/halo')->assertRedirect(url('/id/books/halo'));
    get('/id/buku')->assertRedirect(url('/id/books'));
    get('/id/books/halo')->assertOk();

    // Taking a previous pattern again: it's the address, not a redirect.
    $books->setOwnRoute('id', '/buku/{slug}')->save();
    expect($books->previousRoutes('id'))->toBe(['/kitab/{slug}']);
});

it('keeps an own pattern only when it differs from the main one', function () {
    $this->books->setOwnRoute('id', '/books/{slug}')->setOwnRoute('id', '  ', 'listing_route')->save();

    expect($this->books->routes)->toBeNull()->and($this->books->previous_routes)->toBeNull();
});

it('tries the most specific pattern first, collections before taxonomies', function () {
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    $about = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($pages, ['title' => 'Buku']));
    buku();

    // /id/buku/halo: the books' "/buku/{slug}" before the pages' "/{slug}".
    get('/id/buku/halo')->assertOk()->assertSee('Halo');
    get('/id/buku')->assertOk(); // the listing page before a page "buku"
    // A tie with a taxonomy's own pattern: the collection's page.
    $this->topics->setOwnRoute('id', '/buku/{slug}')->save();
    app()->forgetInstance(Site::class);
    get('/id/buku/halo')->assertOk()->assertSee('Halo');
    get('/id/buku/berita')->assertNotFound();
    expect($about->fresh())->not->toBeNull();
});

it('redirects a page\'s old address at the language\'s pattern when its slug changes', function () {
    buku();
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(fresh($this->halo), ['title' => 'Halo', 'slug' => 'hai']));

    expect(Redirect::query()->where('from_path', '/id/buku/halo')->value('to_url'))->toBe('/id/buku/hai');
    get('/id/buku/halo')->assertRedirect('/id/buku/hai');
});

it('reads the patterns from the cache, and forgets them when they change', function () {
    config(['sunrice-cms.cache.enabled' => true, 'sunrice-cms.cache.pages' => true]);
    get('/id/books/halo')->assertOk();
    get('/id/books')->assertOk();

    buku();
    get('/id/buku/halo')->assertOk();
    get('/id/buku')->assertOk();
    get('/id/books/halo')->assertStatus(301);
    get('/id/books')->assertStatus(301);

    // The patterns come from one cached read: no scan of the collections or taxonomies per request.
    app()->forgetInstance(Site::class);
    DB::enableQueryLog();
    app(Site::class)->match('/id/buku/other');
    app(Site::class)->listing('/id/buku');
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'not null') && (str_contains($sql, 'from "sunrice_collections"') || str_contains($sql, 'from `sunrice_collections`') || str_contains($sql, 'sunrice_taxonomies')))->values()->all())->toBe([]);
});

it('offers each other language\'s URL fields in the collection and taxonomy forms, following the main ones', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.update', 'cms.taxonomies.update'));
    $fields = fn (array $sections) => collect($sections)->flatMap(fn (array $section) => $section['fields'])->keyBy('name');

    get('/admin/cms/collections/books/edit')->assertOk()->assertInertia(function (Assert $page) use ($fields) {
        $all = $fields($page->toArray()['props']['sections']);
        expect($all['route_id'])->toMatchArray(['label' => 'Entry URL in '.Locales::name('id'), 'placeholderFrom' => ['field' => 'route', 'fallback' => '/{handle}/{slug}'], 'showWhen' => ['field' => 'pages', 'value' => null]])
            ->and($all['listing_route_id'])->toMatchArray(['label' => 'Listing page URL in '.Locales::name('id'), 'placeholderFrom' => ['field' => 'listing_route', 'fallback' => '/{handle}']])
            ->and($all['listing_route_id']['links'][0])->toMatchArray(['label' => 'View on site', 'url' => url('/id/books')])
            ->and($page->toArray()['props']['values'])->toMatchArray(['route_id' => null, 'listing_route_id' => null]);
    });
    get('/admin/cms/taxonomies/topics/edit')->assertOk()->assertInertia(function (Assert $page) use ($fields) {
        $all = $fields($page->toArray()['props']['sections']);
        expect($all['route_id'])->toMatchArray(['label' => 'Term page URL in '.Locales::name('id')])
            ->and($all['route_id']['links'][0])->toMatchArray(['label' => 'View a term page', 'url' => url('/id/topics/berita')]);
    });
});

it('saves a language\'s own patterns from the form, checking them', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.update', 'cms.taxonomies.update'));
    $edit = fn (array $input) => put('/admin/cms/collections/books', ['title' => 'Books', 'blueprint' => 'book', ...$input]);

    $edit(['route_id' => '/buku/{slug}', 'listing_route_id' => '/buku'])->assertSessionHasNoErrors();
    expect(Collection::query()->find($this->books->id)?->routes)->toBe(['id' => ['route' => '/buku/{slug}', 'listing_route' => '/buku']]);
    get('/admin/cms/collections/books/edit')->assertInertia(fn (Assert $page) => $page->where('values.route_id', '/buku/{slug}')->where('values.listing_route_id', '/buku'));

    $edit(['route_id' => '/buku'])->assertSessionHasErrors('route_id');
    $edit(['route_id' => '/buku/{author}/{slug}'])->assertSessionHasErrors('route_id'); // not a field of the blueprint
    $edit(['listing_route_id' => '/Buku Baru'])->assertSessionHasErrors('listing_route_id');
    put('/admin/cms/taxonomies/topics', ['title' => 'Topics', 'route_id' => '/topik/{topic}/{slug}'])->assertSessionHasErrors('route_id');

    // No two collections at one address in a language: another's own pattern, or its main one used there.
    Collection::query()->create(['handle' => 'news', 'title' => 'News', 'blueprint' => 'book', 'route' => '/news/{slug}', 'listing_route' => '/news']);
    $edit(['route_id' => '/news/{slug}'])->assertSessionHasErrors(['route_id' => 'News has this URL in '.Locales::name('id').' already.']);
    $edit(['listing_route_id' => '/news'])->assertSessionHasErrors('listing_route_id');
    put('/admin/cms/collections/news', ['title' => 'News', 'blueprint' => 'book', 'route_id' => '/buku/{slug}'])->assertSessionHasErrors(['route_id' => 'Books has this URL in '.Locales::name('id').' already.']);
    // The main pattern too, in a language that uses it.
    put('/admin/cms/collections/news', ['title' => 'News', 'blueprint' => 'book', 'route' => '/buku/{slug}'])->assertSessionHasErrors(['route' => 'Books has this URL in '.Locales::name('id').' already.']);
    put('/admin/cms/collections/news', ['title' => 'News', 'blueprint' => 'book', 'route' => '/buku/{slug}', 'route_id' => '/berita/{slug}'])->assertSessionHasNoErrors();

    // Emptied: the main pattern under the prefix again, the old one redirecting.
    $edit(['route_id' => '', 'listing_route_id' => ''])->assertSessionHasNoErrors();
    expect(Collection::query()->find($this->books->id)?->routes)->toBeNull();
    get('/id/buku/halo')->assertRedirect(url('/id/books/halo'));
});

it('exports and imports the languages\' own patterns, checking them', function () {
    buku();
    $this->topics->setOwnRoute('id', '/topik/{slug}')->save();
    $this->books->refresh()->setOwnRoute('id', '/kitab/{slug}')->save(); // a previous one: this site's history, not carried

    $exported = app(StructureExporter::class)->export();
    $books = collect($exported['collections'])->firstWhere('handle', 'books');
    expect($books['routes'])->toBe(['id' => ['route' => '/kitab/{slug}', 'listing_route' => '/buku']])
        ->and($books)->not->toHaveKey('previous_routes')
        ->and(collect($exported['taxonomies'])->firstWhere('handle', 'topics')['routes'])->toBe(['id' => ['route' => '/topik/{slug}']]);

    // Into a site without them: the same patterns.
    $this->books->forceFill(['routes' => null, 'previous_routes' => null])->save();
    $this->topics->forceFill(['routes' => null])->save();
    app(StructureImporter::class)->import($exported);
    expect(Collection::query()->find($this->books->id)?->routes)->toBe(['id' => ['route' => '/kitab/{slug}', 'listing_route' => '/buku']])
        ->and(Taxonomy::query()->find($this->topics->id)?->routes)->toBe(['id' => ['route' => '/topik/{slug}']]);
    expect(array_unique(array_column(app(StructureImporter::class)->import($exported, dryRun: true)->items(), 'status')))->toBe([ImportReport::UNCHANGED]);

    // Checked like the admin's: the pattern, and no other collection at that address in the language.
    $bad = $exported;
    $bad['collections'][array_search('books', array_column($bad['collections'], 'handle'), true)]['routes'] = ['id' => ['route' => '/kitab']];
    expect(fn () => app(StructureImporter::class)->import($bad))->toThrow(StructureImportFailed::class);
    Collection::query()->create(['handle' => 'news', 'title' => 'News', 'route' => '/berita/{slug}']);
    $bad['collections'][array_search('books', array_column($bad['collections'], 'handle'), true)]['routes'] = ['id' => ['route' => '/berita/{slug}']];
    expect(fn () => app(StructureImporter::class)->import($bad))->toThrow(StructureImportFailed::class);
    $bad['collections'][array_search('books', array_column($bad['collections'], 'handle'), true)]['routes'] = ['id' => ['slug' => '/x/{slug}']];
    expect(fn () => app(StructureImporter::class)->import($bad))->toThrow(StructureImportFailed::class);

    // Patterns in a language the site doesn't have: like its other texts, the import stops, or leaves them out.
    $other = $exported;
    $other['collections'][array_search('books', array_column($other['collections'], 'handle'), true)]['routes'] = ['ms' => ['route' => '/buku-ms/{slug}']];
    expect(fn () => app(StructureImporter::class)->import($other))->toThrow(StructureImportFailed::class);
    app(StructureImporter::class)->import($other, skipOtherLanguages: true);
    expect(Collection::query()->find($this->books->id)?->routes)->toBeNull();
});

it('keeps every address when the main language switches: the patterns swap', function () {
    buku();
    $this->topics->setOwnRoute('id', '/topik/{slug}')->save();

    app(SwitchMainLanguage::class)->handle('id', copyMissing: true);
    app()->forgetInstance(Site::class);

    $books = Collection::query()->findOrFail($this->books->id);
    expect([$books->route, $books->listing_route, $books->routes])->toBe(['/buku/{slug}', '/buku', ['en' => ['route' => '/books/{slug}', 'listing_route' => '/books']]])
        ->and(Taxonomy::query()->findOrFail($this->topics->id)->only('route', 'routes'))->toBe(['route' => '/topik/{slug}', 'routes' => ['en' => ['route' => '/topics/{slug}']]]);
    get('/buku/halo')->assertOk()->assertSee('Halo');
    get('/buku')->assertOk();
    get('/topik/berita')->assertOk();
    get('/en/books/hello')->assertOk()->assertSee('Hello');
    get('/en/books')->assertOk();
    // The old main language's addresses: to its new prefix.
    get('/books/hello')->assertRedirect('/en/books/hello');
});
