<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use Spatie\ResponseCache\ResponseCache;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Settings\CodeSettings;
use Sunrice\Cms\Site\CspNonceReplacer;
use Sunrice\Cms\Site\SiteCsp;

use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['honeypot.enabled' => false]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'page', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($blog, ['title' => 'Hello']));
    app(CodeSettings::class)->fill([
        'head' => '<script>window.dataLayer = [];</script><script async src="https://stats.example.com/a.js"></script>',
        'bodyEnd' => '<style>.chat{color:red}</style><script nonce="their-own">chat()</script>',
    ])->save();
});

/** The nonce the site's policy names. */
function siteNonce(TestResponse $response, string $header = 'Content-Security-Policy'): string
{
    return preg_match("/script-src [^;]*'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get($header), $match) === 1 ? $match[1] : '';
}

it('sends no Content-Security-Policy by default, and no nonce', function () {
    get('/blog/hello')->assertOk()->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only')
        ->assertSee('<style nonce="">', false)
        ->assertSee('<script>window.dataLayer = [];</script>', false);
    expect(SiteCsp::enabled())->toBeFalse();
});

it('sends its policy when turned on, with a nonce on the CMS\'s styles and Site code\'s tags', function () {
    config(['sunrice-cms.site.csp.enabled' => true]);

    $response = get('/blog/hello')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $nonce = siteNonce($response);
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($nonce)->not->toBe('')
        ->and($policy)->toStartWith("default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; style-src-attr 'unsafe-inline'; img-src 'self' data:")
        ->toContain("object-src 'none'")->toContain("base-uri 'self'")->toContain("form-action 'self'")->toContain("frame-ancestors 'self'");
    $response->assertSee('<style nonce="'.$nonce.'">body{', false)
        ->assertSee('<script nonce="'.$nonce.'">window.dataLayer = [];</script>', false)
        ->assertSee('<script nonce="'.$nonce.'" async src="https://stats.example.com/a.js"></script>', false)
        ->assertSee('<style nonce="'.$nonce.'">.chat{color:red}</style>', false)
        // A tag that has a nonce of its own keeps it.
        ->assertSee('<script nonce="their-own">chat()</script>', false);
    expect(siteNonce(get('/blog')->assertOk()))->not->toBe('')->not->toBe($nonce)
        // Not the sitemap's XML.
        ->and(get('/sitemap.xml')->assertOk()->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('takes more sources, report-only mode, no nonce, and the app\'s own policy string', function () {
    config(['sunrice-cms.site.csp' => [
        'enabled' => true,
        'report_only' => true,
        'report_uri' => 'https://reports.example.com/csp',
        'nonce' => false,
        'sources' => ['script-src' => ["'unsafe-inline'", 'https://www.googletagmanager.com'], 'img-src' => 'https:', 'frame-src' => ['https://www.youtube-nocookie.com']],
    ]]);
    $response = get('/blog/hello')->assertOk()->assertHeaderMissing('Content-Security-Policy')->assertSee('<script>window.dataLayer = [];</script>', false);
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');
    expect($policy)->toContain("script-src 'self' 'unsafe-inline' https://www.googletagmanager.com;")->not->toContain('nonce-')
        ->toContain("img-src 'self' data: https:")->toContain("frame-src 'self' https://www.youtube-nocookie.com")
        ->toContain('report-uri https://reports.example.com/csp');

    config(['sunrice-cms.site.csp.report_only' => false, 'sunrice-cms.site.headers.Content-Security-Policy' => "default-src 'none'"]);
    get('/blog/hello')->assertHeader('Content-Security-Policy', "default-src 'none'");
});

it('allows the media disk, the assets\' origin and the captcha', function () {
    config([
        'sunrice-cms.site.csp.enabled' => true,
        'filesystems.disks.public.url' => 'https://media.example.com/storage',
        'app.asset_url' => 'https://cdn.example.com',
        'sunrice-admin.captcha' => ['provider' => 'turnstile', 'site_key' => 'site', 'secret_key' => 'secret'],
    ]);
    $policy = (string) get('/blog/hello')->headers->get('Content-Security-Policy');

    expect($policy)->toContain("img-src 'self' data: https://cdn.example.com https://media.example.com;")
        ->toContain('media-src \'self\' https://cdn.example.com https://media.example.com;')
        ->toContain("script-src 'self' 'nonce-")->toContain(' https://cdn.example.com https://challenges.cloudflare.com;')
        ->toContain("frame-src 'self' https://challenges.cloudflare.com;");
    // The captcha's script carries the nonce.
    expect((string) Blade::render('{!! app(\Sunrice\Admin\Security\Captcha::class)->widget() !!}'))->toContain('nonce="'.SiteCsp::nonce().'" async defer');
});

it('gives kept pages the nonce of the request that serves them', function () {
    config(['sunrice-cms.site.csp.enabled' => true, 'sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);
    expect(config('responsecache.replacers'))->toContain(CspNonceReplacer::class);
    app(ResponseCache::class)->clear();

    $first = get('/blog/hello')->assertOk()->assertHeader('X-Cache-Status', 'MISS');
    $kept = get('/blog/hello')->assertOk()->assertHeader('X-Cache-Status', 'HIT');
    $again = get('/blog/hello')->assertOk()->assertHeader('X-Cache-Status', 'HIT');

    $nonces = array_map(siteNonce(...), [$first, $kept, $again]);
    expect(array_unique(array_filter($nonces)))->toHaveCount(3);
    foreach ([$first, $kept, $again] as $index => $response) {
        $html = (string) $response->getContent();
        expect($html)->toContain('<style nonce="'.$nonces[$index].'">body{')
            ->toContain('<script nonce="'.$nonces[$index].'">window.dataLayer')
            ->not->toContain('__sunrice_csp_nonce__');
        foreach ($nonces as $other => $nonce) {
            if ($other !== $index) {
                expect($html)->not->toContain($nonce);
            }
        }
    }
});
