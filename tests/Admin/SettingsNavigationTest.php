<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\MailSettings;
use Sunrice\Tests\Admin\Fixtures\ShopSettings;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// NavigationItem::inSettings(): pages of a plugin's own in the Settings menu.
beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->settings(MailSettings::class)
        ->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->settings(ShopSettings::class)->navigation(
            NavigationItem::make('shop.taxes')->label('Taxes')->url('shop/taxes')->inSettings('outbox')->permission('shop.settings.shop'),
            NavigationItem::make('shop.rates')->label('Rates')->url('shop/rates')->inSettings('outbox')->permission('shop.settings.shop'),
            NavigationItem::make('shop.misc')->label('Misc')->url('shop/misc')->inSettings(),
        ))));
});

it('puts pages of their own among the settings sections, after the one named', function () {
    $user = adminUser(Panel::ACCESS, 'shop.settings.shop', 'settings.outbox');

    expect(app(Panel::class)->settingsNavigation($user))->toBe([
        ['key' => 'outbox', 'label' => 'Outbox', 'url' => 'settings/outbox'],
        ['key' => 'shop.rates', 'label' => 'Rates', 'url' => 'shop/rates'],
        ['key' => 'shop.taxes', 'label' => 'Taxes', 'url' => 'shop/taxes'],
        ['key' => 'shop', 'label' => 'Shop', 'url' => 'settings/shop'],
        ['key' => 'shop.misc', 'label' => 'Misc', 'url' => 'shop/misc'],
    ]);
    // Not in the sidebar: Settings is, current on them too.
    $items = collect(app(Panel::class)->navigationFor($user))->flatMap(fn (array $group) => $group['items']);
    expect($items->pluck('key')->all())->toBe(['settings'])
        ->and($items->first()['matches'])->toBe(['shop/misc', 'shop/rates', 'shop/taxes']);
    actingAs($user);
    get('/admin/settings/shop')->assertInertia(fn (Assert $page) => $page->where('sections.1.url', 'shop/rates'));

    // A page among them alone still gives Settings, which opens it.
    actingAs(adminUser(Panel::ACCESS));
    expect(array_column(app(Panel::class)->settingsNavigation(auth()->user()), 'key'))->toBe(['shop.misc']);
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('navigation.0.items.0.key', 'settings'));
    get('/admin/settings')->assertRedirect('/admin/shop/misc');
});
