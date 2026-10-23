<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Events\SettingsSaved;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Password;
use Sunrice\Admin\Security\SecuritySettings;
use Sunrice\Admin\Settings\Settings;
use Sunrice\Admin\Settings\SettingsStore;
use Sunrice\Tests\Admin\Fixtures\Currency;
use Sunrice\Tests\Admin\Fixtures\LooseSettings;
use Sunrice\Tests\Admin\Fixtures\MailSettings;
use Sunrice\Tests\Admin\Fixtures\ShopSettings;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->settings(MailSettings::class)
        ->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->settings(ShopSettings::class))));
});

/** Store raw values for a section, as an older version might have. */
function storeSettings(string $key, array $values): void
{
    DB::table(SettingsStore::TABLE)->insert(['key' => $key, 'values' => json_encode($values), 'created_at' => now(), 'updated_at' => now()]);
    app(SettingsStore::class)->forget();
}

it('reads typed settings, with defaults until saved', function () {
    $shop = app(ShopSettings::class);
    expect($shop->currency)->toBe(Currency::Rupiah)
        ->and($shop->freeShippingFrom)->toBeNull()
        ->and($shop->countries)->toBe(['ID']);

    $shop->currency = Currency::Dollar;
    $shop->freeShippingFrom = 100000;
    $shop->paymentKey = 'sk_live_123';
    $shop->countries = ['ID', 'SG'];
    $shop->save();

    $fresh = app(ShopSettings::class);
    expect($fresh->currency)->toBe(Currency::Dollar)
        ->and($fresh->freeShippingFrom)->toBe(100000)
        ->and($fresh->paymentKey)->toBe('sk_live_123')
        ->and($fresh->countries)->toBe(['ID', 'SG'])
        ->and(app(MailSettings::class)->from)->toBe('shop@example.com');

    // The secret is encrypted in the database.
    $stored = json_decode(DB::table(SettingsStore::TABLE)->where('key', 'shop')->value('values'), true);
    expect($stored['paymentKey'])->not->toContain('sk_live')
        ->and(json_decode(Crypt::decryptString($stored['paymentKey'])))->toBe('sk_live_123')
        ->and($stored['currency'])->toBe('USD');
});

it('falls back to defaults for stored values that no longer fit', function () {
    storeSettings('shop', [
        'name' => ['not', 'a', 'string'],
        'currency' => 'EUR',
        'guestCheckout' => '0',
        'freeShippingFrom' => '250',
        'taxRate' => 'lots',
        'countries' => 'ID',
        'paymentKey' => 'not encrypted',
        'removed' => 'old setting',
    ]);

    $shop = app(ShopSettings::class);
    expect($shop->name)->toBe('Shop')
        ->and($shop->currency)->toBe(Currency::Rupiah)
        ->and($shop->guestCheckout)->toBeFalse()
        ->and($shop->freeShippingFrom)->toBe(250)
        ->and($shop->taxRate)->toBe(0.11)
        ->and($shop->countries)->toBe(['ID'])
        ->and($shop->paymentKey)->toBe('');
});

it('reads all sections with one cached query', function () {
    app(ShopSettings::class)->fill(['name' => 'Toko'])->save();

    DB::enableQueryLog();
    app(SettingsStore::class)->forget();
    app(ShopSettings::class);
    app(MailSettings::class);
    app()->forgetScopedInstances();
    expect(app(ShopSettings::class)->name)->toBe('Toko')
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('refuses settings without a supported type or a default', function () {
    expect(fn () => SettingsStore::properties(LooseSettings::class))->toThrow(PanelMisconfigured::class, 'needs a type')
        ->and(fn () => app(LooseSettings::class))->toThrow(PanelMisconfigured::class);
});

it('registers sections with a permission each and one sidebar item', function () {
    $panel = app(Panel::class);
    expect($panel->getPermissions())->toHaveKeys(['settings.outbox', 'shop.settings.shop'])
        ->and($panel->getPermissions()['shop.settings.shop']->label)->toBe('Edit Shop settings');

    actingAs(adminUser(Panel::ACCESS, 'shop.settings.shop'));
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('navigation.0.items.0.url', 'settings'));
    get('/admin/settings')->assertRedirect('/admin/settings/shop');
    get('/admin/settings/outbox')->assertForbidden();
    put('/admin/settings/outbox', ['from' => 'x@example.com'])->assertForbidden();
    get('/admin/settings/nope')->assertNotFound();

    actingAs(adminUser(Panel::ACCESS));
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('navigation', []));
    get('/admin/settings')->assertForbidden();

    expect(fn () => Panel::make()->settings(MailSettings::class, MailSettings::class))->toThrow(PanelMisconfigured::class, 'Two settings sections');
});

it('edits a section: validated, typed, secrets kept when left empty', function () {
    Event::fake([SettingsSaved::class]);
    actingAs(adminUser(Panel::ACCESS, 'shop.settings.shop', 'settings.outbox'));

    get('/admin/settings/shop')->assertInertia(fn (Assert $page) => $page
        ->component('Settings/Edit', shouldExist: false)
        ->where('section', ['key' => 'shop', 'label' => 'Shop', 'description' => 'How the shop sells.', 'warning' => null])
        ->where('sections', [['key' => 'outbox', 'label' => 'Outbox', 'url' => 'settings/outbox'], ['key' => 'shop', 'label' => 'Shop', 'url' => 'settings/shop']])
        ->where('values', ['name' => 'Shop', 'currency' => 'IDR', 'guestCheckout' => true, 'freeShippingFrom' => null, 'taxRate' => 0.11, 'countries' => ['ID'], 'paymentKey' => null])
        ->where('secrets', ['paymentKey' => false])
        ->has('form', 2));

    put('/admin/settings/shop', ['name' => '', 'currency' => 'EUR', 'taxRate' => 2, 'countries' => ['XX'], 'paymentKey' => ''])
        ->assertSessionHasErrors(['name', 'currency', 'taxRate', 'countries.0', 'paymentKey']);

    put('/admin/settings/shop', [
        'name' => 'Toko', 'currency' => 'USD', 'freeShippingFrom' => '50000', 'taxRate' => '0.1',
        'countries' => ['SG'], 'paymentKey' => 'sk_1', 'orders' => 99,
    ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Shop settings saved.');

    $shop = app(ShopSettings::class);
    expect($shop->name)->toBe('Toko')
        ->and($shop->currency)->toBe(Currency::Dollar)
        ->and($shop->guestCheckout)->toBeFalse() // unchecked: not sent
        ->and($shop->freeShippingFrom)->toBe(50000)
        ->and($shop->taxRate)->toBe(0.1)
        ->and($shop->countries)->toBe(['SG'])
        ->and($shop->paymentKey)->toBe('sk_1')
        ->and($shop->orders)->toBe(0); // not on the form
    Event::assertDispatched(SettingsSaved::class, fn (SettingsSaved $e) => $e->section === 'shop'
        && $e->changed === ['name', 'currency', 'guestCheckout', 'freeShippingFrom', 'taxRate', 'countries', 'paymentKey']);

    // Saved secret: the form leaves it empty, and empty keeps it.
    get('/admin/settings/shop')->assertInertia(fn (Assert $page) => $page->where('values.paymentKey', null)->where('secrets', ['paymentKey' => true]));
    put('/admin/settings/shop', ['name' => 'Toko', 'currency' => 'USD', 'taxRate' => '0.1', 'paymentKey' => ''])->assertSessionHasNoErrors();
    expect(app(ShopSettings::class)->paymentKey)->toBe('sk_1');
});

it('refuses settings forms with fields that aren\'t properties or can\'t be settings', function () {
    $bad = new class extends Settings
    {
        public string $password = '';

        public static function key(): string
        {
            return 'bad';
        }

        public static function form(Form $form): Form
        {
            return $form->schema([Password::make('password')]);
        }
    };
    app()->forgetInstance(Panel::class);
    usePanel(fn (Panel $panel) => $panel->settings($bad::class));
    actingAs(adminUser(Panel::ACCESS, 'settings.bad'));

    $this->withoutExceptionHandling();
    expect(fn () => get('/admin/settings/bad'))->toThrow(PanelMisconfigured::class, 'use Secret for secrets');
});

it('lists the panel\'s Security and Mail sections first, for those allowed', function () {
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.security', 'sunrice.settings.mail', 'settings.outbox'));

    get('/admin/settings/outbox')->assertInertia(fn (Assert $page) => $page
        ->where('sections', [['key' => 'security', 'label' => 'Security', 'url' => 'settings/security'], ['key' => 'mail', 'label' => 'Mail', 'url' => 'settings/mail'], ['key' => 'outbox', 'label' => 'Outbox', 'url' => 'settings/outbox']])
        ->where('cards', []));
});

it('announces what changed when settings are saved in code, as the screen does', function () {
    Event::fake([SettingsSaved::class]);

    $shop = app(ShopSettings::class);
    $shop->currency = Currency::Dollar;
    $shop->freeShippingFrom = 100;
    expect($shop->save())->toBe(['currency', 'freeShippingFrom']);
    Event::assertDispatched(SettingsSaved::class, fn (SettingsSaved $event) => $event->section === 'shop' && $event->changed === ['currency', 'freeShippingFrom']);

    // Saving the same values again announces nothing.
    expect(app(ShopSettings::class)->save())->toBe([]);
    Event::assertDispatchedTimes(SettingsSaved::class, 1);
});

it('answers a save with the saved values, which the form starts again from', function () {
    app(SecuritySettings::class)->fill(['twoFactor' => true])->save();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.security'));

    put('/admin/settings/security', ['twoFactor' => false], ['X-Inertia' => 'true', 'Referer' => url('/admin/settings/security')])
        ->assertRedirect('/admin/settings/security');
    get('/admin/settings/security')->assertInertia(fn (Assert $page) => $page->where('values.twoFactor', false));
    expect(app(SecuritySettings::class)->twoFactor)->toBeFalse();
});
