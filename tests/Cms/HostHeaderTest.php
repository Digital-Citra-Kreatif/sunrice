<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Settings\LocaleSettings;
use Sunrice\Cms\Site\Site;

/*
 * Whoever sends a request chooses its Host header. Without TrustHosts in
 * front, it must not end up in what the site sends back: redirects stay
 * on the site (host-relative), and the sitemap's addresses are APP_URL's.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id'], 'app.url' => 'https://www.acme.test']);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}', 'listing_route' => '/pages']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($pages, ['title' => 'About']));
});

/** Through the kernel as a browser would ask, with a forged Host. */
function forged(string $uri, array $server = []): TestResponse
{
    return TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create($uri, server: ['HTTP_HOST' => 'evil.example', ...$server])));
}

it('redirects on the site, never to the Host the request named', function () {
    forged('/en/about?x=1')->assertStatus(301)->assertHeader('Location', '/about?x=1');
    forged('/en')->assertStatus(301)->assertHeader('Location', '/');
    forged('/about/')->assertStatus(301)->assertHeader('Location', '/about');
    forged('/about/?ref=x')->assertHeader('Location', '/about?ref=x');
    $script = ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => public_path('index.php')];
    forged('/index.php/about', $script)->assertStatus(301)->assertHeader('Location', '/about');
    forged('/index.php', $script)->assertHeader('Location', '/');

    Redirect::query()->create(['from_path' => '/old', 'to_url' => '/about', 'status' => 301]);
    Redirect::query()->create(['from_path' => '/partner', 'to_url' => 'https://partner.example/x', 'status' => 302]);
    forged('/old')->assertStatus(301)->assertHeader('Location', '/about');
    forged('/partner')->assertStatus(302)->assertHeader('Location', 'https://partner.example/x');
});

it('redirects plain addresses to the main language\'s prefix on the site', function () {
    app(LocaleSettings::class)->fill(['languages' => "en: English | en\nid: Bahasa Indonesia | id"])->save();
    app()->forgetInstance(LocaleSettings::class);
    app()->forgetInstance(Site::class);

    forged('/about?x=1')->assertStatus(301)->assertHeader('Location', '/en/about?x=1');
    forged('/')->assertHeader('Location', '/en');
});

it('builds the sitemap on APP_URL, whatever the Host', function () {
    config(['sunrice-cms.cache.enabled' => true]);
    $xml = (string) forged('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('<loc>https://www.acme.test/about</loc>')->toContain('https://www.acme.test/pages')
        ->not->toContain('evil.example');
    // One sitemap for every Host: the one kept is APP_URL's.
    $again = (string) TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create('/sitemap.xml')))->getContent();
    expect($again)->toBe($xml);
});
