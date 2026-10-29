<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Entries\UnpublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Settings\SiteSettings;
use Sunrice\Cms\Site\Site;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * A live page always wins over a redirect from its address: Structure →
 * Redirects says so under From, and marks such redirects in the list.
 */
beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}', 'listing_route' => '/all-pages']);
    $this->contact = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->pages, ['title' => 'Contact']));
});

/** The warning under From on a redirect's edit form, or null. */
function shadowWarning(Redirect $redirect): ?array
{
    $warning = null;
    get("/admin/cms/redirects/{$redirect->id}/edit")->assertOk()
        ->assertInertia(function (Assert $page) use (&$warning) {
            $warning = $page->toArray()['props']['sections'][0]['fields'][0]['warning'];
        });

    return $warning;
}

function shadowRedirect(string $from, array $more = []): Redirect
{
    return Redirect::query()->create(['from_path' => $from, 'to_url' => '/', ...$more]);
}

it('warns that a live page at From wins, linking to its editor for those who may edit it', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.create', 'cms.redirects.update', 'cms.entries.view', 'cms.entries.update'));
    // Saving takes it, then the edit form it lands on says why it won't apply.
    post('/admin/cms/redirects', ['from_path' => '/contact', 'to_url' => '/', 'status' => '301'])->assertSessionHasNoErrors();
    $redirect = Redirect::query()->where('from_path', '/contact')->firstOrFail();
    get('/contact')->assertOk(); // the page, not the redirect

    $warning = shadowWarning($redirect);
    expect($warning['text'])->toBe('"Contact" (Pages) is live at /contact, and a live page wins over a redirect: this redirect applies only once that page is unpublished, moved to the trash or given another slug.')
        ->and($warning['links'])->toHaveCount(1)
        ->and($warning['links'][0]['label'])->toBe('Edit "Contact"')
        ->and($warning['links'][0]['url'])->toContain("/cms/collections/pages/entries/{$this->contact->entry_id}/edit");

    // Without the right to edit the entry: the warning, no link.
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.update'));
    expect(shadowWarning($redirect)['links'])->toBe([]);
});

it('warns about another language\'s live page only for a redirect in every language', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.update'));
    config(['sunrice-cms.site.fallback' => false]);
    $redirect = shadowRedirect('/kontak');
    expect(shadowWarning($redirect))->toBeNull();

    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($this->contact->entry, 'id'), ['title' => 'Kontak', 'slug' => 'kontak']));
    expect(shadowWarning($redirect))->toBeNull(); // /kontak only: /id/kontak isn't one of its addresses

    $redirect->update(['every_language' => true]);
    expect(shadowWarning($redirect)['text'])->toBe('"Kontak" (Pages) is live at /id/kontak, and a live page wins over a redirect: this redirect applies only once that page is unpublished, moved to the trash or given another slug.');
    get('/kontak')->assertRedirect('/');
    get('/id/kontak')->assertOk();
});

it('says nothing for a draft, an unpublished or a trashed page', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.update'));
    app(CreateEntry::class)->handle($this->pages, ['title' => 'Draft only']);
    $draft = shadowRedirect('/draft-only');
    expect(shadowWarning($draft))->toBeNull();

    $redirect = shadowRedirect('/contact');
    expect(shadowWarning($redirect))->not->toBeNull();
    app(UnpublishEntry::class)->handle($this->contact);
    expect(shadowWarning($redirect))->toBeNull();
    get('/contact')->assertRedirect('/');

    app(PublishEntry::class)->handle(EntryTranslation::query()->findOrFail($this->contact->id));
    expect(shadowWarning($redirect))->not->toBeNull();
    app(TrashEntry::class)->handle($this->contact->entry);
    expect(shadowWarning($redirect))->toBeNull();
});

it('warns about the homepage, a listing page, a term page and the search page', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.update'));
    $settings = app(SiteSettings::class);
    $settings->homepage = $this->contact->entry_id;
    $settings->save();
    app(Site::class)->flush();

    expect(shadowWarning(shadowRedirect('/contact', ['to_url' => '/x']))['text'])
        ->toBe('This is the own address of the homepage, "Contact" (Pages), which sends visitors to / before any redirect.')
        ->and(shadowWarning(shadowRedirect('/', ['to_url' => '/x']))['text'])
        ->toBe('"Contact" (Pages) is the homepage, at /, and a live page wins over a redirect: this redirect applies only once another page is the homepage, or none is.')
        ->and(shadowWarning(shadowRedirect('/all-pages'))['text'])
        ->toBe('/all-pages is the listing page of Pages, and a live page wins over a redirect: this redirect applies only once that listing page has another address, or none.')
        ->and(shadowWarning(shadowRedirect('/search'))['text'])
        ->toBe('/search is the site search page, which wins over a redirect.');

    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}', 'index_route' => '/topics']);
    app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    app(Site::class)->flush();
    expect(shadowWarning(shadowRedirect('/topics/news'))['text'])
        ->toBe('"News" (Topics) has its page at /topics/news, and a live page wins over a redirect: this redirect applies only once that term is moved to the trash or given another slug.')
        ->and(shadowWarning(shadowRedirect('/topics'))['text'])
        ->toBe('/topics is the index page of Topics, and a live page wins over a redirect: this redirect applies only once that index page has another address, or none.');
});

it('warns when the app\'s own route answers the address first', function () {
    Route::get('/app-contact', fn () => 'app');
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.update'));

    expect(shadowWarning(shadowRedirect('/app-contact'))['text'])->toBe("Your app's own route /app-contact answers this address first.")
        // The CMS's own homepage and fallback routes aren't the app's.
        ->and(shadowWarning(shadowRedirect('/nothing-here')))->toBeNull();
});

it('checks no wildcard: it takes only the addresses under its path', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.update'));
    $wildcard = shadowRedirect('/contact/*', ['to_url' => '/x/*']);

    expect(shadowWarning($wildcard))->toBeNull();
    get('/contact')->assertOk();
    get('/contact/more')->assertRedirect('/x/more');
});

it('marks redirects that something answers first in the list, with one query per kind of page', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view'));
    Route::get('/app-only', fn () => 'app');
    foreach (['Alpha', 'Beta', 'Gamma'] as $title) {
        app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->pages, ['title' => $title]));
    }
    shadowRedirect('/alpha');
    shadowRedirect('/app-only');
    shadowRedirect('/b-gone');
    shadowRedirect('/id/alpha');
    shadowRedirect('/all-pages');
    shadowRedirect('/contact/*', ['to_url' => '/x/*']);
    $list = function (): array {
        $cells = [];
        get('/admin/cms/redirects?sort=from_path')->assertOk()->assertInertia(function (Assert $page) use (&$cells) {
            foreach ($page->toArray()['props']['rows']['data'] as $row) {
                $cells[$row['cells']['from_path']] = $row['cells']['shadowed'];
            }
        });

        return $cells;
    };
    $queries = function () use ($list): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $list();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($list())->toBe(['/all-pages' => 'Shadowed by a live page', '/alpha' => 'Shadowed by a live page', '/app-only' => 'Shadowed by an app route', '/b-gone' => null, '/contact/*' => null, '/id/alpha' => 'Shadowed by a live page']);
    $before = $queries();
    // More shadowed redirects, in every language too and by the main language's page standing in: no more queries.
    shadowRedirect('/beta');
    shadowRedirect('/gamma', ['every_language' => true]);
    shadowRedirect('/contact');
    shadowRedirect('/id/contact');
    expect($queries())->toBe($before)
        ->and($list())->toMatchArray(['/beta' => 'Shadowed by a live page', '/gamma' => 'Shadowed by a live page', '/contact' => 'Shadowed by a live page', '/id/contact' => 'Shadowed by a live page']);
});

it('keeps the warning to those who may see redirects', function () {
    $redirect = shadowRedirect('/contact');

    actingAs(adminUser(Panel::ACCESS));
    get("/admin/cms/redirects/{$redirect->id}/edit")->assertForbidden();
    get('/admin/cms/redirects')->assertForbidden();
});

it('finds what answers many paths at once as it does one by one', function () {
    $site = app(Site::class);
    $paths = ['/contact', '/id/contact', '/all-pages', '/search', '/nothing', '/', '/id'];
    $all = $site->servedAll($paths);

    foreach ($paths as $path) {
        $one = $site->served($path);
        expect($all[$path] ?? null)->toEqual($one, $path);
    }
    expect(array_keys($all))->toEqualCanonicalizing(['/contact', '/id/contact', '/all-pages', '/search']);
});

it('warns that an address without a prefix goes to the main language\'s first, when that has one', function () {
    config(['sunrice-cms.locale_prefixes' => ['en' => 'en']]);
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.update'));
    $redirect = shadowRedirect('/old');

    expect(shadowWarning($redirect)['text'])->toBe("Addresses without a language prefix go to the main language's (/en/old) before any redirect: start From with /en.");
    get('/old')->assertRedirect(url('/en/old'));
    // In every language: from each language's prefixed address, none of them taken.
    $redirect->update(['every_language' => true]);
    expect(shadowWarning($redirect))->toBeNull();
});
