<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\ContentSecurityPolicy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/** The policy's directives: ['script-src' => ["'self'", "'nonce-…'"], …]. */
function cspDirectives(TestResponse $response, string $header = 'Content-Security-Policy'): array
{
    $directives = [];
    foreach (explode(';', (string) $response->headers->get($header)) as $part) {
        $words = preg_split('/\s+/', trim($part)) ?: [];
        if (($name = array_shift($words)) !== null && $name !== '') {
            $directives[$name] = $words;
        }
    }

    return $directives;
}

/** The nonce the policy names in script-src. */
function cspNonce(TestResponse $response): string
{
    foreach (cspDirectives($response)['script-src'] ?? [] as $source) {
        if (preg_match("/^'nonce-(.+)'$/", $source, $match) === 1) {
            return $match[1];
        }
    }

    return '';
}

/** The panel's assets, published (in a public folder of its own), so the layout prints Vite's tags. */
function publishPanelAssets(): string
{
    app()->usePublicPath(sys_get_temp_dir().'/sunrice-csp-'.uniqid());
    $manifest = public_path('vendor/sunrice-admin/manifest.json');
    @mkdir(dirname($manifest), 0777, true);
    file_put_contents($manifest, json_encode(['resources/js/app.tsx' => ['file' => 'assets/app-abc.js', 'src' => 'resources/js/app.tsx', 'isEntry' => true, 'css' => ['assets/app-abc.css'], 'imports' => ['_chunk-def.js']], '_chunk-def.js' => ['file' => 'assets/chunk-def.js', 'src' => '_chunk-def.js']]));

    return $manifest;
}

it('sends a strict policy with a new nonce that the layout\'s scripts, styles and Vite tags carry', function () {
    usePanel();
    $manifest = publishPanelAssets();
    try {
        $response = get('/admin/login')->assertOk();
        $nonce = cspNonce($response);
        $html = (string) $response->getContent();

        expect($nonce)->toMatch('/^[A-Za-z0-9]{40}$/')
            ->and(cspDirectives($response))->toMatchArray([
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'nonce-{$nonce}'"],
                'style-src' => ["'self'", "'nonce-{$nonce}'"],
                'style-src-attr' => ["'unsafe-inline'"],
                'img-src' => ["'self'", 'data:', 'blob:'],
                'connect-src' => ["'self'"],
                'object-src' => ["'none'"],
                'base-uri' => ["'self'"],
                'form-action' => ["'self'"],
                'frame-ancestors' => ["'none'"],
            ]);
        // Every inline <script> and <style> of the layout, and Vite's tags, have it.
        preg_match_all('/<(script|style|link)\b[^>]*>/', $html, $tags);
        // Not Inertia's page data (type="application/json"), which never runs.
        $inline = array_filter($tags[0], fn (string $tag) => ! str_contains($tag, 'application/json')
            && (! str_starts_with($tag, '<link') || str_contains($tag, 'modulepreload') || str_contains($tag, 'stylesheet')));
        expect($inline)->not->toBeEmpty();
        foreach ($inline as $tag) {
            expect($tag)->toContain('nonce="'.$nonce.'"');
        }
        expect($html)->toContain('src="'.url('vendor/sunrice-admin/assets/app-abc.js').'"')
            ->toContain('<meta property="csp-nonce" nonce="'.$nonce.'">');

        // Another request, another nonce.
        expect(cspNonce(get('/admin/login')))->not->toBe($nonce)->not->toBe('');
    } finally {
        @unlink($manifest);
    }
});

it('sends the policy on the signed-in pages, not on JSON', function () {
    usePanel(fn (Panel $panel) => $panel->routes(fn () => Route::get('report.json', fn () => response()->json(['ok' => true]))->name('report')));
    actingAs(adminUser(Panel::ACCESS));

    expect(cspNonce(get('/admin')->assertOk()))->not->toBe('');
    get('/admin/report.json')->assertOk()->assertHeaderMissing('Content-Security-Policy');
});

it('can be turned off, made report-only, and given more sources', function () {
    usePanel();
    config(['sunrice-admin.security.csp.enabled' => false]);
    get('/admin/login')->assertOk()->assertHeaderMissing('Content-Security-Policy')->assertHeader('X-Frame-Options', 'DENY');
    expect(Vite::cspNonce())->toBeNull();

    config(['sunrice-admin.security.csp' => [
        'enabled' => true,
        'report_only' => true,
        'report_uri' => '/csp-reports',
        'sources' => [
            'img-src' => ['https://images.example.com', "'self'"],
            'connect-src' => 'https://api.example.com wss://live.example.com',
            'script-src' => ['https://evil.example.com;', 'https://ok.example.com'], // can't end the directive
            'upgrade-insecure-requests' => [],
        ],
    ]]);
    $response = get('/admin/login')->assertOk()->assertHeaderMissing('Content-Security-Policy');
    $directives = cspDirectives($response, 'Content-Security-Policy-Report-Only');

    expect($directives['img-src'])->toBe(["'self'", 'data:', 'blob:', 'https://images.example.com'])
        ->and($directives['connect-src'])->toBe(["'self'", 'https://api.example.com', 'wss://live.example.com'])
        ->and($directives['script-src'])->toContain('https://ok.example.com')->not->toContain('https://evil.example.com;')->not->toContain('https://evil.example.com')
        ->and($directives['upgrade-insecure-requests'])->toBe([])
        ->and($directives['report-uri'])->toBe(['/csp-reports']);
});

it('allows the media disk, the assets\' origin, plugin bundles, the web font and the Vite dev server', function () {
    config([
        'filesystems.disks.public.url' => 'https://media.example.com/storage',
        'app.asset_url' => 'https://cdn.example.com',
        'sunrice-admin.font.stylesheet' => 'https://fonts.example.com/css?family=Inter',
    ]);
    usePanel(fn (Panel $panel) => $panel->bundle(PluginBundle::make('shop')->module('https://plugins.example.com/shop.js')->style('https://plugins.example.com/shop.css')->pages('Shop/')));
    app()->usePublicPath(sys_get_temp_dir().'/sunrice-csp-'.uniqid());
    $hot = public_path('vendor/sunrice-admin/hot');
    @mkdir(dirname($hot), 0777, true);
    file_put_contents($hot, 'http://localhost:5173');
    try {
        $directives = cspDirectives(get('/admin/login')->assertOk());
    } finally {
        @unlink($hot);
    }

    expect($directives['img-src'])->toContain('https://media.example.com')->toContain('https://cdn.example.com')->toContain('http://localhost:5173')
        ->and($directives['media-src'])->toContain('https://media.example.com')
        ->and($directives['script-src'])->toContain('https://cdn.example.com')->toContain('https://plugins.example.com')->toContain('http://localhost:5173')
        ->and($directives['style-src'])->toContain('https://plugins.example.com')->toContain('https://fonts.example.com')->toContain('http://localhost:5173')
        ->and($directives['font-src'])->toContain('https://fonts.example.com')
        ->and($directives['connect-src'])->toBe(["'self'", 'http://localhost:5173', 'ws://localhost:5173']);
});

it('leaves the framework\'s debug error page without a policy', function () {
    config(['app.debug' => true]);
    usePanel(fn (Panel $panel) => $panel->routes(fn () => Route::get('broken', fn () => throw new RuntimeException('Broken'))->name('broken')));
    actingAs(adminUser(Panel::ACCESS));

    get('/admin/broken')->assertStatus(500)->assertHeaderMissing('Content-Security-Policy');
});

it('builds policies source by source, dropping what could break the header', function () {
    $policy = ContentSecurityPolicy::make()
        ->add('default-src', "'self'")
        ->add('img-src', ["'self'", 'data:', null, 'https://a.example.com', 'https://a.example.com', 'x;y', "bad'quote"])
        ->add('Bad Directive', 'https://c.example.com')
        ->nonce('abc123')
        ->nonce('not a nonce;');

    expect($policy->toString())->toBe("default-src 'self'; img-src 'self' data: https://a.example.com; script-src 'nonce-abc123'; style-src 'nonce-abc123'")
        ->and(ContentSecurityPolicy::make()->add('img-src', "https://b.example.com\r\nX-Evil: 1")->toString())->not->toContain("\n")
        ->and(ContentSecurityPolicy::origin('https://Cdn.Example.com:8443/path?x=1'))->toBe('https://cdn.example.com:8443')
        ->and(ContentSecurityPolicy::origin('/storage'))->toBeNull()
        ->and(ContentSecurityPolicy::origin('javascript:alert(1)'))->toBeNull();
});
