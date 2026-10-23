<?php

declare(strict_types=1);

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Submission;
use Sunrice\Cms\Site\StartSessionWhenNeeded;

use function Pest\Laravel\actingAs;

/*
 * Each request here is a new visitor's, as on a real site: sessions in
 * the database (Laravel's default driver), the session store made afresh
 * and only the cookies a test sends.
 */
beforeEach(fn () => lazySessionsSite());

/** The panel with the CMS, a page without a form (/about) and one with (/contact/us); sessions in the database. */
function lazySessionsSite(): void
{
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text']]]);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    View::addNamespace('cached', __DIR__.'/Fixtures/views');
    config(['sunrice-cms.site.template_prefixes' => ['cms.', 'cached::'], 'honeypot.enabled' => false, 'responsecache.debug.enabled' => true]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'post', 'route' => '/{slug}']);
    $contact = Collection::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post', 'route' => '/contact/{slug}', 'template' => 'cached::cms.with-form']);
    test()->about = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($pages, ['title' => 'About', 'data' => ['intro' => 'Live words']]));
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($contact, ['title' => 'Us']));
    config(['session.driver' => 'database']);
}

/** A request by a new visitor (or one with the given cookies): the session store made afresh, as in a new PHP process. */
function newVisit(string $url, array $cookies = [], string $method = 'GET', array $input = []): TestResponse
{
    app('session')->forgetDrivers();
    // Encrypted, as the browser sends them back.
    $cookies = collect($cookies)->map(fn (string $value, string $name) => encrypt(CookieValuePrefix::create($name, app('encrypter')->getKey()).$value, false))->all();

    return test()->call($method, $url, $input, $cookies, [], ['HTTP_REFERER' => url('/contact/us')]);
}

/** @return list<string> */
function cookiesSet(TestResponse $response): array
{
    return array_values(array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies()));
}

it('keeps no session and sets no cookie for visitors on pages without a form, kept or not', function (bool $kept) {
    config(['sunrice-cms.cache.pages' => $kept]);

    foreach (range(1, 3) as $i) {
        $response = newVisit('/about')->assertOk()->assertSee('Live words');
        expect(cookiesSet($response))->toBe([]);
    }
    expect(DB::table('sessions')->count())->toBe(0);
    if ($kept) {
        $response->assertHeader('X-Cache-Status', 'HIT');
    }
    // HEAD too.
    expect(cookiesSet(newVisit('/about', method: 'HEAD')->assertOk()))->toBe([])
        ->and(DB::table('sessions')->count())->toBe(0);
})->with(['rendered' => false, 'kept' => true]);

it('keeps Laravel\'s session for every visitor with site.lazy_sessions off', function () {
    config(['sunrice-cms.site.lazy_sessions' => false]);

    foreach (range(1, 3) as $i) {
        expect(cookiesSet(newVisit('/about')->assertOk()))->toContain(config('session.cookie'), 'XSRF-TOKEN');
    }
    expect(DB::table('sessions')->count())->toBe(3);
});

it('starts a session on a page with a form, and its form can be sent, kept or not', function (bool $kept) {
    config(['sunrice-cms.cache.pages' => $kept]);
    if ($kept) {
        newVisit('/contact/us')->assertHeader('X-Cache-Status', 'MISS');
        DB::table('sessions')->delete();
    }

    $page = newVisit('/contact/us')->assertOk();
    if ($kept) {
        $page->assertHeader('X-Cache-Status', 'HIT');
    }
    expect(cookiesSet($page))->toContain(config('session.cookie'), 'XSRF-TOKEN')
        ->and(DB::table('sessions')->count())->toBe(1)
        ->and(tokenIn($page))->toBeString()
        ->and(unserialize(base64_decode((string) DB::table('sessions')->value('payload')))['_token'] ?? null)->toBe(tokenIn($page));

    // Without the session, the token is refused; with it, the form is sent.
    // (CSRF is checked as in production: Laravel skips it in unit tests.)
    app()->detectEnvironment(fn () => 'local');
    $input = ['_token' => tokenIn($page), 'data' => ['name' => 'Ana']];
    newVisit('/forms/contact', method: 'POST', input: $input)->assertStatus(419);
    newVisit('/forms/contact', [config('session.cookie') => $page->getCookie(config('session.cookie'))?->getValue()], 'POST', $input)->assertRedirect(url('/contact/us').'#form-contact-status');
    expect(Submission::query()->count())->toBe(1);
})->with(['rendered' => false, 'kept' => true]);

it('keeps the session of a visitor who has one', function () {
    $cookie = newVisit('/contact/us')->getCookie(config('session.cookie'))?->getValue();
    expect($cookie)->toBeString();

    $response = newVisit('/about', [config('session.cookie') => $cookie])->assertOk();
    expect(cookiesSet($response))->toContain(config('session.cookie'), 'XSRF-TOKEN')
        ->and($response->getCookie(config('session.cookie'))?->getValue())->toBe($cookie)
        ->and(DB::table('sessions')->count())->toBe(1);
});

it('keeps a session when something is put in it', function () {
    View::addLocation($views = sys_get_temp_dir().'/sunrice-views-'.uniqid());
    mkdir("{$views}/cms/collections/pages", recursive: true);
    file_put_contents("{$views}/cms/collections/pages/about.blade.php", '{{ session()->put(\'seen\', true) }}About');

    expect(cookiesSet(newVisit('/about')->assertOk()))->toContain(config('session.cookie'))
        ->and(DB::table('sessions')->count())->toBe(1);
});

it('keeps signed-in editors\' sessions, with their drafts and banners', function () {
    app(SaveDraft::class)->handle($this->about->refresh(), ['title' => 'About', 'data' => ['intro' => 'Draft words']]);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view-drafts', 'cms.entries.view'));

    $response = newVisit('/about')->assertOk()->assertSee('data-sunrice-draft-banner', false);
    expect(cookiesSet($response))->toContain(config('session.cookie'));
    newVisit('/about?draft')->assertOk()->assertSee('Draft words');
});

it('runs the site\'s routes in their own group, with Laravel\'s StartSession left as it is for the panel', function () {
    newVisit('/about')->assertOk();

    $site = app('router')->getMiddlewareGroups()[StartSessionWhenNeeded::GROUP];
    expect($site)->toContain(StartSessionWhenNeeded::class)->not->toContain(StartSession::class)
        ->and(app('router')->getMiddlewareGroups()['web'])->toContain(StartSession::class);
});

function tokenIn(TestResponse $response): ?string
{
    preg_match('/name="_token" value="([^"]+)"/', (string) $response->getContent(), $m);

    return $m[1] ?? null;
}

it('works with cached routes (route:cache)', function () {
    // Cache the routes as route:cache does, to a file of this test's own (APP_ROUTES_CACHE).
    $routes = app('router')->getRoutes();
    $routes->refreshNameLookups();
    $routes->refreshActionLookups();
    foreach ($routes as $route) {
        $route->prepareForSerialization();
    }
    $file = sys_get_temp_dir().'/sunrice-routes-'.uniqid().'.php';
    file_put_contents($file, "<?php\n\napp('router')->setCompiledRoutes(\n    ".var_export($routes->compile(), true)."\n);\n");
    $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $file;

    try {
        // A new app that boots with them, as in production: the CMS and the panel register no routes.
        $this->reloadApplication();
        expect(app()->routesAreCached())->toBeTrue()
            ->and(app('router')->getRoutes()->getRoutes())->toBe([]);
        require app()->getCachedRoutesPath();
        lazySessionsSite();
        expect(app('router')->getRoutes())->toBeInstanceOf(CompiledRouteCollection::class)
            ->and(app('router')->getRoutes()->match(Request::create('/'))->getName())->toBe('sunrice.cms.home')
            ->and(app('router')->getRoutes()->match(Request::create('/about'))->getName())->toBe('sunrice.cms.page');

        $about = newVisit('/about')->assertOk()->assertSee('Live words');
        expect(cookiesSet($about))->toBe([])
            ->and(DB::table('sessions')->count())->toBe(0);
        $contact = newVisit('/contact/us')->assertOk();
        expect(cookiesSet($contact))->toContain(config('session.cookie'), 'XSRF-TOKEN')
            ->and(DB::table('sessions')->count())->toBe(1);

        // The site's group was made at the first request; the panel keeps Laravel's StartSession.
        $router = app('router');
        $site = $router->gatherRouteMiddleware($router->getRoutes()->getByName('sunrice.cms.page'));
        $panel = $router->gatherRouteMiddleware($router->getRoutes()->getByName('sunrice.admin.dashboard'));
        expect($site)->toContain(StartSessionWhenNeeded::class)->not->toContain(StartSession::class)
            ->and($panel)->toContain(StartSession::class)->not->toContain(StartSessionWhenNeeded::class);
    } finally {
        unset($_ENV['APP_ROUTES_CACHE'], $_SERVER['APP_ROUTES_CACHE']);
        @unlink($file);
    }
});
