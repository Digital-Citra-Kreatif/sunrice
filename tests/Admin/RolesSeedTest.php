<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SeedRoles;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;

use function Pest\Laravel\artisan;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel
        ->permissions(Permission::make('shop.orders.view', 'View orders', 'Shop'), Permission::make('shop.orders.refund', 'Refund orders', 'Shop'))
        // More permissions a test adds later, as a new collection would.
        ->permissionsUsing(fn (): array => array_map(fn (string $name) => Permission::make($name, $name, 'Shop'), $GLOBALS['laterPermissions'] ?? []))
        ->defaultRole('cashier', [Panel::ACCESS, 'shop.orders.view'])
        ->defaultRole('cashier', ['shop.orders.*']));
    $GLOBALS['laterPermissions'] = [];
});

it('creates the default roles with their permissions, adding only', function () {
    expect(app(Panel::class)->getDefaultRoles())->toBe([
        'administrator' => ['*', '!sunrice.ai.run-commands', '!sunrice.ai.error-log'],
        'cashier' => [Panel::ACCESS, 'shop.orders.view', 'shop.orders.*', 'sunrice.guide.view'],
    ]);

    expect(array_keys(app(SeedRoles::class)->handle()))->toBe(['administrator', 'cashier']);
    $cashier = Role::findByName('cashier', 'web');
    expect($cashier->permissions->pluck('name')->sort()->values()->all())->toBe(['shop.orders.refund', 'shop.orders.view', 'sunrice.access', 'sunrice.guide.view'])
        ->and(Role::findByName('administrator', 'web')->permissions->pluck('name'))->toContain('sunrice.settings.security', 'shop.orders.refund', 'sunrice.ai.connect')
        // Letting agents run commands or read the error log stays with super admins, unless given on purpose.
        ->not->toContain('sunrice.ai.run-commands', 'sunrice.ai.error-log');

    // Run again: a permission taken away in the panel stays away.
    $cashier->revokePermissionTo('shop.orders.refund');
    artisan('sunrice:roles')->expectsOutputToContain('administrator')->assertSuccessful();
    expect(Role::findByName('cashier', 'web')->permissions->pluck('name'))->not->toContain('shop.orders.refund');
});

it('gives default roles new permissions on a later run, but never again one taken away', function () {
    app(SeedRoles::class)->handle();
    Role::findByName('cashier', 'web')->revokePermissionTo('shop.orders.refund');

    $GLOBALS['laterPermissions'] = ['shop.orders.ship'];
    app(Panel::class)->flushPermissions();
    expect(app(SeedRoles::class)->handle()['cashier'])->toBe(['shop.orders.ship'])
        ->and(Role::findByName('cashier', 'web')->permissions->pluck('name')->sort()->values()->all())->toBe(['shop.orders.ship', 'shop.orders.view', 'sunrice.access', 'sunrice.guide.view']);
});

it('gives a role seeded before this was remembered what it is missing, once', function () {
    app(SyncPermissions::class)->handle();
    Role::findOrCreate('cashier', 'web')->givePermissionTo(Panel::ACCESS);
    expect(app(SeedRoles::class)->handle()['cashier'])->toBe(['sunrice.guide.view', 'shop.orders.view', 'shop.orders.refund'])
        ->and(app(SeedRoles::class)->handle()['cashier'])->toBe([]);
});

it('reports what default roles have beyond their defaults, and takes nothing away (SEC-10)', function () {
    app(SeedRoles::class)->handle();
    expect(app(SeedRoles::class)->extras())->toBe([]);
    // Left over from an older default, or given on purpose.
    Role::findByName('administrator', 'web')->givePermissionTo('sunrice.ai.run-commands');
    Role::findByName('cashier', 'web')->givePermissionTo('sunrice.settings.security');

    expect(app(SeedRoles::class)->extras())->toBe(['administrator' => ['sunrice.ai.run-commands'], 'cashier' => ['sunrice.settings.security']]);
    artisan('sunrice:roles')
        ->expectsOutputToContain('permissions their default no longer gives')
        ->expectsOutputToContain('sunrice.ai.run-commands')
        ->assertSuccessful();
    expect(Role::findByName('administrator', 'web')->permissions->pluck('name'))->toContain('sunrice.ai.run-commands')
        ->and(Role::findByName('cashier', 'web')->permissions->pluck('name'))->toContain('sunrice.settings.security');
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
