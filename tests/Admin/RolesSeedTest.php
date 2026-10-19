<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SeedRoles;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;

use function Pest\Laravel\artisan;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel
        ->permissions(Permission::make('shop.orders.view', 'View orders', 'Shop'), Permission::make('shop.orders.refund', 'Refund orders', 'Shop'))
        ->defaultRole('cashier', [Panel::ACCESS, 'shop.orders.view'])
        ->defaultRole('cashier', ['shop.orders.*']));
});

it('creates the default roles with their permissions, adding only', function () {
    expect(app(Panel::class)->getDefaultRoles())->toBe([
        'administrator' => ['*'],
        'cashier' => [Panel::ACCESS, 'shop.orders.view', 'shop.orders.*'],
    ]);

    expect(array_keys(app(SeedRoles::class)->handle()))->toBe(['administrator', 'cashier']);
    $cashier = Role::findByName('cashier', 'web');
    expect($cashier->permissions->pluck('name')->sort()->values()->all())->toBe(['shop.orders.refund', 'shop.orders.view', 'sunrice.access'])
        ->and(Role::findByName('administrator', 'web')->permissions->pluck('name'))->toContain('sunrice.settings.security', 'shop.orders.refund');

    // Run again, it gives what's missing.
    $cashier->revokePermissionTo('shop.orders.refund');
    artisan('sunrice:roles')->expectsOutputToContain('administrator')->assertSuccessful();
    expect(Role::findByName('cashier', 'web')->permissions->pluck('name'))->toContain('shop.orders.refund');
});

it('wants lowercase role names', function () {
    expect(fn () => Panel::make()->defaultRole('Shop Manager', []))->toThrow(PanelMisconfigured::class, 'lowercase');
});

it('installs: migrations, assets, permissions, roles, and points to make-admin', function () {
    artisan('sunrice:install', ['--roles' => true, '--no-user' => true])
        ->expectsOutputToContain('sunrice:make-admin')
        ->assertSuccessful();

    expect(Role::query()->pluck('name')->all())->toContain('administrator', 'cashier', 'super-admin');
});

it('resolves permissions that depend on data, when asked and after a flush', function () {
    $shops = ['north'];
    $panel = Panel::make()->permissionsUsing(function () use (&$shops): array {
        return array_map(fn (string $shop) => Permission::make("shop.{$shop}.orders", "Orders of {$shop}", 'Shops'), $shops);
    });
    $panel->boot();

    expect(array_keys($panel->getPermissions()))->toContain('shop.north.orders');
    $shops[] = 'south';
    expect(array_keys($panel->getPermissions()))->not->toContain('shop.south.orders');
    $panel->flushPermissions();
    expect(array_keys($panel->getPermissions()))->toContain('shop.north.orders', 'shop.south.orders');
});
