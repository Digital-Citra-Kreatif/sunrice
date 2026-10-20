<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Query\EntryQuery;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Site\PageCache;
use Sunrice\Cms\Site\PageCacheProfile;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'route' => '/{slug}']);
    foreach ([[$this->blog, 'Hello'], [$this->pages, 'About']] as [$collection, $title]) {
        app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection, ['title' => $title]));
    }
    View::addNamespace('hooks', __DIR__.'/Fixtures/views');
});

it('lets the app choose the template of any page', function () {
    $seen = [];
    Content::resolveTemplateUsing(function (Page $page) use (&$seen): ?string {
        $seen[] = $page->type.':'.$page->view;

        return $page->collection?->handle === 'blog' && $page->type === Page::ENTRY ? 'hooks::cms.special' : null;
    });
    // A later hook sees the choice before it, and null keeps it.
    $later = [];
    Content::resolveTemplateUsing(function (Page $page) use (&$later): ?string {
        $later[] = $page->view;

        return null;
    });

    get('/blog/hello')->assertOk()->assertSee('A special page: Hello');
    expect(Content::page()?->view)->toBe('hooks::cms.special')
        ->and(Content::bodyClass())->toContain('template-hooks-cms-special');
    get('/about')->assertOk()->assertDontSee('A special page');
    get('/blog')->assertOk();
    get('/search?q=x')->assertOk();

    expect($seen)->toBe(['entry:sunrice-cms::entry', 'entry:sunrice-cms::entry', 'listing:sunrice-cms::listing', 'search:sunrice-cms::search'])
        ->and($later)->toBe(['hooks::cms.special', 'sunrice-cms::entry', 'sunrice-cms::listing', 'sunrice-cms::search']);
});

it('lets the app add body classes', function () {
    Content::bodyClassUsing(fn (Page $page): array => $page->type === Page::ENTRY ? ['Is Article', 'from-'.$page->collection?->handle] : []);

    get('/blog/hello')->assertOk()->assertSee('is-article from-blog', false);
    expect(Content::bodyClass('dark'))->toEndWith('is-article from-blog dark');
    get('/blog')->assertOk()->assertDontSee('is-article', false);
});

it('prints the body\'s class attribute with @bodyClass', function () {
    expect(Blade::render('<body @bodyClass>'))->toBe('<body class="lang-en">')
        ->and(Blade::render('<body @bodyClass(\'dark <x>\')>'))->toBe('<body class="lang-en dark x">')
        ->and(Blade::render('<body @bodyClass([\'a\', \'b\'])>'))->toBe('<body class="lang-en a b">');
});

it('has helper functions for templates', function () {
    expect(function_exists('cms_entries') && function_exists('cms_menu') && function_exists('cms_global')
        && function_exists('cms_locale_urls') && function_exists('cms_body_class') && function_exists('cms_page'))->toBeTrue()
        ->and(cms_entries('blog'))->toBeInstanceOf(EntryQuery::class)
        ->and(cms_entries('blog')->get()->map->title->all())->toBe(['Hello'])
        ->and(cms_menu('nope'))->toBe([])
        ->and(cms_global('nope'))->toBe([])
        ->and(cms_page())->toBeNull()
        ->and(cms_locale_urls())->toBe([])
        ->and(cms_body_class('x'))->toBe('lang-en x');

    get('/about')->assertOk();
    expect(cms_page()?->entry?->title)->toBe('About')
        ->and(cms_locale_urls())->toBe(['en' => url('/about')])
        ->and(cms_body_class())->toBe(Content::bodyClass());
});

it('keeps pages for as long as the app says, the shortest answer winning', function () {
    config(['sunrice-cms.cache.pages' => true, 'sunrice-cms.cache.ttl' => 3600, 'responsecache.debug.enabled' => true]);
    $cache = app(PageCache::class);
    $request = Request::create('/blog/hello');
    $profile = app(PageCacheProfile::class);

    expect($cache->lifetime($request, null))->toBeNull()
        ->and($profile->cacheLifetimeInSeconds($request))->toBe(3600);

    $cache->lifetimeUsing(fn (Request $request, ?Page $page): ?int => $page?->collection?->handle === 'blog' ? 600 : null)
        ->lifetimeUsing(fn (Request $request, ?Page $page): ?int => $page?->type === Page::ENTRY ? 900 : null)
        ->lifetimeUsing(fn (Request $request, ?Page $page): ?int => $page?->entry?->title === 'About' ? 0 : null);

    get('/blog/hello')->assertOk();
    expect($cache->lifetime(request(), Page::current()))->toBe(600)
        ->and($profile->cacheLifetimeInSeconds(request()))->toBe(600);
    get('/blog/hello')->assertHeader('X-Cache-Status', 'HIT');

    // 0: not kept.
    get('/about')->assertHeader('X-Cache-Status', 'MISS');
    get('/about')->assertHeader('X-Cache-Status', 'MISS');
});
