<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

beforeEach(fn () => TestPlugin::$calls = []);

/** A user who has exactly these permissions. */
function userWith(string ...$permissions): Authorizable
{
    return new class($permissions) implements Authorizable
    {
        /** @param list<string> $permissions */
        public function __construct(private array $permissions) {}

        public function can($abilities, $arguments = []): bool
        {
            return in_array($abilities, $this->permissions, true);
        }
    };
}

it('has sensible defaults and validates its path', function () {
    $panel = Panel::make();
    expect($panel->getPath())->toBe('admin')
        ->and($panel->getGuard())->toBe('web')
        ->and($panel->path('/backend/cms/')->getPath())->toBe('backend/cms');

    expect(fn () => Panel::make()->path('Admin Area'))->toThrow(InvalidArgumentException::class);
});

it('registers and boots plugins with their dependencies first', function () {
    $panel = Panel::make()
        ->plugin(new TestPlugin('commerce', ['cms']))
        ->plugin(new TestPlugin('cms'))
        ->plugin(new TestPlugin('xendit', ['commerce']));

    $panel->boot();

    expect(TestPlugin::$calls)->toBe([
        'register cms', 'register commerce', 'register xendit',
        'boot cms', 'boot commerce', 'boot xendit',
    ])->and($panel->isBooted())->toBeTrue()
        ->and($panel->hasPlugin('cms'))->toBeTrue();
});

it('refuses missing, duplicate, circular and reserved plugins', function () {
    expect(fn () => Panel::make()->plugin(new TestPlugin('commerce', ['cms']))->boot())
        ->toThrow(PanelMisconfigured::class, 'The plugin "commerce" requires the plugin "cms"');
    expect(fn () => Panel::make()->plugin(new TestPlugin('cms'))->plugin(new TestPlugin('cms')))
        ->toThrow(PanelMisconfigured::class, 'Two plugins have the id "cms"');
    expect(fn () => Panel::make()->plugin(new TestPlugin('a', ['b']))->plugin(new TestPlugin('b', ['a']))->boot())
        ->toThrow(PanelMisconfigured::class, 'a → b → a');
    expect(fn () => Panel::make()->plugin(new TestPlugin('sunrice')))->toThrow(PanelMisconfigured::class);
    expect(fn () => Panel::make()->plugin(new TestPlugin('My Plugin')))->toThrow(PanelMisconfigured::class);
});

it('is locked once booted', function () {
    $panel = Panel::make();
    $panel->boot();

    expect(fn () => $panel->path('other'))->toThrow(PanelMisconfigured::class)
        ->and(fn () => $panel->navigation(NavigationItem::make('late')))->toThrow(PanelMisconfigured::class)
        ->and(fn () => $panel->boot())->toThrow(PanelMisconfigured::class);
});

it('makes plugins prefix their permissions with their id', function () {
    $ok = Panel::make()->userResource(null)->roleResource(null)->mediaLibrary(false)->aiAccess(false)
        ->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->permissions(Permission::make('shop.orders.view', 'View orders', 'Shop'))));
    $ok->boot();
    expect(array_keys($ok->getPermissions()))->toBe([Panel::ACCESS, 'sunrice.activity.view', 'sunrice.activity.prune', 'sunrice.settings.branding', 'sunrice.settings.security', 'sunrice.settings.mail', 'shop.orders.view']);

    $panel = Panel::make()->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->permissions(Permission::make('cms.entries.edit', 'Edit entries'))));
    expect(fn () => $panel->boot())->toThrow(PanelMisconfigured::class, 'start with its id ("shop.…")');

    // The app may not take a plugin's prefix either.
    expect(fn () => Panel::make()->plugin(new TestPlugin('shop'))->permissions(Permission::make('shop.secret', 'Secret')))
        ->toThrow(PanelMisconfigured::class);

    expect(fn () => Permission::make('Orders View', 'x'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Panel::make()->permissions(Permission::make('app.export', 'Export'), Permission::make('app.export', 'Export')))
        ->toThrow(PanelMisconfigured::class, 'registered twice');
});

it('builds the sidebar for a user: groups, order, submenus and permissions', function () {
    $panel = Panel::make()->navigation(
        NavigationItem::make('dashboard')->label('Dashboard')->url('/')->sort(0),
        NavigationItem::make('shop.orders')->label('Orders')->url('/shop/orders/')->group('Shop')->permission('shop.orders.view'),
        NavigationItem::make('shop.reports')->label('Reports')->url('shop/reports')->group('Shop')->permission('shop.reports.view'),
        NavigationItem::make('shop.refunds')->label('Refunds')->url('shop/refunds')->parent('shop.orders'),
        NavigationItem::make('shop.customers')->label('Customers')->url('shop/customers')->group('Shop'),
    );
    $panel->boot();

    $nav = $panel->navigationFor(userWith('shop.orders.view'));

    expect($nav)->toHaveCount(2)
        ->and($nav[0]['group'])->toBeNull()
        ->and($nav[0]['items'][0]['key'])->toBe('dashboard')
        ->and($nav[1]['group'])->toBe('Shop')
        ->and(array_column($nav[1]['items'], 'label'))->toBe(['Customers', 'Orders'])
        ->and($nav[1]['items'][1]['url'])->toBe('shop/orders')
        ->and($nav[1]['items'][1]['children'][0]['key'])->toBe('shop.refunds');

    // No permission for the parent: its submenu goes too.
    expect(array_column($panel->navigationFor(userWith())[1]['items'], 'key'))->toBe(['shop.customers']);
});

it('rejects broken navigation', function () {
    expect(fn () => Panel::make()->navigation(NavigationItem::make('a'), NavigationItem::make('a')))
        ->toThrow(PanelMisconfigured::class, 'Two navigation items have the key "a"');
    expect(fn () => Panel::make()->navigation(NavigationItem::make('child')->parent('missing'))->boot())
        ->toThrow(PanelMisconfigured::class, "doesn't exist");
    expect(fn () => Panel::make()->navigation(
        NavigationItem::make('a'), NavigationItem::make('b')->parent('a'), NavigationItem::make('c')->parent('b'),
    )->boot())->toThrow(PanelMisconfigured::class, 'two levels deep');
    expect(fn () => NavigationItem::make('Has Spaces'))->toThrow(InvalidArgumentException::class);
});

it('collects shared page data without resolving it', function () {
    $resolved = false;
    $panel = Panel::make()->share('shop', function () use (&$resolved) {
        $resolved = true;

        return ['currency' => 'IDR'];
    });

    expect($resolved)->toBeFalse()
        ->and(($panel->getSharedData()['shop'])())->toBe(['currency' => 'IDR'])
        ->and(fn () => $panel->share('shop', fn () => null))->toThrow(PanelMisconfigured::class);
});

it('checks plugin bundles', function () {
    $bundle = fn (string $id, string ...$pages) => PluginBundle::make($id)->module("/{$id}.js")->pages(...$pages);

    expect(fn () => Panel::make()->bundle(PluginBundle::make('shop')->pages('Shop/')))
        ->toThrow(PanelMisconfigured::class, 'has no module');
    expect(fn () => Panel::make()->bundle($bundle('shop', 'Users/')))
        ->toThrow(PanelMisconfigured::class, 'belong to the panel');
    expect(fn () => Panel::make()->bundle($bundle('shop', 'Shop/'))->bundle($bundle('crm', 'Shop/Customers/')))
        ->toThrow(PanelMisconfigured::class, 'both claim');
    expect(fn () => Panel::make()->bundle($bundle('shop', 'Shop/'))->bundle($bundle('shop', 'Store/')))
        ->toThrow(PanelMisconfigured::class, 'Two bundles');
    expect(fn () => PluginBundle::make('shop')->pages('shop'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Panel::make()->share('auth', fn () => null))->toThrow(PanelMisconfigured::class, 'belongs to the panel');
});
