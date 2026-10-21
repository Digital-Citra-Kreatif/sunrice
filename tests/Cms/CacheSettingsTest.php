<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Settings\CacheSettings;
use Sunrice\Cms\Site\PageCache;
use Sunrice\Cms\Site\PageCacheHasher;
use Sunrice\Cms\Site\PageCacheProfile;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['responsecache.debug.enabled' => true]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'post', 'route' => '/{slug}']);
});

function cachePage(Collection $collection, string $title): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection, ['title' => $title]));
}

/** Save Settings → Cache as the panel does (which starts a new content version). */
function saveCache(array $values): void
{
    app(CacheSettings::class)->fill($values)->save();
}

/** @return Illuminate\Support\Collection<string, array<string, mixed>> the form's fields by name */
function cacheFields(): Illuminate\Support\Collection
{
    return collect(CacheSettings::form(new Form)->toSections('edit'))->pluck('fields')->flatten(1)->keyBy('name');
}

it('adds Settings → Cache, guarded by its own permission, which administrators have', function () {
    expect(app(Panel::class)->getSettings())->toHaveKey('cache')
        ->and(app(Panel::class)->getPermissions())->toHaveKey('cms.settings.cache');

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    get('/admin/settings/cache')->assertForbidden();
    put('/admin/settings/cache', ['pageCache' => 'on'])->assertForbidden();
    post('/admin/cms/cache/clear')->assertForbidden();
    post('/admin/cms/cache/clear-pages')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.cache'));
    get('/admin/settings/cache')->assertOk()->assertSee('Clear page cache')->assertSee('Clear all site caches');
    $version = app(SiteCache::class)->version(SiteCache::ROUTES);
    put('/admin/settings/cache', [
        'pageCache' => 'on', 'pageMinutes' => 30, 'exclude' => "/contact\n/account/*", 'collections' => ['blog'],
        'query' => "tab\nsort", 'otherQuery' => CacheSettings::SKIP, 'ignore' => "utm_*\nfbclid", 'siteMinutes' => 120, 'preloadSchedule' => CacheSettings::NEVER,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $settings = new CacheSettings;
    expect($settings->pageCache)->toBe('on')->and($settings->pageMinutes)->toBe(30)
        ->and($settings->excludedPaths())->toBe(['/contact', '/account/*'])
        ->and($settings->collections)->toBe(['blog'])
        ->and($settings->queryParameters())->toBe(['tab', 'sort'])
        ->and($settings->ignoredParameters())->toBe(['utm_*', 'fbclid'])
        ->and($settings->siteMinutes)->toBe(120);
    // Saved: everything kept before is never read again.
    expect(app(SiteCache::class)->version(SiteCache::ROUTES))->toBe($version + 1)
        ->and(Activity::query()->where('subject_type', 'settings')->where('subject_id', 'cache')->exists())->toBeTrue();
});

it('refuses addresses and parameter names it can\'t use', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.cache'));

    put('/admin/settings/cache', ['pageCache' => 'on', 'exclude' => 'contact'])->assertSessionHasErrors('exclude');
    put('/admin/settings/cache', ['pageCache' => 'on', 'exclude' => '/search?q=*'])->assertSessionHasErrors('exclude');
    put('/admin/settings/cache', ['pageCache' => 'on', 'exclude' => 'https://example.com/a'])->assertSessionHasErrors('exclude');
    put('/admin/settings/cache', ['pageCache' => 'on', 'query' => 'utm_*'])->assertSessionHasErrors('query');
    put('/admin/settings/cache', ['pageCache' => 'on', 'query' => 'q'])->assertSessionHasErrors('query');
    put('/admin/settings/cache', ['pageCache' => 'on', 'ignore' => 'a b'])->assertSessionHasErrors('ignore');
    put('/admin/settings/cache', ['pageCache' => 'on', 'collections' => ['nope']])->assertSessionHasErrors('collections.0');
    put('/admin/settings/cache', ['pageCache' => 'sometimes'])->assertSessionHasErrors('pageCache');
    put('/admin/settings/cache', ['pageCache' => 'on', 'pageMinutes' => 0])->assertSessionHasErrors('pageMinutes');
    put('/admin/settings/cache', ['pageCache' => 'on', 'otherQuery' => 'ignore', 'preloadSchedule' => 'off', 'exclude' => "/a-b_c.html\n*/print\n/search*", 'ignore' => "utm_*\nfilter[x]"])->assertSessionHasNoErrors();
});

it('turns whole pages on and off over the config, and keeps them as long as it says', function () {
    cachePage($this->blog, 'Hello');

    // The config's default (off): nothing kept.
    get('/blog/hello')->assertOk()->assertHeaderMissing('X-Cache-Status');
    saveCache(['pageCache' => CacheSettings::ON]);
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/hello')->assertHeader('X-Cache-Status', 'HIT');
    expect(PageCache::enabled())->toBeTrue();

    // Off here wins over the config.
    config(['sunrice-cms.cache.pages' => true]);
    saveCache(['pageCache' => CacheSettings::OFF]);
    get('/blog/hello')->assertHeaderMissing('X-Cache-Status');
    // As configured: the config again.
    saveCache(['pageCache' => CacheSettings::CONFIGURED]);
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');

    // How long: the config's ttl, or the minutes set (never past responsecache's own lifetime).
    $lifetime = fn (): int => app(PageCacheProfile::class)->cacheLifetimeInSeconds(Request::create('/blog/hello'));
    expect($lifetime())->toBe(3600);
    saveCache(['pageMinutes' => 10]);
    expect($lifetime())->toBe(600);
    saveCache(['pageMinutes' => 10080]);
    config(['responsecache.cache.lifetime_in_seconds' => 86400]);
    expect($lifetime())->toBe(86400);
});

it('sets how long the site\'s data is kept, over the config', function () {
    expect(SiteCache::ttl())->toBe(3600);
    saveCache(['siteMinutes' => 5]);
    expect(SiteCache::ttl())->toBe(300);
    config(['sunrice-cms.cache.ttl' => 60]);
    expect(SiteCache::ttl())->toBe(300);
    saveCache(['siteMinutes' => null]);
    expect(SiteCache::ttl())->toBe(60);
});

it('never keeps pages whose address matches a pattern, nor a collection\'s pages', function () {
    config(['sunrice-cms.cache.pages' => true, 'sunrice-cms.locales' => ['en', 'id']]);
    foreach (['Contact', 'Account', 'Search results', 'About'] as $title) {
        cachePage($this->pages, $title);
    }
    cachePage($this->blog, 'Hello');
    saveCache(['exclude' => "/contact\n/account*\n/search-*"]);
    $twice = function (string $url, ?string $second): void {
        get($url)->assertOk();
        $second === null ? get($url)->assertHeaderMissing('X-Cache-Status') : get($url)->assertHeader('X-Cache-Status', $second);
    };

    $twice('/contact', null);
    $twice('/account', null);
    $twice('/search-results', null);
    $twice('/about', 'HIT');
    // In another language too (its prefix doesn't count).
    $twice('/id/contact', null);
    $twice('/blog/hello', 'HIT');
    $twice('/blog', 'HIT');

    // A collection: its pages and its listing are rendered, never kept.
    saveCache(['collections' => ['blog']]);
    $twice('/blog/hello', 'MISS');
    $twice('/blog', 'MISS');
    $twice('/about', 'HIT');
    expect(app(PageCache::class)->excluded(Request::create('/account/orders/12')))->toBeTrue()
        ->and(app(PageCache::class)->excluded(Request::create('/CONTACT/')))->toBeTrue()
        ->and(app(PageCache::class)->excluded(Request::create('/about')))->toBeFalse();
});

it('ignores other query parameters, or skips the cache for them, but never for the ignored ones', function () {
    config(['sunrice-cms.cache.pages' => true, 'sunrice-cms.cache.query' => ['sort']]);
    cachePage($this->blog, 'Hello');
    $views = sys_get_temp_dir().'/sunrice-cache-settings-'.uniqid();
    mkdir("{$views}/cms", recursive: true);
    file_put_contents("{$views}/cms/blog.blade.php", '@php(app(\Sunrice\Cms\Site\PageCache::class)->read("tab"))tab={{ request("tab") }}');
    View::addLocation($views);

    // Ignored: the same kept page.
    saveCache(['otherQuery' => CacheSettings::IGNORE]);
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/hello?utm_source=mail&x='.uniqid())->assertHeader('X-Cache-Status', 'HIT');

    // Parameters that make a page of their own: the config's and these.
    saveCache(['query' => 'tab']);
    expect(PageCacheHasher::query())->toBe(['page', 'sort', 'tab']);
    get('/blog/hello?tab=a')->assertHeader('X-Cache-Status', 'MISS')->assertSee('tab=a');
    get('/blog/hello?tab=a&utm_medium=x')->assertHeader('X-Cache-Status', 'HIT')->assertSee('tab=a');
    get('/blog/hello?tab=b')->assertHeader('X-Cache-Status', 'MISS')->assertSee('tab=b');

    // Skip the cache for any other: neither served from the cache nor kept, except the ignored ones.
    saveCache(['otherQuery' => CacheSettings::SKIP, 'ignore' => "utm_*\nfbclid"]);
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/hello?x=1')->assertOk()->assertHeaderMissing('X-Cache-Status');
    get('/blog/hello?x=1')->assertHeaderMissing('X-Cache-Status');
    get('/blog/hello?utm_source=a&utm_campaign=b&fbclid=c')->assertHeader('X-Cache-Status', 'HIT');
    get('/blog/hello?tab=a&fbclid=c')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/hello?tab=a')->assertHeader('X-Cache-Status', 'HIT');
    get('/blog/hello?gclid=1')->assertHeaderMissing('X-Cache-Status');
});

it('disables the page cache\'s settings and says why when responsecache is off', function () {
    saveCache(['pageCache' => CacheSettings::ON]);
    cachePage($this->blog, 'Hello');
    expect(cacheFields()['pageCache']['disabled'])->toBeFalse();

    config(['responsecache.enabled' => false]);
    expect(PageCache::enabled())->toBeFalse()
        ->and(PageCache::unavailable())->toContain('RESPONSE_CACHE_ENABLED')
        ->and(cacheFields()->only(['pageCache', 'pageMinutes', 'exclude', 'collections', 'query', 'otherQuery', 'ignore'])->pluck('disabled')->unique()->all())->toBe([true])
        ->and(cacheFields()['siteMinutes']['disabled'])->toBeFalse()
        ->and(cacheFields()['pageCache']['developerHelp']['text'])->toContain('RESPONSE_CACHE_ENABLED');
    actingAs($user = adminUser(Panel::ACCESS, 'cms.settings.cache'));
    $card = CacheSettings::cards($user)[0]->toArray();
    expect(collect($card['blocks'])->where('type', 'notice')->pluck('detail')->implode(' '))->toContain('RESPONSE_CACHE_ENABLED')
        ->and(collect($card['buttons'])->pluck('label')->all())->toBe(['Clear all site caches']);
    // Disabled fields aren't saved.
    put('/admin/settings/cache', ['pageCache' => CacheSettings::OFF, 'siteMinutes' => 9])->assertSessionHasNoErrors();
    expect((new CacheSettings)->pageCache)->toBe(CacheSettings::ON)->and((new CacheSettings)->siteMinutes)->toBe(9);
    auth()->logout();
    get('/blog/hello')->assertOk()->assertHeaderMissing('X-Cache-Status');

    // Without the CSRF token replacer, turned on here: off, and it says so.
    config(['responsecache.enabled' => true, 'responsecache.replacers' => []]);
    expect(PageCache::enabled())->toBeFalse()->and(PageCache::unavailable())->toContain('CsrfTokenReplacer');
});

it('clears the page cache or all the site\'s caches, logged, and shows who did last', function () {
    config(['sunrice-cms.cache.pages' => true]);
    cachePage($this->blog, 'Hello');
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/hello')->assertHeader('X-Cache-Status', 'HIT');
    Cache::put('app:own', 'kept');
    $versions = fn (): array => array_map(fn (string $area): int => app(SiteCache::class)->version($area), SiteCache::AREAS);
    [$pages, $routes, $menus, $sitemap] = $versions();

    actingAs($user = adminUser(Panel::ACCESS, 'cms.settings.cache'));
    post('/admin/cms/cache/clear-pages')->assertRedirect()->assertSessionHas('success', 'The page cache is cleared: visitors get freshly rendered pages.');
    expect($versions())->toBe([$pages + 1, $routes, $menus, $sitemap])
        ->and(Cache::get('app:own'))->toBe('kept'); // the app's own cache stays
    $cleared = Activity::query()->where('subject_type', 'cache')->latest('id')->firstOrFail();
    expect($cleared->action)->toBe('cleared')->and($cleared->subject_id)->toBe('pages')->and($cleared->sentence())->toBe('Rina cleared the page cache');

    auth()->logout();
    session()->flush(); // the flashed message
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
    get('/blog/hello')->assertHeader('X-Cache-Status', 'HIT');

    actingAs($user);
    post('/admin/cms/cache/clear')->assertRedirect()->assertSessionHas('success', 'All the site\'s caches are cleared: pages, addresses, menus and the sitemap are read afresh.');
    expect($versions())->toBe([$pages + 2, $routes + 1, $menus + 1, $sitemap + 1])
        ->and(Activity::query()->where('subject_type', 'cache')->latest('id')->firstOrFail()->subject_id)->toBe('all');
    auth()->logout();
    session()->flush();
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');

    $rows = collect(collect(CacheSettings::cards($user)[0]->toArray()['blocks'])->firstWhere('type', 'rows')['rows'])->pluck('value', 'label');
    expect($rows['Whole-page cache'])->toBe('On (as configured)')
        ->and($rows['Last cleared by'])->toBe('Rina (all site caches)')
        ->and($rows)->toHaveKey('Last cleared');
});

it('clears pages from responsecache\'s own store when it holds nothing else', function () {
    config(['sunrice-cms.cache.pages' => true, 'cache.stores.pages' => ['driver' => 'array'], 'responsecache.cache.store' => 'pages']);
    cachePage($this->blog, 'Hello');
    get('/blog/hello')->assertHeader('X-Cache-Status', 'MISS');
    Cache::store('pages')->put('other', 'x');

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.cache'));
    post('/admin/cms/cache/clear-pages')->assertRedirect();
    expect(Cache::store('pages')->get('other'))->toBeNull();
});
