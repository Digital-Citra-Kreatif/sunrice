<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\SafeSvg;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Admin\Support\Branding;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    Storage::fake('public');
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));
});

function iconUpload(string $name, int $width = 64, int $height = 64): Media
{
    $file = str_ends_with($name, '.svg')
        ? UploadedFile::fake()->createWithContent($name, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>')
        : UploadedFile::fake()->image($name, $width, $height);

    return app(UploadMedia::class)->handle($file, []);
}

function setBranding(array $values): void
{
    app(BrandingSettings::class)->fill($values)->save();
}

it('serves the lettermark as a cacheable SVG to anyone, without a session', function () {
    setBranding(['name' => 'kebun <Studio>', 'tagline' => 'Secret tagline', 'color' => '#1e66f5']);
    $version = Branding::make()->lettermarkVersion();

    $response = get("/admin/branding/icon.svg?v={$version}")->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('immutable')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
        ->and($response->headers->getCookies())->toBe([]);

    $svg = $response->getContent();
    expect($svg)->toStartWith('<svg ')->toContain('fill="#1e66f5"')->toContain('fill="#ffffff">K</text>')
        ->not->toContain('kebun')->not->toContain('Studio')->not->toContain('Secret')->not->toContain('Acme');

    // An old or missing version is cached for a day only, and an unchanged icon is not sent again.
    expect(get('/admin/branding/icon.svg')->headers->get('Cache-Control'))->not->toContain('immutable');
    get('/admin/branding/icon.svg', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
});

it('escapes the letter and uses the neutral colour without a brand colour', function () {
    setBranding(['name' => '<b>']);

    expect(get('/admin/branding/icon.svg')->getContent())->toContain('&lt;</text>')->not->toContain('<b>')
        ->toContain('fill="'.Branding::NEUTRAL.'"');
});

it('registers the icon outside the session middleware', function () {
    expect(app('router')->getRoutes()->getByName('sunrice.admin.branding.icon')->gatherMiddleware())->not->toContain('web');
});

it('links the lettermark icon on every panel page, the sign-in page included', function () {
    setBranding(['color' => '#1e66f5']);
    $href = e(route('sunrice.admin.branding.icon', ['v' => Branding::make()->lettermarkVersion()]));

    get('/admin/login')->assertOk()->assertSee('<link rel="icon" href="'.$href.'" type="image/svg+xml">', false)
        ->assertDontSee('apple-touch-icon');

    actingAs(adminUser(Panel::ACCESS));
    get('/admin')->assertOk()->assertSee('<link rel="icon" href="'.$href.'" type="image/svg+xml">', false);

    // A new colour, a new URL.
    setBranding(['color' => '#ff0000']);
    expect(get('/admin')->getContent())->not->toContain($href);
});

it('uses a square logo as the icon, and a raster one as the home screen icon', function () {
    $logo = iconUpload('logo.png', 120, 128);
    setBranding(['logo' => $logo->id]);

    get('/admin/login')->assertOk()
        ->assertSee('<link rel="icon" href="'.e($logo->url()).'" type="image/png">', false)
        ->assertSee('<link rel="apple-touch-icon" href="'.e($logo->url()).'">', false);
});

it('keeps the lettermark for a wide logo, and takes an SVG logo', function () {
    setBranding(['logo' => iconUpload('wide.png', 300, 80)->id]);
    get('/admin/login')->assertSee('branding/icon.svg', false)->assertDontSee('apple-touch-icon');

    $svg = iconUpload('mark.svg');
    setBranding(['logo' => $svg->id]);
    get('/admin/login')->assertSee('<link rel="icon" href="'.e($svg->url()).'" type="'.$svg->mime_type.'">', false)
        ->assertDontSee('apple-touch-icon');
});

it('prefers the chosen favicon, in one query with the other images', function () {
    $logo = iconUpload('logo.png', 64, 64);
    $favicon = iconUpload('favicon.svg');
    setBranding(['logo' => $logo->id, 'favicon' => $favicon->id]);

    DB::enableQueryLog();
    $branding = Branding::make();
    $icon = $branding->icon();
    $branding->toArray();
    $mediaQueries = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'sunrice_media'))->count();

    expect($icon)->toBe(['href' => $favicon->url(), 'type' => $favicon->mime_type, 'apple' => $logo->url()])
        ->and($mediaQueries)->toBe(1);
});

it('accepts an image as the favicon, and only an image', function () {
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding', 'sunrice.media.view'));
    $pdf = app(UploadMedia::class)->handle(UploadedFile::fake()->create('terms.pdf', 5, 'application/pdf'), []);

    put('/admin/settings/branding', ['favicon' => $pdf->id, 'loginLayout' => 'split'])->assertSessionHasErrors(['favicon']);

    $png = iconUpload('icon.png', 32, 32);
    put('/admin/settings/branding', ['favicon' => $png->id, 'loginLayout' => 'split'])->assertSessionHasNoErrors();
    expect(app(BrandingSettings::class)->favicon)->toBe($png->id);
});

it('reads an SVG\'s size from its viewBox, so a wide SVG logo replaces the name and never becomes the icon', function () {
    $wide = app(UploadMedia::class)->handle(UploadedFile::fake()->createWithContent('lumina-logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 208 56"><rect width="208" height="56"/></svg>'), []);
    expect([$wide->width, $wide->height])->toBe([208, 56])
        ->and(SafeSvg::dimensions('<svg width="32px" height="30">'))->toBe([32, 30])
        ->and(SafeSvg::dimensions('<svg>'))->toBe([null, null]);

    setBranding(['logo' => $wide->id]);
    get('/admin/login')->assertSee('branding/icon.svg', false)->assertDontSee('<link rel="icon" href="'.e($wide->url()), false);
    expect(Branding::make()->toArray())->toMatchArray(['logo' => $wide->url(), 'logoWide' => true]);

    setBranding(['logo' => iconUpload('square.png', 64, 64)->id]);
    expect(Branding::make()->toArray()['logoWide'])->toBeFalse();
});
