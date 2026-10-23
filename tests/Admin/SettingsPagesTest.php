<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Settings\SettingsFields;
use Sunrice\Tests\Admin\Fixtures\MailSettings;
use Sunrice\Tests\Admin\Fixtures\ShopCheckoutController;
use Sunrice\Tests\Admin\Fixtures\ShopSettings;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

// Panel::settingsPage(): a page of a plugin's own at settings/{key}, without the CMS.
it('serves a plugin\'s own settings page at settings/{key}, in the Settings menu after the section named', function () {
    usePanel(fn (Panel $panel) => $panel->settings(MailSettings::class)
        ->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->settings(ShopSettings::class)
            ->settingsPage('checkout', 'Checkout', ShopCheckoutController::class, 'shop.settings.shop', after: 'outbox'))));
    $user = adminUser(Panel::ACCESS, 'shop.settings.shop', 'settings.outbox');

    expect(app(Panel::class)->settingsNavigation($user))->toBe([
        ['key' => 'outbox', 'label' => 'Outbox', 'url' => 'settings/outbox'],
        ['key' => 'checkout', 'label' => 'Checkout', 'url' => 'settings/checkout'],
        ['key' => 'shop', 'label' => 'Shop', 'url' => 'settings/shop'],
    ])->and(route('sunrice.admin.settings.checkout.edit', absolute: false))->toBe('/admin/settings/checkout')
        ->and(route('sunrice.admin.settings.checkout.update', absolute: false))->toBe('/admin/settings/checkout');
    // Not in the sidebar: Settings is, current on it.
    $items = collect(app(Panel::class)->navigationFor($user))->flatMap(fn (array $group) => $group['items']);
    expect($items->pluck('key')->all())->toBe(['settings'])->and($items->first()['matches'])->toBe(['settings/checkout']);

    actingAs($user);
    get('/admin/settings/checkout')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Shop/Checkout', false)
        ->where('settingsNav.1.key', 'checkout')
        ->where('checkout.sections.0.title', 'Checkout')
        ->where('checkout.sections.0.fields.0.name', 'guestCheckout')
        ->where('checkout.values', ['guestCheckout' => true, 'freeShippingFrom' => null])
        ->where('checkout.secrets', []));
    // Checked as the Settings page checks its fields, and only these fields are saved.
    put('/admin/settings/checkout', ['guestCheckout' => false, 'freeShippingFrom' => -5])->assertSessionHasErrors('freeShippingFrom');
    put('/admin/settings/checkout', ['guestCheckout' => false, 'freeShippingFrom' => 500000, 'name' => 'Not this'])->assertSessionHasNoErrors();
    expect(app(ShopSettings::class)->guestCheckout)->toBeFalse()
        ->and(app(ShopSettings::class)->freeShippingFrom)->toBe(500000)
        ->and(app(ShopSettings::class)->name)->toBe('Shop');

    // Behind its permission.
    actingAs(adminUser(Panel::ACCESS, 'settings.outbox'));
    expect(array_column(app(Panel::class)->settingsNavigation(auth()->user()), 'key'))->toBe(['outbox']);
    get('/admin/settings/checkout')->assertForbidden();
    put('/admin/settings/checkout', ['guestCheckout' => true])->assertForbidden();
});

it('refuses a settings page whose key is a section\'s or another page\'s', function () {
    $page = fn (string $key) => fn (Panel $p) => $p->settingsPage($key, 'Checkout', ShopCheckoutController::class, 'shop.settings.shop');

    expect(fn () => Panel::make()->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->settings(ShopSettings::class)))
        ->plugin(new TestPlugin('extra', register: $page('shop')))->boot())
        ->toThrow(PanelMisconfigured::class, 'The settings page "shop" has the key of the settings section')
        ->and(fn () => Panel::make()->plugin(new TestPlugin('extra', register: $page('mail')))->boot())
        ->toThrow(PanelMisconfigured::class, 'The settings page "mail" has the key of the settings section')
        ->and(fn () => Panel::make()->plugin(new TestPlugin('extra', register: fn (Panel $p) => $page('checkout')($page('checkout')($p))))->boot())
        ->toThrow(PanelMisconfigured::class, 'Two settings pages have the key "checkout"')
        ->and(fn () => Panel::make()->plugin(new TestPlugin('extra', register: $page('Check out')))->boot())
        ->toThrow(PanelMisconfigured::class, 'use lowercase letters')
        ->and(fn () => Panel::make()->plugin(new TestPlugin('extra', register: fn (Panel $p) => $p->settingsPage('checkout', 'Checkout', ShopSettings::class, 'shop.settings.shop')))->boot())
        ->toThrow(PanelMisconfigured::class, 'needs a controller with edit() and update()');
});

it('refuses fields that aren\'t the settings class\'s properties', function () {
    expect(fn () => SettingsFields::make(ShopSettings::class, (new Form)->schema([TextInput::make('nope')])))
        ->toThrow(PanelMisconfigured::class, 'isn\'t one of its properties');
});

it('offers a settings page\'s fields to AI agents as a section of its key, behind its permission', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->settings(ShopSettings::class)
        ->settingsPage('checkout', 'Checkout', ShopCheckoutController::class, 'shop.settings.shop', fields: ShopCheckoutController::fields(...)))));
    app(SyncPermissions::class)->handle();
    $agent = function (string ...$permissions): string {
        $user = adminUser(Panel::ACCESS, 'sunrice.ai.connect', ...$permissions);

        return AiToken::issue((string) $user->getKey(), 'Claude')[1];
    };
    $call = function (string $token, string $tool, array $arguments = []): array|string {
        $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);
        $text = (string) $response->json('result.content.0.text');

        return $response->json('result.isError') === true ? $text : json_decode($text, true);
    };
    $token = $agent('shop.settings.shop');

    expect(collect($call($token, 'get_panel_info')['settings'])->pluck('label', 'key')->all())->toBe(['shop' => 'Shop', 'checkout' => 'Checkout'])
        ->and($call($token, 'get_settings', ['section' => 'checkout'])['values'])->toBe(['guestCheckout' => true, 'freeShippingFrom' => null])
        ->and($call($token, 'update_settings', ['section' => 'checkout', 'values' => ['freeShippingFrom' => -1]]))->toContain('Not saved:')
        ->and($call($token, 'update_settings', ['section' => 'checkout', 'values' => ['freeShippingFrom' => 100, 'name' => 'Not this']]))->toBe(['saved' => true, 'changed' => ['freeShippingFrom']])
        ->and([app(ShopSettings::class)->freeShippingFrom, app(ShopSettings::class)->name])->toBe([100, 'Shop'])
        ->and(Activity::query()->where('subject_type', 'settings')->where('subject_id', 'shop')->exists())->toBeTrue();

    $other = $agent();
    expect($call($other, 'get_settings', ['section' => 'checkout']))->toContain('No such settings section')
        ->and($call($other, 'update_settings', ['section' => 'checkout', 'values' => ['freeShippingFrom' => 1]]))->toContain('No such settings section');
});
