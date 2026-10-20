<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Admin\Settings\Settings;
use Sunrice\Admin\Support\Branding;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    Storage::fake('public');
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));
});

/** Save branding values directly, as validated input or not. */
function brand(array $values): void
{
    app(BrandingSettings::class)->fill($values)->save();
}

function brandingUpload(string $name): Media
{
    $file = str_ends_with($name, '.pdf') ? UploadedFile::fake()->create($name, 5, 'application/pdf') : UploadedFile::fake()->image($name, 64, 64);

    return app(UploadMedia::class)->handle($file, []);
}

it('is a settings section of every panel, guarded by its permission', function () {
    $panel = app(Panel::class);
    expect(array_keys($panel->getSettings()))->toContain('branding')
        ->and($panel->getSettings()['branding']->permission)->toBe('sunrice.settings.branding')
        ->and($panel->isPrivilegedPermission('sunrice.settings.branding'))->toBeTrue();

    actingAs(adminUser(Panel::ACCESS));
    get('/admin/settings/branding')->assertForbidden();
    put('/admin/settings/branding', ['name' => 'Evil'])->assertForbidden();
    expect(app(BrandingSettings::class)->name)->toBe('');

    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding'));
    get('/admin/settings/branding')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Settings/Edit', shouldExist: false)
        ->where('section.key', 'branding')
        ->where('values.loginLayout', 'split')
        ->where('form.0.fields.4.inputType', 'color'));
});

it('refuses another section with the key "branding"', function () {
    $taken = new class extends Settings
    {
        public static function key(): string
        {
            return 'branding';
        }

        public static function form(Form $form): Form
        {
            return $form;
        }
    };

    expect(fn () => Panel::make()->settings($taken::class)->boot())->toThrow(PanelMisconfigured::class, 'belongs to the panel');
});

it('validates the colour, layout, font and images', function () {
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding', 'sunrice.media.view'));
    $pdf = brandingUpload('terms.pdf');

    put('/admin/settings/branding', [
        'color' => 'red;}</style>',
        'loginLayout' => 'sideways',
        'font' => 'comic-sans',
        'logo' => $pdf->id,
        'loginImage' => 999,
    ])->assertSessionHasErrors(['color', 'loginLayout', 'font', 'logo', 'loginImage']);

    put('/admin/settings/branding', ['color' => '#12345'])->assertSessionHasErrors(['color']);
    expect(app(BrandingSettings::class)->color)->toBe('');

    $logo = brandingUpload('logo.png');
    put('/admin/settings/branding', [
        'name' => 'Sunrise Studio',
        'tagline' => 'Content workspace',
        'logo' => $logo->id,
        'font' => 'manrope',
        'color' => '#1E66F5',
        'loginLayout' => 'centered',
        'loginHeadline' => "Hello.\nWelcome back.",
    ])->assertSessionHasNoErrors();

    $saved = app(BrandingSettings::class);
    expect($saved->name)->toBe('Sunrise Studio')
        ->and($saved->logo)->toBe($logo->id)
        ->and($saved->font)->toBe('manrope')
        ->and($saved->color)->toBe('#1E66F5')
        ->and($saved->loginLayout)->toBe('centered');
});

it('loads the whole page again after a change, so the new look shows', function () {
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding'));

    put('/admin/settings/branding', ['color' => '#1e66f5', 'loginLayout' => 'split'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('sunrice.admin.settings.edit', 'branding'))
        ->assertSessionHas('success', 'Branding settings saved.');
});

it('picks black or white text for the brand colour by contrast', function () {
    expect(Branding::readableOn('#ffffff'))->toBe('#111111')
        ->and(Branding::readableOn('#000000'))->toBe('#ffffff')
        ->and(Branding::readableOn('#1e66f5'))->toBe('#ffffff')
        ->and(Branding::readableOn('#facc15'))->toBe('#111111')
        ->and(Branding::readableOn('#22c55e'))->toBe('#111111')
        ->and(Branding::readableOn('#7c3aed'))->toBe('#ffffff')
        ->and(Branding::luminance('#ffffff'))->toBe(1.0)
        ->and(Branding::luminance('#000000'))->toBe(0.0)
        ->and(Branding::lighten('#facc15', 0.3))->toBe('#facc15')
        ->and(Branding::luminance(Branding::lighten('#0b1f4d', 0.3)))->toBeGreaterThanOrEqual(0.3);
});

it('prints the font and the brand colour for light and dark mode', function () {
    get('/admin/login')->assertOk()
        ->assertSee('https://fonts.bunny.net/css?family=inter:400,500,600&amp;display=swap', false)
        ->assertDontSee('--primary:', false);

    brand(['font' => 'manrope', 'color' => '#1E66F5']);
    $dark = Branding::lighten('#1e66f5', 0.3);

    get('/admin/login')->assertOk()
        ->assertSee('<link rel="stylesheet" href="https://fonts.bunny.net/css?family=manrope:400,500,600&amp;display=swap">', false)
        ->assertSee('html:root{--sunrice-font:"Manrope";}', false)
        ->assertSee('html:root{--primary:#1e66f5;--primary-foreground:#ffffff;--ring:color-mix(in oklch,#1e66f5 70%,var(--background));--sidebar-primary:#1e66f5;', false)
        ->assertSee("html.dark{--primary:{$dark};", false);

    brand(['font' => 'system']);
    get('/admin/login')->assertOk()
        ->assertDontSee('fonts.bunny.net')
        ->assertSee('--sunrice-font:'.Branding::SYSTEM_FONT, false);
});

it('keeps the font config as the fallback', function () {
    config(['sunrice-admin.font' => ['stylesheet' => 'https://fonts.example.com/css?family=brand', 'family' => 'Brand Sans']]);

    get('/admin/login')->assertOk()
        ->assertSee('<link rel="preconnect" href="https://fonts.example.com" crossorigin>', false)
        ->assertSee('html:root{--sunrice-font:"Brand Sans";}', false);

    brand(['font' => 'figtree']);
    get('/admin/login')->assertOk()
        ->assertDontSee('fonts.example.com')
        ->assertSee('--sunrice-font:"Figtree"', false);
});

it('never prints stored values that are not a known font or a colour', function () {
    // Stored without the form (an older version, or a direct write).
    brand(['font' => '"};</style><script>alert(1)</script>', 'color' => '#fff;}</style><script>alert(1)</script>']);

    get('/admin/login')->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('--primary:', false)
        ->assertSee('family=inter', false);

    brand(['name' => '</title><script>alert(1)</script>']);
    get('/admin/login')->assertOk()
        ->assertDontSee('</title><script>alert(1)</script>', false)
        ->assertSee('&lt;/title&gt;&lt;script&gt;', false);
});

it('carries the CSP nonce on the branding styles', function () {
    Vite::useCspNonce('n0nc3');
    brand(['color' => '#1e66f5']);

    get('/admin/login')->assertOk()->assertSee('nonce="n0nc3" >html:root{--primary:#1e66f5', false);
});

it('shares the branding with every page, with the panel\'s defaults for what is empty', function () {
    get('/admin/login')->assertInertia(fn (Assert $page) => $page
        ->where('sunrice.brand', 'Acme')
        ->where('sunrice.branding', [
            'name' => 'Acme',
            'tagline' => '',
            'logo' => null,
            'lettermark' => 'A',
            'color' => null,
            'login' => [
                'layout' => 'split',
                'eyebrow' => BrandingSettings::LOGIN_DEFAULTS['eyebrow'],
                'headline' => BrandingSettings::LOGIN_DEFAULTS['headline'],
                'text' => BrandingSettings::LOGIN_DEFAULTS['text'],
                'footer' => BrandingSettings::LOGIN_DEFAULTS['footer'],
                'image' => null,
            ],
        ]));

    $logo = brandingUpload('logo.png');
    $side = brandingUpload('side.jpg');
    brand(['name' => 'ümbra', 'tagline' => 'Studio', 'logo' => $logo->id, 'color' => '#FACC15',
        'loginLayout' => 'centered', 'loginEyebrow' => 'Hi', 'loginImage' => $side->id]);

    get('/admin/login')->assertInertia(fn (Assert $page) => $page
        ->where('sunrice.brand', 'ümbra')
        ->where('sunrice.branding.name', 'ümbra')
        ->where('sunrice.branding.lettermark', 'Ü')
        ->where('sunrice.branding.tagline', 'Studio')
        ->where('sunrice.branding.logo', $logo->url())
        ->where('sunrice.branding.color', '#facc15')
        ->where('sunrice.branding.login.layout', 'centered')
        ->where('sunrice.branding.login.eyebrow', 'Hi')
        ->where('sunrice.branding.login.text', BrandingSettings::LOGIN_DEFAULTS['text'])
        ->where('sunrice.branding.login.image', $side->url()));
    get('/admin/login')->assertSee('<title inertia>ümbra</title>', false);

    // A logo that went to the trash falls back to the lettermark.
    $logo->delete();
    get('/admin/login')->assertInertia(fn (Assert $page) => $page->where('sunrice.branding.logo', null));
});

it('gives the browser tab an icon: the lettermark on the brand colour', function () {
    $icon = rawurldecode(Branding::make()->favicon());
    expect($icon)->toStartWith('data:image/svg+xml,<svg')
        ->toContain('fill="#2f2a26"')
        ->toContain('>A</text>');

    brand(['name' => '<b>', 'color' => '#facc15']);
    $icon = rawurldecode(Branding::make()->favicon());
    expect($icon)->toContain('fill="#facc15"')
        ->toContain('fill="#111111"')
        ->toContain('>&lt;</text>')
        ->not->toContain('<b>');

    get('/admin/login')->assertOk()->assertSee('<link rel="icon" href="data:image/svg+xml,', false);
});
