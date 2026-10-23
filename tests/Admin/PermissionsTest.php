<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission as PanelPermission;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/** Names of the permissions stored for the web guard. */
function storedPermissions(): array
{
    return Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
}

it('creates the roles and permissions tables', function () {
    expect(Schema::hasTable('permissions'))->toBeTrue()
        ->and(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles'))->toBeTrue();
});

it('syncs the registered permissions and leaves the app\'s own alone', function () {
    usePanel(fn (Panel $panel) => $panel->userResource(null)->roleResource(null)->mediaLibrary(false)->aiAccess(false)->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->permissions(
        PanelPermission::make('shop.orders.view', 'View orders'),
    ))));
    Permission::findOrCreate('shop.old-feature', 'web');      // a plugin permission that's gone
    Permission::findOrCreate('sunrice.removed', 'web');       // a panel permission that's gone
    Permission::findOrCreate('reports.export', 'web');        // the app's own

    $result = app(SyncPermissions::class)->handle();

    expect($result['created'])->toBe([Panel::ACCESS, 'sunrice.activity.view', 'sunrice.activity.prune', 'sunrice.settings.branding', 'sunrice.settings.security', 'sunrice.settings.mail', 'sunrice.guide.view', 'sunrice.docs.view', 'shop.orders.view'])
        ->and($result['deleted'])->toEqualCanonicalizing(['shop.old-feature', 'sunrice.removed'])
        ->and(storedPermissions())->toBe(['reports.export', 'shop.orders.view', Panel::ACCESS, 'sunrice.activity.prune', 'sunrice.activity.view', 'sunrice.docs.view', 'sunrice.guide.view', 'sunrice.settings.branding', 'sunrice.settings.mail', 'sunrice.settings.security'])
        ->and(Role::query()->where('name', 'super-admin')->exists())->toBeTrue();

    // Running it again changes nothing.
    expect(app(SyncPermissions::class)->handle())->toBe(['created' => [], 'deleted' => []]);
});

it('syncs after every migrate', function () {
    usePanel();
    Permission::query()->delete();

    artisan('migrate')->assertSuccessful();

    expect(storedPermissions())->toEqualCanonicalizing(array_keys(app(Panel::class)->getPermissions()))
        ->and(storedPermissions())->toContain(Panel::ACCESS, 'sunrice.users.view', 'sunrice.roles.update');
});

it('lets super admins do everything, and others only what their roles allow', function () {
    usePanel();
    app(SyncPermissions::class)->handle();

    $boss = adminUser();
    $boss->assignRole('super-admin');
    $editor = adminUser();
    $editor->assignRole(Role::findOrCreate('editor', 'web')->givePermissionTo(Panel::ACCESS));
    $nobody = adminUser();

    expect($boss->can(Panel::ACCESS))->toBeTrue()
        ->and($boss->can('anything.at.all'))->toBeTrue()
        ->and($editor->can(Panel::ACCESS))->toBeTrue()
        ->and($editor->can('anything.at.all'))->toBeFalse()
        ->and($nobody->can(Panel::ACCESS))->toBeFalse();

    actingAs($editor);
    get('/admin')->assertOk();
    actingAs($nobody);
    get('/admin')->assertForbidden();
});

it('makes a super admin from the console', function () {
    usePanel();

    artisan('sunrice:make-admin', ['--name' => 'Boss', '--email' => 'boss@example.com', '--password' => 'A-long-password-1!'])
        ->assertSuccessful();

    $boss = User::query()->where('email', 'boss@example.com')->firstOrFail();
    expect($boss->hasRole('super-admin'))->toBeTrue()
        ->and(Gate::forUser($boss)->allows(Panel::ACCESS))->toBeTrue();

    // An existing user is promoted, without asking for a password.
    $rina = adminUser();
    artisan('sunrice:make-admin', ['--email' => $rina->email])->assertSuccessful();
    expect($rina->fresh()?->hasRole('super-admin'))->toBeTrue();

    artisan('sunrice:make-admin', ['--name' => 'X', '--email' => 'not-an-email', '--password' => 'A-long-password-1!'])->assertFailed();
    artisan('sunrice:make-admin', ['--name' => 'X', '--email' => 'x@example.com', '--password' => 'short'])->assertFailed();
    expect(User::query()->where('email', 'x@example.com')->exists())->toBeFalse();
});
