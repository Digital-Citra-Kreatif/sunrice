<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Admin\Support\Branding;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\put;

beforeEach(function () {
    Storage::fake('public');
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));
});

function displayImage(string $name, int $width, int $height): Media
{
    return app(UploadMedia::class)->handle(UploadedFile::fake()->image($name, $width, $height), []);
}

function saveBranding(array $values): void
{
    app(BrandingSettings::class)->fill($values)->save();
}

it('saves "Sidebar shows", only as one of its choices and only with the branding permission', function () {
    actingAs(adminUser(Panel::ACCESS));
    put('/admin/settings/branding', ['brandDisplay' => 'logo', 'loginLayout' => 'split'])->assertForbidden();
    expect(app(BrandingSettings::class)->brandDisplay)->toBe('auto');

    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding'));
    get('/admin/settings/branding')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Settings/Edit', shouldExist: false)
        ->where('values.brandDisplay', 'auto')
        ->where('form.0.fields.4.type', 'brand_display')
        ->where('form.0.fields.4.options.0.value', 'auto')
        ->where('form.0.fields.4.options.4.value', 'name')
        ->where('form.0.fields.4.previewUrl', route('sunrice.admin.branding.preview')));

    put('/admin/settings/branding', ['brandDisplay' => 'wordmark', 'loginLayout' => 'split'])->assertSessionHasErrors(['brandDisplay']);
    put('/admin/settings/branding', ['brandDisplay' => ['logo'], 'loginLayout' => 'split'])->assertSessionHasErrors(['brandDisplay']);
    expect(app(BrandingSettings::class)->brandDisplay)->toBe('auto');

    foreach (['logo_name', 'logo', 'icon_name', 'name', 'auto'] as $choice) {
        put('/admin/settings/branding', ['brandDisplay' => $choice, 'loginLayout' => 'split'])->assertSessionHasNoErrors();
        expect(app(BrandingSettings::class)->brandDisplay)->toBe($choice);
    }
});

it('shares what the sidebar shows and the square mark for every combination of images', function (?array $logo, ?array $icon, array $expected) {
    $logoFile = $logo === null ? null : displayImage('logo.png', ...$logo);
    $iconFile = $icon === null ? null : displayImage('icon.png', ...$icon);
    $url = fn (?string $which) => match ($which) {
        'logo' => $logoFile?->url(),
        'icon' => $iconFile?->url(),
        default => null,
    };

    foreach ($expected as $choice => [$display, $mark]) {
        saveBranding(['logo' => $logoFile?->id, 'favicon' => $iconFile?->id, 'brandDisplay' => $choice]);
        $shared = Branding::make()->toArray();
        expect([$choice, $shared['display'], $shared['mark']])->toBe([$choice, $display, $url($mark)]);
    }
})->with([
    // [logo size], [icon size], choice => [display, mark]
    'no images' => [null, null, [
        'auto' => ['logo_name', null], 'logo_name' => ['logo_name', null], 'logo' => ['logo_name', null],
        'icon_name' => ['icon_name', null], 'name' => ['name', null],
    ]],
    'a square logo' => [[64, 64], null, [
        'auto' => ['logo_name', 'logo'], 'logo_name' => ['logo_name', 'logo'], 'logo' => ['logo', 'logo'],
        'icon_name' => ['icon_name', 'logo'], 'name' => ['name', 'logo'],
    ]],
    'a wide logo' => [[300, 80], null, [
        'auto' => ['logo', null], 'logo_name' => ['logo_name', null], 'logo' => ['logo', null],
        'icon_name' => ['icon_name', null], 'name' => ['name', null],
    ]],
    'a wide logo with an icon' => [[300, 80], [32, 32], [
        'auto' => ['logo', 'icon'], 'logo_name' => ['logo_name', 'icon'], 'logo' => ['logo', 'icon'],
        'icon_name' => ['icon_name', 'icon'], 'name' => ['name', 'icon'],
    ]],
    'an icon only' => [null, [32, 32], [
        'auto' => ['logo_name', 'icon'], 'logo_name' => ['logo_name', 'icon'], 'logo' => ['logo_name', 'icon'],
        'icon_name' => ['icon_name', 'icon'], 'name' => ['name', 'icon'],
    ]],
]);

it('keeps today\'s look when nothing is chosen', function () {
    // An install from before the setting: no brandDisplay stored at all.
    $wide = displayImage('wide.png', 300, 80);
    saveBranding(['logo' => $wide->id]);
    expect(Branding::make()->toArray())->toMatchArray(['logoWide' => true, 'display' => 'logo', 'mark' => null]);

    $square = displayImage('square.png', 64, 64);
    saveBranding(['logo' => $square->id]);
    expect(Branding::make()->toArray())->toMatchArray(['logoWide' => false, 'display' => 'logo_name', 'mark' => $square->url()]);

    // Anything unknown stored is Automatic too.
    saveBranding(['logo' => $wide->id, 'brandDisplay' => 'sideways']);
    expect(Branding::make()->display())->toBe('logo');
});

it('links the same image as the favicon as the collapsed sidebar shows', function () {
    $wide = displayImage('wide.png', 300, 80);
    $square = displayImage('square.png', 64, 64);
    $icon = displayImage('icon.png', 32, 32);

    // The browser icon first, else the square logo, else the lettermark: unchanged, and the same as `mark`.
    foreach ([[$wide, $icon, $icon], [$square, $icon, $icon], [$square, null, $square], [$wide, null, null], [null, null, null]] as [$logo, $favicon, $expected]) {
        saveBranding(['logo' => $logo?->id, 'favicon' => $favicon?->id]);
        $branding = Branding::make();
        $href = $expected?->url() ?? route('sunrice.admin.branding.icon', ['v' => $branding->lettermarkVersion()]);

        expect($branding->icon()['href'])->toBe($href)
            ->and($branding->toArray()['mark'])->toBe($expected?->url());
        get('/admin/login')->assertSee('<link rel="icon" href="'.e($href).'"', false);
    }
});

it('previews the form\'s unsaved images and each choice, for editors of the branding only', function () {
    $wide = displayImage('wide.png', 300, 80);
    $icon = displayImage('icon.png', 32, 32);

    actingAs(adminUser(Panel::ACCESS));
    getJson('/admin/branding/preview?logo='.$wide->id)->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding'));
    getJson('/admin/branding/preview?'.http_build_query(['name' => 'northline', 'tagline' => 'Corporate', 'logo' => $wide->id, 'favicon' => $icon->id, 'color' => '#1e66f5']))
        ->assertOk()
        ->assertExactJson([
            'name' => 'northline',
            'tagline' => 'Corporate',
            'logo' => $wide->url(),
            'logoWide' => true,
            'mark' => $icon->url(),
            'lettermark' => 'N',
            'color' => '#1e66f5',
            'displays' => ['auto' => 'logo', 'logo_name' => 'logo_name', 'logo' => 'logo', 'icon_name' => 'icon_name', 'name' => 'name'],
        ]);

    getJson('/admin/branding/preview')->assertOk()
        ->assertJsonPath('name', 'Acme')
        ->assertJsonPath('logo', null)
        ->assertJsonPath('mark', null)
        ->assertJsonPath('displays.logo', 'logo_name');
    getJson('/admin/branding/preview?logo=abc')->assertUnprocessable();

    // Nothing was saved.
    expect(app(BrandingSettings::class)->logo)->toBeNull();
});
