<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Spatie\ResponseCache\ResponseCache;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Comments\PostComment;
use Sunrice\Cms\Actions\Comments\SetCommentStatus;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\RestoreEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Entries\UnpublishEntry;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\CmsServiceProvider;
use Sunrice\Cms\Events\ContentChanged;
use Sunrice\Cms\Events\EntryDeleted;
use Sunrice\Cms\Events\EntryPublished;
use Sunrice\Cms\Events\EntryRestored;
use Sunrice\Cms\Events\EntryTrashed;
use Sunrice\Cms\Events\EntryUnpublished;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Comment;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Submission;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Settings\CacheSettings;
use Sunrice\Cms\Settings\SiteSettings;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Site\PageCache;
use Sunrice\Cms\Site\PageCacheHasher;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
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
    $taxonomy = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $term = app(SaveTerm::class)->handle($taxonomy, ['title' => 'News']);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    expect($changes)->toContain('taxonomy '.$taxonomy->id)->toContain('term '.$term->id)->toContain('menu '.$menu->id);
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
    // The site's share image (on every page) is read once per content version, not per page.
    $image = Media::query()->create(['disk' => 'public', 'path' => 'media/share.jpg', 'filename' => 'share.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
    app(SiteSettings::class)->fill(['image' => $image->id])->save();
    foreach (['/blog/post-1', '/blog', '/tags/news'] as $path) {
        get($path)->assertOk()->assertSee('og:image" content="'.$image->url(), false); // warm the cache
    }
    get('/sitemap.xml')->assertOk();

    // However many entries: no queries in loops.
    expect(withQueries(fn () => get('/blog/post-1'))[1])->toBeLessThanOrEqual(5)
        ->and(withQueries(fn () => get('/blog'))[1])->toBeLessThanOrEqual(6)
        ->and(withQueries(fn () => get('/blog?page=2'))[1])->toBeLessThanOrEqual(6)
        ->and(withQueries(fn () => get('/tags/news'))[1])->toBeLessThanOrEqual(7)
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
    // A package's views, under the site's template prefixes.
    config(['sunrice-cms.site.template_prefixes' => ['cms.', 'cached::']]);
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

it('starts a new version only for the areas a change touches', function () {
    $cache = app(SiteCache::class);
    $versions = fn (): array => array_combine(SiteCache::AREAS, array_map(fn (string $area) => $cache->version($area), SiteCache::AREAS));

    $was = $versions();
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    expect($cache->version(SiteCache::MENUS))->toBe($was['menus'] + 1)
        ->and($cache->version(SiteCache::PAGES))->toBe($was['pages'] + 1)
        ->and($cache->version(SiteCache::ROUTES))->toBe($was['routes'])
        ->and($cache->version(SiteCache::SITEMAP))->toBe($was['sitemap']);

    $was = $versions();
    $media = Media::query()->create(['disk' => 'public', 'path' => 'media/a.jpg', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
    $media->update(['alt' => 'A photo']);
    expect($cache->version(SiteCache::PAGES))->toBe($was['pages'] + 2)
        ->and($cache->version(SiteCache::ROUTES))->toBe($was['routes'])
        ->and($cache->version(SiteCache::MENUS))->toBe($was['menus'])
        ->and($cache->version(SiteCache::SITEMAP))->toBe($was['sitemap']);

    // An entry going live changes addresses, menus and the sitemap alike.
    $was = $versions();
    cachedPost('Hello');
    foreach (SiteCache::AREAS as $area) {
        expect($cache->version($area))->toBeGreaterThan($was[$area]);
    }
    $was = $versions();
    $cache->changed('everything');
    foreach (SiteCache::AREAS as $area) {
        expect($cache->version($area))->toBe($was[$area] + 1);
    }
    expect($cache->key('x', SiteCache::MENUS))->toContain(':menus:'.$cache->version(SiteCache::MENUS).':x')
        ->and($cache->key('x'))->toContain(':pages:');
    $menu->delete();
});

it('lets no comment touch the cache, unless an approved one shows on a kept page', function () {
    $this->blog->update(['comments' => true]);
    $hello = cachedPost('Hello');
    $cache = app(SiteCache::class);
    $changes = [];
    Event::listen(ContentChanged::class, function (ContentChanged $e) use (&$changes) {
        $changes[] = $e->what;
    });
    $post = fn () => app(PostComment::class)->handle($hello->entry, ['body' => 'Nice', 'author_name' => 'Rina', 'author_email' => 'rina@example.com'], null, 'en', '10.0.0.1');

    // Comments are read from the database on every render: nothing kept shows them.
    $pages = $cache->version(SiteCache::PAGES);
    $comment = $post();
    app(SetCommentStatus::class)->handle($comment, Comment::APPROVED);
    app(SetCommentStatus::class)->handle($comment, Comment::SPAM);
    expect($changes)->toBe([])->and($cache->version(SiteCache::PAGES))->toBe($pages);

    // But a kept page: only approved comments, and only the pages area.
    config(['sunrice-cms.cache.pages' => true]);
    $versions = fn (): array => [$cache->version(SiteCache::PAGES), $cache->version(SiteCache::ROUTES), $cache->version(SiteCache::MENUS), $cache->version(SiteCache::SITEMAP)];
    [$pages, $routes, $menus, $sitemap] = $versions();
    $pending = $post();
    expect($pending->status)->toBe(Comment::PENDING)->and($changes)->toBe([])->and($versions())->toBe([$pages, $routes, $menus, $sitemap]);
    app(SetCommentStatus::class)->handle($pending, Comment::APPROVED);
    expect($changes)->toBe(['comment '.$pending->id])->and($versions())->toBe([$pages + 1, $routes, $menus, $sitemap]);
    app(SetCommentStatus::class)->handle($pending, Comment::PENDING);
    expect($changes)->toHaveCount(2)->and($versions())->toBe([$pages + 2, $routes, $menus, $sitemap]);
    app(SetCommentStatus::class)->handle($pending, Comment::SPAM);
    expect($changes)->toHaveCount(2)->and($versions())->toBe([$pages + 2, $routes, $menus, $sitemap]);
});

it('keeps one page whatever other query parameters say', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true, 'sunrice-cms.cache.query' => ['sort']]);
    app(CacheSettings::class)->fill(['otherQuery' => CacheSettings::IGNORE])->save();
    foreach (range(1, 3) as $i) {
        cachedPost("Post {$i}");
    }
    $this->blog->update(['per_page' => 2]);

    get('/blog/post-1')->assertOk()->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/post-1?utm_source=newsletter&x='.uniqid())->assertHeader('X-Cache-Status', 'HIT');
    get('/blog')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog?fbclid=1')->assertHeader('X-Cache-Status', 'HIT');
    get('/blog?page=2')->assertHeader('X-Cache-Status', 'MISS')->assertSee('paged-2');
    get('/blog?page=2&utm_medium=mail')->assertHeader('X-Cache-Status', 'HIT');
    // The plain listing doesn't read ?sort=: rendered, not kept.
    get('/blog?sort=title')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog?sort=title')->assertHeader('X-Cache-Status', 'MISS');
    expect(PageCacheHasher::query())->toBe(['page', 'sort']);
});

it('keeps no copies for pages past the last, for query parameters a page didn\'t read, nor for values of any number', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true, 'sunrice-cms.cache.query' => ['sort', 'tab', 'price_min', 'price_max', 'listings']]);
    Blueprint::query()->where('handle', 'post')->firstOrFail()->update(['fields' => [['handle' => 'intro', 'type' => 'text'], ['handle' => 'price', 'type' => 'number']]]);
    foreach (range(1, 3) as $i) {
        app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => "Post {$i}", 'data' => ['price' => $i * 10]]));
    }
    $this->blog->update(['per_page' => 2]);
    $views = sys_get_temp_dir().'/sunrice-read-'.uniqid();
    mkdir("{$views}/cms/collections/blog", recursive: true);
    // The listing: an entry filter with a range offering steps, a sort, and a tab of the template's own.
    file_put_contents("{$views}/cms/collections/blog/index.blade.php", '@php(app(\Sunrice\Cms\Site\PageCache::class)->read("tab"))'
        .'<x-sunrice-cms::entry-filter collection="blog" :per-page="2" :filters="[\'price\' => [\'type\' => \'range\', \'options\' => [10, 20]]]" :sorts="[\'new\' => \'latest\', \'cheap\' => \'price\']">'
        .'@foreach ($component->entries as $e){{ $e->title }};@endforeach</x-sunrice-cms::entry-filter>');
    // A post lists other posts under a page name of its own.
    file_put_contents("{$views}/cms/collections/blog/show.blade.php", '{{ $title }}|@foreach (Cms::entries("blog")->paginate(1, "listings") as $e){{ $e->title }};@endforeach');
    View::addLocation($views);
    $twice = function (string $url, string $second): void {
        get($url)->assertOk()->assertHeader('X-Cache-Status', 'MISS');
        get($url)->assertOk()->assertHeader('X-Cache-Status', $second);
    };

    // Past the last page: a 404 (never kept), with an entry filter as with the CMS's own $entries.
    get('/blog?page=2')->assertOk();
    get('/blog?page=123456')->assertNotFound();
    get('/blog?page=123456')->assertNotFound();
    // A step the filter offers, a sort it has and the template's own tab are kept; any other number isn't.
    $twice('/blog?price_min=10', 'HIT');
    $twice('/blog?price_max=20&sort=cheap&tab=a', 'HIT');
    $twice('/blog?price_min=1234567', 'MISS');
    $twice('/blog?sort=zzz', 'MISS');
    // A filter's parameters on a page without that filter: not kept.
    $twice('/blog/post-1?price_min=10', 'MISS');
    $twice('/blog/post-1?sort=cheap', 'MISS');
    $twice('/blog/post-1?tab=a', 'MISS');
    get('/blog/post-1?utm_source=x')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/post-1')->assertHeader('X-Cache-Status', 'HIT');
    // Another page name: read where it paginates; past its last page the page shows, but isn't kept.
    $twice('/blog/post-1?listings=2', 'HIT');
    $twice('/blog/post-1?listings=99', 'MISS');
    get('/blog/post-1?listings=99')->assertSee('Post 1|');
    // An empty one (a form's blank choice) is the page without it.
    get('/blog')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog?sort=')->assertHeader('X-Cache-Status', 'HIT');
});

it('never keeps a filtered page under the address without its filter, when the filter\'s parameters aren\'t listed', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);
    Blueprint::query()->where('handle', 'post')->firstOrFail()->update(['fields' => [['handle' => 'intro', 'type' => 'text'], ['handle' => 'price', 'type' => 'number']]]);
    foreach (range(1, 3) as $i) {
        app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => "Post {$i}", 'data' => ['price' => $i * 10]]));
    }
    $views = sys_get_temp_dir().'/sunrice-unlisted-'.uniqid();
    mkdir("{$views}/cms/collections/blog", recursive: true);
    file_put_contents("{$views}/cms/collections/blog/index.blade.php", '<x-sunrice-cms::entry-filter collection="blog" :filters="[\'price\' => [\'type\' => \'range\', \'options\' => [10, 20, 30]]]">'
        .'@foreach ($component->entries as $e){{ $e->title }};@endforeach</x-sunrice-cms::entry-filter>');
    View::addLocation($views);

    // By default other parameters skip the cache: the filter works, and the plain listing stays whole.
    expect(app(CacheSettings::class)->skipsOtherQuery())->toBeTrue();
    get('/blog?price_min=30')->assertSee('Post 3;')->assertDontSee('Post 1;')->assertHeaderMissing('X-Cache-Status');
    get('/blog')->assertSee('Post 1;')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog?price_min=30')->assertDontSee('Post 1;');

    // Ignoring other parameters, the filtered page still isn't kept as the plain listing.
    app(SiteCache::class)->changed('test');
    app(ResponseCache::class)->clear();
    app(CacheSettings::class)->fill(['otherQuery' => CacheSettings::IGNORE])->save();
    get('/blog?price_min=30')->assertSee('Post 3;')->assertDontSee('Post 1;');
    get('/blog')->assertSee('Post 1;')->assertSee('Post 2;');
});

it('keeps no copies for page numbers a page doesn\'t read, nor for made-up ones', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);
    foreach (range(1, 3) as $i) {
        cachedPost("Post {$i}");
    }
    $this->blog->update(['per_page' => 2]);

    // An entry page doesn't paginate: ?page=7771 is rendered, never kept.
    get('/blog/post-1?page=7771')->assertOk()->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/post-1?page=7771')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/post-1')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/post-1?page=1')->assertHeader('X-Cache-Status', 'HIT'); // page 1 is the page
    // Not written plainly: neither served from the cache nor kept.
    get('/blog?page=abc')->assertOk()->assertHeaderMissing('X-Cache-Status');
    get('/blog?page=02')->assertHeaderMissing('X-Cache-Status');
    get('/blog?page[]=2')->assertHeaderMissing('X-Cache-Status');
    get('/blog')->assertHeader('X-Cache-Status', 'MISS');
    // A listing paginates: page 2 is kept.
    get('/blog?page=2')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog?page=2')->assertHeader('X-Cache-Status', 'HIT');
});

it('never keeps nor serves kept pages for a search or a comment reply', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);
    $this->blog->update(['comments' => true]);
    cachedPost('Hello');
    $comment = app(PostComment::class)->handle(EntryTranslation::query()->firstOrFail()->entry, ['body' => 'Hi', 'author_name' => 'Sophie', 'author_email' => 's@example.com'], null, 'en', '127.0.0.1');
    app(SetCommentStatus::class)->handle($comment, Comment::APPROVED);

    // A reply link opened first: not kept, so the plain page doesn't reply to anyone.
    get('/blog/hello?reply_to='.$comment->id)->assertOk()->assertHeaderMissing('X-Cache-Status')->assertSee('name="parent_id" value="'.$comment->id.'"', false);
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS')->assertDontSee('name="parent_id" value="'.$comment->id.'"', false);
    // And once the page is kept, a reply link still shows the reply form.
    get('/blog/hello?reply_to='.$comment->id)->assertHeaderMissing('X-Cache-Status')->assertSee('name="parent_id" value="'.$comment->id.'"', false);
    get('/blog/hello?q=villa')->assertOk()->assertHeaderMissing('X-Cache-Status');
    expect(PageCache::UNKEPT_QUERY)->toBe(['q', 'reply_to']);
});

it('starts new kept pages when the front end is built again', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);
    cachedPost('Hello');
    $manifest = public_path('build/manifest.json');
    @mkdir(dirname($manifest), 0777, true);
    file_put_contents($manifest, '{"app.css":{"file":"assets/app-A.css"}}');

    try {
        get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
        get('/blog/hello')->assertHeader('X-Cache-Status', 'HIT');
        file_put_contents($manifest, '{"app.css":{"file":"assets/app-B.css"}}');
        get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
    } finally {
        @unlink($manifest);
        @rmdir(dirname($manifest));
    }
});

it('refuses to keep pages without the CSRF token replacer', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.replacers' => []]);

    expect(fn () => app()->register(CmsServiceProvider::class, force: true))->toThrow(PanelMisconfigured::class, 'CsrfTokenReplacer');
});

it('reads global sets, fieldsets and forms from the site cache until they change', function () {
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'phone', 'type' => 'text'], ['handle' => 'more', 'type' => 'fieldset', 'config' => ['fieldset' => 'extra']]]]);
    Blueprint::query()->create(['handle' => 'newsletter', 'title' => 'Newsletter', 'fields' => [['handle' => 'email', 'type' => 'text']]]);
    Fieldset::query()->create(['handle' => 'extra', 'title' => 'Extra', 'fields' => [['handle' => 'fax', 'type' => 'text']]]);
    $contact = GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    app(SaveGlobals::class)->handle($contact, ['phone' => '+62 1', 'fax' => '+62 2']);
    Form::query()->create(['handle' => 'newsletter', 'title' => 'Newsletter', 'blueprint' => 'newsletter']);
    $views = sys_get_temp_dir().'/sunrice-globals-'.uniqid();
    mkdir("{$views}/cms/collections/blog", recursive: true);
    file_put_contents("{$views}/cms/collections/blog/show.blade.php", '{{ $title }}|{{ Cms::global("contact")["phone"] }}|{{ Cms::global("contact")["fax"] }}|<x-sunrice-cms::form handle="newsletter" />');
    View::addLocation($views);
    cachedPost('Hello');

    get('/blog/hello')->assertOk()->assertSee('+62 1|+62 2|', false)->assertSee('name="data[email]"', false);
    app()->forgetScopedInstances();
    [$response, $warm] = withQueries(fn () => get('/blog/hello'));
    $response->assertSee('+62 1|+62 2|', false)->assertSee('name="data[email]"', false);
    expect($warm)->toBeLessThanOrEqual(5); // as a page without them

    // A change shows at once.
    app(SaveGlobals::class)->handle($contact->fresh(), ['phone' => '+62 9', 'fax' => '+62 2']);
    app()->forgetScopedInstances();
    get('/blog/hello')->assertSee('+62 9|', false);
});

it('runs a listing\'s and a term page\'s entries query only when the template uses $entries', function () {
    $tags = Taxonomy::query()->create(['handle' => 'places', 'title' => 'Places', 'route' => '/places/{slug}', 'hierarchical' => true]);
    $bali = app(SaveTerm::class)->handle($tags, ['title' => 'Bali']);
    $canggu = app(SaveTerm::class)->handle($tags, ['title' => 'Canggu', 'parent_id' => $bali->id]);
    $tags->collections()->attach($this->blog);
    foreach (range(1, 3) as $i) {
        app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => "Post {$i}", 'terms' => ['places' => $i === 1 ? $bali->id : $canggu->id]]));
    }
    $views = sys_get_temp_dir().'/sunrice-lazy-'.uniqid();
    foreach (['collections/blog', 'taxonomies/places'] as $folder) {
        mkdir("{$views}/cms/{$folder}", recursive: true);
    }
    file_put_contents("{$views}/cms/collections/blog/index.blade.php", 'Own list @if (request("list")) @foreach ($entries as $e){{ $e->title }};@endforeach @endif');
    file_put_contents("{$views}/cms/taxonomies/places/show.blade.php", '@foreach ($entries as $e){{ $e->title }};@endforeach');
    View::addLocation($views);
    get('/blog')->assertOk();
    $own = withQueries(fn () => get('/blog')->assertOk()->assertSee('Own list'))[1];
    $listed = withQueries(fn () => get('/blog?list=1')->assertOk()->assertSee('Post 3;'))[1];

    expect($own)->toBeLessThan($listed);
    // A term page in a hierarchy lists the entries of the terms under it too.
    get('/places/bali')->assertOk()->assertSee('Post 1;')->assertSee('Post 2;')->assertSee('Post 3;');
    get('/places/canggu')->assertOk()->assertDontSee('Post 1;')->assertSee('Post 2;');
    // Past the last page: still a 404.
    get('/blog?list=1&page=9')->assertNotFound();
    get('/places/bali?page=9')->assertNotFound();
});

it('gives each visitor of a kept page their own honeypot fields, so a too-fast send is still refused', function () {
    Mail::fake();
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true, 'honeypot.enabled' => true, 'honeypot.amount_of_seconds' => 3]);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text']]]);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    View::addNamespace('cached', __DIR__.'/Fixtures/views');
    // A package's views, under the site's template prefixes.
    config(['sunrice-cms.site.template_prefixes' => ['cms.', 'cached::']]);
    $this->blog->update(['template' => 'cached::cms.with-form']);
    cachedPost('Hello');
    $fields = function (TestResponse $response): array {
        preg_match('/name="(my_name_[A-Za-z0-9]{16})"/', (string) $response->getContent(), $name);
        preg_match('/name="valid_from"\s+type="text"\s+value="([^"]+)"/', (string) $response->getContent(), $validFrom);

        return [$name[1] ?? null, $validFrom[1] ?? null];
    };

    $first = $fields(get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS'));
    travelTo(now()->addMinutes(10));
    $kept = get('/blog/hello')->assertHeader('X-Cache-Status', 'HIT');
    $second = $fields($kept);
    expect($second[0])->not->toBeNull()->not->toBe($first[0])
        ->and($second[1])->not->toBeNull()->not->toBe($first[1])
        ->and($kept->getContent())->not->toContain('__sunrice_honeypot');

    // Sent right away from the kept page: too fast.
    $send = fn (array $fields) => post('/forms/contact', ['_token' => csrfIn($kept), 'data' => ['name' => 'Bot'], $fields[0] => '', 'valid_from' => $fields[1]]);
    $send($second)->assertOk()->assertContent('');
    expect(Submission::query()->count())->toBe(0);
    travelTo(now()->addSeconds(5));
    $send($second)->assertRedirect();
    expect(Submission::query()->count())->toBe(1);
});

it('clears the site\'s caches and the kept pages\' own store with php artisan cache:clear, whatever store they\'re in', function () {
    config([
        'cache.stores.site' => ['driver' => 'array'],
        'cache.stores.pages' => ['driver' => 'array'],
        'sunrice-cms.cache.store' => 'site',
        'responsecache.cache.store' => 'pages',
        'responsecache.cache.tag' => '',
    ]);
    $cache = app(SiteCache::class);
    $cache->changed('start');
    $was = array_map(fn (string $area) => $cache->version($area), SiteCache::AREAS);
    cache()->store('pages')->put('kept-page', 'html');

    artisan('cache:clear')->assertSuccessful();

    foreach (SiteCache::AREAS as $i => $area) {
        expect($cache->version($area))->toBe($was[$i] + 1);
    }
    expect(cache()->store('pages')->get('kept-page'))->toBeNull();

    // Clearing only some tags leaves them.
    $was = $cache->version(SiteCache::PAGES);
    cache()->store('pages')->put('kept-page', 'html');
    artisan('cache:clear', ['--tags' => 'other'])->assertSuccessful();
    expect($cache->version(SiteCache::PAGES))->toBe($was)
        ->and(cache()->store('pages')->get('kept-page'))->toBe('html');

    // Before the site's tables exist (a deploy that clears, then migrates): the command still succeeds.
    app()->forgetInstance(CacheSettings::class);
    Schema::drop('sunrice_settings');
    Schema::drop('sunrice_activity');
    artisan('cache:clear')->assertSuccessful();
});

it('reads a value from the cache store once per request, and the next version after a change', function () {
    config(['cache.default' => 'database', 'cache.stores.database.connection' => null]);
    app('cache')->forgetDriver('database');
    $cache = app(SiteCache::class);
    $made = 0;
    $make = function () use (&$made) {
        return ++$made;
    };
    $cache->remember('x', $make); // made and kept

    app()->forgetScopedInstances(); // the next request
    $cache = app(SiteCache::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $values = [$cache->remember('x', $make), $cache->remember('x', $make), $cache->remember('x', $make)];
    $reads = array_column(array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with(str_replace(['"', '`'], '', $query['query']), 'select * from cache ')), 'bindings');
    DB::disableQueryLog();
    expect($values)->toBe([1, 1, 1])->and($made)->toBe(1)
        ->and(array_values(array_filter($reads, fn (array $keys): bool => in_array(config('cache.prefix').$cache->key('x'), $keys, true))))->toHaveCount(1);

    $cache->changed('test');
    expect($cache->remember('x', $make))->toBe(2)->and($made)->toBe(2);
});
