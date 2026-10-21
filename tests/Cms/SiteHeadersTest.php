<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Site\SiteSecurityHeaders;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['honeypot.enabled' => false]);
    Mail::fake();
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'page', 'route' => '/blog/{slug}', 'listing_route' => '/blog', 'comments' => true]);
    $this->post = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello']));
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'page', 'message' => 'Thanks!']);
});

function assertSiteHeaders(TestResponse $response): TestResponse
{
    return $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeaderMissing('Content-Security-Policy');
}

it('sends security headers with every site response', function () {
    assertSiteHeaders(get('/blog/hello')->assertOk());
    assertSiteHeaders(get('/blog')->assertOk());
    assertSiteHeaders(get('/search?q=hello')->assertOk());
    assertSiteHeaders(get('/no-such-page')->assertNotFound());
    assertSiteHeaders(get('/sitemap.xml')->assertOk());
    assertSiteHeaders(get(URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(30), ['translation' => $this->post->id]))->assertOk());
    assertSiteHeaders(post('/forms/contact', [])->assertRedirect());
    assertSiteHeaders(post("/comments/{$this->post->entry_id}", ['body' => ''])->assertRedirect());
    // Not the panel's: it has its own.
    get('/admin/login')->assertHeader('X-Frame-Options', 'DENY');
});

it('sends the headers config says, a CSP only when set, and keeps the app\'s own', function () {
    config(['sunrice-cms.site.headers' => [
        'X-Frame-Options' => null,
        'Content-Security-Policy' => "default-src 'self'",
        'Referrer-Policy' => 'no-referrer',
    ]]);
    get('/blog/hello')->assertHeaderMissing('X-Frame-Options')->assertHeader('Content-Security-Policy', "default-src 'self'")
        ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeaderMissing('Permissions-Policy');

    config(['sunrice-cms.site.headers' => ['X-Frame-Options' => 'SAMEORIGIN', 'Content-Security-Policy' => "default-src 'self'"]]);
    $response = (new SiteSecurityHeaders)->handle(Request::create('/'), fn () => response('x')->header('X-Frame-Options', 'DENY')->header('Content-Security-Policy', "default-src 'none'"));
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'");
});
