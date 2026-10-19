<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Branding\Branding;
use Sunrice\Admin\Media\MediaUsage;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    Storage::fake('public');
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));
});

it('brands the panel from Settings → Branding, over the panel\'s own', function () {
    actingAs(adminUser(Panel::ACCESS));
    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('sunrice.brand', 'Acme')->where('sunrice.logo', null)->where('sunrice.color', null)
        ->where('sunrice.login', ['headline' => null, 'text' => null]));
    get('/admin/settings/branding')->assertForbidden();

    $logo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png', 64, 64));
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding'));
    put('/admin/settings/branding', ['name' => 'Acme Studio', 'color' => '#0F766E', 'logo' => $logo->id, 'loginHeadline' => 'Welcome', 'loginText' => 'Ask Rina for an account.'])
        ->assertSessionHasNoErrors();

    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('sunrice.brand', 'Acme Studio')->where('sunrice.logo', $logo->url())
        ->where('sunrice.color', '#0f766e')->where('sunrice.colorText', '#ffffff'));
    expect(MediaUsage::query()->where('type', 'sunrice.branding')->value('media_id'))->toBe($logo->id);

    auth()->logout();
    get('/admin/login')->assertInertia(fn (Assert $page) => $page->where('sunrice.login', ['headline' => 'Welcome', 'text' => 'Ask Rina for an account.']));
});

it('takes only a hex colour', function () {
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding'));

    put('/admin/settings/branding', ['color' => 'red;}body{display:none'])->assertSessionHasErrors('color');
});

it('picks the text colour that reads best', function () {
    expect(Branding::readableOn('#0f766e'))->toBe('#ffffff')
        ->and(Branding::readableOn('#fde047'))->toBe('#171717')
        ->and(Branding::readableOn('#000000'))->toBe('#ffffff')
        ->and(Branding::readableOn('#ffffff'))->toBe('#171717');
});
