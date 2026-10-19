<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\RestoreEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Entries\UnpublishEntry;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Events\ContentChanged;
use Sunrice\Cms\Events\EntryDeleted;
use Sunrice\Cms\Events\EntryPublished;
use Sunrice\Cms\Events\EntryRestored;
use Sunrice\Cms\Events\EntryTrashed;
use Sunrice\Cms\Events\EntryUnpublished;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Site\PageCache;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'dated' => true, 'listing_route' => '/blog']);
});

function cachedPost(string $title, bool $publish = true): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle(test()->blog, ['title' => $title]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

/** The response, and how many database queries it took. */
function withQueries(Closure $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [$response, $count];
}

it('fires events when entries go live, off, to the trash and back', function () {
    Event::fake([EntryPublished::class, EntryUnpublished::class, EntryTrashed::class, EntryRestored::class, EntryDeleted::class]);
    $hello = cachedPost('Hello');
    app(UnpublishEntry::class)->handle($hello);
    app(TrashEntry::class)->handle($hello->entry);
    app(RestoreEntry::class)->handle($hello->entry);
    app(TrashEntry::class)->handle($hello->entry);
    app(ForceDeleteEntry::class)->handle($hello->entry);

    Event::assertDispatched(EntryPublished::class, fn (EntryPublished $e) => $e->translation->is($hello) && ! $e->scheduled);
    Event::assertDispatched(EntryUnpublished::class, 1);
    Event::assertDispatched(EntryTrashed::class, 2);
    Event::assertDispatched(EntryRestored::class, fn (EntryRestored $e) => $e->entry->is($hello->entry));
    Event::assertDispatched(EntryDeleted::class, 1);
});

it('says when the site\'s content changed, but not for drafts', function () {
    $changes = [];
    Event::listen(ContentChanged::class, function (ContentChanged $e) use (&$changes) {
        $changes[] = $e->what;
    });
    $draft = cachedPost('Draft', publish: false);
    app(SaveDraft::class)->handle($draft, ['title' => 'Still a draft']);
    expect($changes)->toBe([]);

    app(PublishEntry::class)->handle($draft);
    expect($changes)->toContain('entrytranslation '.$draft->id);

    $changes = [];
    app(SaveTerm::class)->handle(Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']), ['title' => 'News']);
    Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    expect($changes)->toContain('taxonomy 1')->toContain('term 1')->toContain('menu 1');
});

it('reads which page is at an address, menus and the sitemap from the cache, until the content changes', function () {
    $hello = cachedPost('Hello');
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['entry_id' => $hello->entry_id, 'labels' => ['en' => 'Hello']]]);

    [, $cold] = withQueries(fn () => get('/blog/hello')->assertOk());
    [, $warm] = withQueries(fn () => get('/blog/hello')->assertOk());
    expect($warm)->toBeLessThan($cold);

    expect(Cms::menu('main')[0]['label'])->toBe('Hello');
    [, $menuQueries] = withQueries(fn () => app(Menus::class)->flush() ?? Cms::menu('main'));
    expect($menuQueries)->toBe(0);

    get('/sitemap.xml')->assertOk();
    [$sitemap, $sitemapQueries] = withQueries(fn () => get('/sitemap.xml'));
    expect($sitemapQueries)->toBe(0)->and($sitemap->getContent())->toContain(url('/blog/hello'));

    // A new slug: the old address redirects, the new one shows it, the sitemap and menu follow.
    app(SaveDraft::class)->handle($hello, ['slug' => 'hello-there']);
    app(PublishEntry::class)->handle($hello->fresh());
    get('/blog/hello')->assertRedirect('/blog/hello-there');
    get('/blog/hello-there')->assertOk();
    expect(get('/sitemap.xml')->getContent())->toContain(url('/blog/hello-there'))->not->toContain(url('/blog/hello').'<');
    app(Menus::class)->flush();
    expect(Cms::menu('main')[0]['url'])->toBe(url('/blog/hello-there'));

    app(UnpublishEntry::class)->handle($hello->fresh());
    get('/blog/hello-there')->assertNotFound();
});

it('keeps to a query budget per page', function () {
    $news = app(SaveTerm::class)->handle(Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']), ['title' => 'News']);
    foreach (range(1, 12) as $i) {
        cachedPost("Post {$i}");
    }
    foreach (['/blog/post-1', '/blog', '/tags/news', '/sitemap.xml'] as $path) {
        get($path)->assertOk(); // warm the cache
    }

    // However many entries: no queries in loops.
    expect(withQueries(fn () => get('/blog/post-1'))[1])->toBeLessThanOrEqual(6)
        ->and(withQueries(fn () => get('/blog'))[1])->toBeLessThanOrEqual(7)
        ->and(withQueries(fn () => get('/blog?page=2'))[1])->toBeLessThanOrEqual(7)
        ->and(withQueries(fn () => get('/tags/news'))[1])->toBeLessThanOrEqual(8)
        ->and(withQueries(fn () => get('/sitemap.xml'))[1])->toBe(0);
});

it('works without the cache', function () {
    config(['sunrice-cms.cache.enabled' => false]);
    cachedPost('Hello');

    get('/blog/hello')->assertOk();
    expect(app(SiteCache::class)->remember('x', fn () => 'made'))->toBe('made')
        ->and(app(SiteCache::class)->store()->get(app(SiteCache::class)->key('x')))->toBeNull();
});

it('keeps whole pages for visitors, each with their own CSRF token and none of their cookies', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text']]]);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    View::addNamespace('cached', __DIR__.'/Fixtures/views');
    $this->blog->update(['template' => 'cached::cms.with-form']);
    cachedPost('Hello');

    $first = get('/blog/hello')->assertOk()->assertHeader('X-Cache-Status', 'MISS');
    $token = csrfIn($first);
    session()->flush();
    session()->regenerateToken();
    $second = get('/blog/hello')->assertOk()->assertHeader('X-Cache-Status', 'HIT');
    expect($first->headers->getCookies())->not->toBe([]); // the first visitor's session
    expect(collect($second->headers->getCookies())->map->getValue()->intersect(collect($first->headers->getCookies())->map->getValue())->all())->toBe([]);
    expect(csrfIn($second))->toBe(session()->token())->not->toBe($token)
        ->and($second->getContent())->not->toContain('__sunrice_csrf_token__');

    // Changed content: rendered again.
    app(SaveDraft::class)->handle($post = EntryTranslation::query()->firstOrFail(), ['title' => 'Hello again']);
    app(PublishEntry::class)->handle($post->fresh());
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS')->assertSee('Hello again');

    // Not for people signed in, nor with errors flashed to them, nor what's skipped.
    actingAs(adminUser());
    get('/blog/hello')->assertHeaderMissing('X-Cache-Status');
    auth()->logout();
    get('/blog/hello')->assertHeader('X-Cache-Status', 'HIT');
    session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag(['name' => 'Required.'])));
    get('/blog/hello')->assertHeaderMissing('X-Cache-Status');
    session()->flush();
    app(PageCache::class)->skipWhen(fn ($request, ?Page $page) => $request->cookies->has('cart'));
    $this->withCookie('cart', '1')->get('/blog/hello')->assertHeaderMissing('X-Cache-Status'); // not served the kept page
    $this->defaultCookies = [];
    app(PageCache::class)->skipWhen(fn ($request, ?Page $page) => $page?->entry?->title === 'Hello again');
    app(SiteCache::class)->changed('test');
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS'); // not kept
    get('/nowhere')->assertNotFound();
    get('/nowhere')->assertNotFound()->assertHeader('X-Cache-Status', 'MISS');
});

function csrfIn(TestResponse $response): ?string
{
    preg_match('/name="_token" value="([^"]+)"/', (string) $response->getContent(), $m);

    return $m[1] ?? null;
}

it('announces scheduled entries when their time comes, and clears the caches', function () {
    config(['sunrice-cms.cache.pages' => true]);
    travelTo('2026-10-19 09:00');
    artisan('sunrice:publish-scheduled')->assertSuccessful();
    cachedPost('Now');
    app(PublishEntry::class)->handle(cachedPost('Later', publish: false), new DateTime('2026-10-19 09:30'));
    expect(get('/blog')->assertOk()->getContent())->toContain('Now')->not->toContain('Later');

    Event::fake([EntryPublished::class]);
    travelTo('2026-10-19 09:29');
    artisan('sunrice:publish-scheduled')->expectsOutput('0 scheduled entries went live.');
    travelTo('2026-10-19 09:30:30');
    get('/blog')->assertDontSee('Later'); // the kept page, until the scheduler runs
    artisan('sunrice:publish-scheduled')->expectsOutput('1 scheduled entry went live.');
    artisan('sunrice:publish-scheduled')->expectsOutput('0 scheduled entries went live.');

    Event::assertDispatched(EntryPublished::class, fn (EntryPublished $e) => $e->scheduled && $e->translation->title === 'Later');
    get('/blog')->assertSee('Later');
});
