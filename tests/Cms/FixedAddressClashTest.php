<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Collections\AttachTaxonomies;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/*
 * Fixed addresses (listing pages, taxonomies' index pages, shared or in a
 * collection) are matched before any {slug} pattern: an entry or term at
 * one of them could never be reached, so neither side may take the other's
 * address, in any language.
 */
beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    $this->books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'blueprint' => 'page', 'route' => '/books/{slug}', 'listing_route' => '/books']);
});

/** The validation errors a call throws, by field. */
function clashErrors(Closure $call): array
{
    try {
        $call();
    } catch (ValidationException $e) {
        return array_map(fn (array $messages): string => $messages[0], $e->errors());
    }

    return [];
}

function clashPage(string $title, ?string $slug = null, string $collection = 'pages'): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(Collection::query()->where('handle', $collection)->firstOrFail(), ['title' => $title, 'slug' => $slug]));
}

it('refuses an entry slug whose address is a listing page\'s, on creating, saving and publishing', function () {
    expect(clashErrors(fn () => app(CreateEntry::class)->handle($this->pages, ['title' => 'Our books', 'slug' => 'books'])))
        ->toBe(['slug' => 'With this slug its URL would be /books, which Books has in English already.']);

    $about = clashPage('About');
    expect(clashErrors(fn () => app(SaveDraft::class)->handle($about, ['slug' => 'books'])))->toHaveKey('slug')
        ->and($about->fresh()?->draft_slug)->toBe('about');

    // Its own listing page too: /{slug} would give a book the address /books.
    $this->books->update(['route' => '/{slug}', 'listing_route' => '/library']);
    $this->pages->update(['route' => '/pages/{slug}']);
    expect(clashErrors(fn () => app(CreateEntry::class)->handle($this->books->fresh(), ['title' => 'Library', 'slug' => 'library'])))
        ->toBe(['slug' => 'With this slug its URL would be /library, which Books has in English already.']);
});

it('makes a slug from the title that keeps clear of fixed addresses', function () {
    $made = app(CreateEntry::class)->handle($this->pages, ['title' => 'Books']);

    expect($made->draft_slug)->toBe('books-2');
    app(PublishEntry::class)->handle($made);
    get('/books-2')->assertOk()->assertSee('Books');
});

it('refuses publishing a draft slug that clashes, but keeps an address the page already had', function () {
    $about = clashPage('About');
    // A clash made before the check (or by a draft copied in): publishing it is refused.
    DB::table('sunrice_entry_translations')->where('id', $about->id)->update(['draft_slug' => 'books']);
    expect(clashErrors(fn () => app(PublishEntry::class)->handle($about->fresh() ?? $about)))->toHaveKey('slug');

    // Live at a clashing address already: other changes still save and publish.
    DB::table('sunrice_entry_translations')->where('id', $about->id)->update(['slug' => 'books', 'draft_slug' => 'books']);
    $saved = app(SaveDraft::class)->handle($about->fresh() ?? $about, ['title' => 'About us']);
    $published = app(PublishEntry::class)->handle($saved);
    expect($published->title)->toBe('About us')->and($published->slug)->toBe('books');

    // And the collection keeps its listing page as it is.
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.update'));
    put('/admin/cms/collections/books', ['title' => 'Books', 'blueprint' => 'page', 'route' => '/books/{slug}', 'listing_route' => '/books'])->assertSessionHasNoErrors();
});

it('checks each language with its own patterns and listing URLs', function () {
    $this->books->setOwnRoute('id', '/buku/{slug}')->setOwnRoute('id', '/buku', 'listing_route')->save();
    app()->forgetInstance(Site::class);
    $about = clashPage('About');
    $tentang = app(CreateTranslation::class)->handle($about->entry, 'id');

    expect(clashErrors(fn () => app(SaveDraft::class)->handle($tentang, ['title' => 'Buku kami', 'slug' => 'buku'])))
        ->toBe(['slug' => 'With this slug its URL would be /id/buku, which Books has in '.Locales::name('id').' already.']);
    // /id/books is no listing page in Indonesian, and /buku none in English.
    expect(clashErrors(fn () => app(SaveDraft::class)->handle($tentang, ['title' => 'Buku', 'slug' => 'books'])))->toBe([])
        ->and(clashErrors(fn () => app(CreateEntry::class)->handle($this->pages, ['title' => 'Buku', 'slug' => 'buku'])))->toBe([]);
});

it('lets entries without pages have any slug', function () {
    Collection::query()->create(['handle' => 'snippets', 'title' => 'Snippets', 'blueprint' => 'page']);

    expect(clashPage('Books', 'books', 'snippets')->slug)->toBe('books');
});

it('refuses a listing or index page URL an entry has, live, in a draft or in the trash', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.update', 'cms.taxonomies.update'));
    $edit = fn (array $input) => put('/admin/cms/collections/books', ['title' => 'Books', 'blueprint' => 'page', 'route' => '/books/{slug}', ...$input]);
    clashPage('About');
    $draft = app(CreateEntry::class)->handle($this->pages, ['title' => 'Contact']);
    $old = clashPage('Old');
    app(TrashEntry::class)->handle($old->entry);

    $edit(['listing_route' => '/about'])->assertSessionHasErrors(['listing_route' => '"About" in Pages has this URL in English already.']);
    $edit(['listing_route' => '/contact'])->assertSessionHasErrors(['listing_route' => '"Contact" in Pages has this URL in English already.']);
    $edit(['listing_route' => '/old'])->assertSessionHasErrors('listing_route');
    $edit(['listing_route' => '/library'])->assertSessionHasNoErrors();

    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'index_route' => '/about'])->assertSessionHasErrors(['index_route' => '"About" in Pages has this URL in English already.']);
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'index_route' => '/tags'])->assertSessionHasNoErrors();
    expect($draft->exists)->toBeTrue()->and($tags->exists)->toBeTrue();
});

it('refuses a listing URL an entry has in another language', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.update'));
    $about = clashPage('About');
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($about->entry, 'id'), ['title' => 'Tentang', 'slug' => 'tentang']));
    $edit = fn (array $input) => put('/admin/cms/collections/books', ['title' => 'Books', 'blueprint' => 'page', 'route' => '/books/{slug}', ...$input]);

    // The main URL, which Indonesian uses without its own: /id/tentang.
    $edit(['listing_route' => '/tentang'])->assertSessionHasErrors(['listing_route' => '"Tentang" in Pages has this URL in '.Locales::name('id').' already.']);
    $edit(['listing_route' => '/books', 'listing_route_id' => '/tentang'])->assertSessionHasErrors('listing_route_id');
    // Its own in Indonesian: /tentang in English is free.
    $edit(['listing_route' => '/tentang', 'listing_route_id' => '/buku'])->assertSessionHasNoErrors();
});

it('refuses a taxonomy\'s index page in a collection at an entry\'s address', function () {
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    clashPage('Tags', 'tags', 'books');

    // On by default: /books/tags, which the book "Tags" has.
    expect(clashErrors(fn () => app(AttachTaxonomies::class)->handle($this->books, [$tags->id => false])))->toHaveKey('taxonomies');
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.update'));
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [(string) $this->books->id], "term_pages_{$this->books->id}" => true, "index_page_{$this->books->id}" => true])
        ->assertSessionHasErrors(["index_route_{$this->books->id}" => '"Tags" in Books has this URL in English already.']);
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [(string) $this->books->id], "term_pages_{$this->books->id}" => true, "index_page_{$this->books->id}" => false])
        ->assertSessionHasNoErrors();
});

it('checks terms against fixed addresses, on their shared pages and in collections, both ways', function () {
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    Collection::query()->create(['handle' => 'events', 'title' => 'Events', 'blueprint' => 'page', 'route' => '/events/{slug}', 'listing_route' => '/topics/events']);

    expect(clashErrors(fn () => app(SaveTerm::class)->handle($topics, ['title' => 'Events', 'slug' => 'events'])))
        ->toBe(['slug' => 'With this slug its URL would be /topics/events, which Events has in English already.'])
        // Made from the title: clear of it.
        ->and(app(SaveTerm::class)->handle($topics, ['title' => 'Events'])->translation('en')?->slug)->toBe('events-2');

    // Its pages in a collection: /books/topics/{slug}, and /books/topics/archive is a listing page.
    app(AttachTaxonomies::class)->handle($this->books, [$topics->id => false]);
    Collection::query()->create(['handle' => 'archive', 'title' => 'Archive', 'blueprint' => 'page', 'route' => '/archive/{slug}', 'listing_route' => '/books/topics/archive']);
    expect(clashErrors(fn () => app(SaveTerm::class)->handle($topics, ['title' => 'Archive', 'slug' => 'archive'])))->toHaveKey('slug');

    // The other way: a listing URL at a term's address, even one in the trash.
    $news = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    app(SaveTermContent::class)->handle($news, ['title' => 'Berita', 'slug' => 'berita'], 'id');
    app(TrashTerm::class)->handle($news);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.update'));
    put('/admin/cms/collections/books', ['title' => 'Books', 'blueprint' => 'page', 'route' => '/books/{slug}', 'listing_route' => '/topics/news'])
        ->assertSessionHasErrors(['listing_route' => '"News" in Topics has this URL in English already.']);
    put('/admin/cms/collections/books', ['title' => 'Books', 'blueprint' => 'page', 'route' => '/books/{slug}', 'listing_route' => '/books', 'listing_route_id' => '/books/topics/berita'])
        ->assertSessionHasErrors(['listing_route_id' => '"Berita" in Topics has this URL in '.Locales::name('id').' already.']);
    expect(Term::withTrashed()->find($news->id)?->trashed())->toBeTrue();
});
