<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission as PanelPermission;
use Sunrice\Admin\Users\PermissionRank;
use Sunrice\Admin\Users\UserResource;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

const MANAGES_USERS = [Panel::ACCESS, 'sunrice.users.view', 'sunrice.users.create', 'sunrice.users.update', 'sunrice.users.delete', 'sunrice.roles.view', 'sunrice.roles.create', 'sunrice.roles.update', 'sunrice.roles.delete'];

beforeEach(function () {
    usePanel();
    app(SyncPermissions::class)->handle();
    Permission::findOrCreate('reports.export', 'web'); // the app's own, not registered with the panel

    $this->editorRole = Role::findOrCreate('editor', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.users.view']);
    $this->managerRole = Role::findOrCreate('manager', 'web')->givePermissionTo(MANAGES_USERS);
    $this->bossRole = Role::findOrCreate('boss', 'web')->givePermissionTo([...MANAGES_USERS, 'reports.export']);

    $this->super = person('Super', 'super-admin');
    $this->manager = person('Manager', 'manager');
    $this->editor = person('Editor', 'editor');
    $this->boss = person('Boss', 'boss');
});

function person(string $name, string ...$roles): User
{
    $user = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret-password']);

    return $user->assignRole($roles);
}

/** The value of one field on a form page. */
function formField(Assert $page, string $name): array
{
    $fields = collect($page->toArray()['props']['sections'])->flatMap(fn ($section) => $section['fields']);

    return $fields->firstWhere('name', $name);
}

it('adds the Users and Roles screens with their permissions', function () {
    expect(array_keys(app(Panel::class)->getPermissions()))->toContain('sunrice.users.view', 'sunrice.users.delete', 'sunrice.roles.update')
        ->and(route('sunrice.admin.resources.users.index', absolute: false))->toBe('/admin/users');

    actingAs($this->manager);
    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('navigation.0.group', 'Administration')
        ->where('navigation.0.items.0.label', 'Users')
        ->where('navigation.0.items.1.label', 'Roles'));

    actingAs($this->editor);
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('navigation.0.items', fn ($items) => collect($items)->pluck('label')->all() === ['Users']));
    get('/admin/roles')->assertForbidden();
});

it('can leave the screens out or use subclasses', function () {
    $panel = Panel::make()->userResource(null)->roleResource(null)->mediaLibrary(false)->aiAccess(false);
    $panel->boot();
    expect($panel->getResources())->toBe([])
        ->and(array_keys($panel->getPermissions()))->toBe([Panel::ACCESS, 'sunrice.activity.view']);

    // The panel's own permissions come first, whoever registers theirs earlier.
    $panel = Panel::make()->mediaLibrary(false)->aiAccess(false)->permissions(PanelPermission::make('app.export', 'Export'));
    $panel->boot();
    expect(array_keys($panel->getPermissions()))->toBe([Panel::ACCESS, 'sunrice.activity.view', 'sunrice.users.view', 'sunrice.users.create', 'sunrice.users.update', 'sunrice.users.delete', 'sunrice.roles.view', 'sunrice.roles.create', 'sunrice.roles.update', 'sunrice.roles.delete', 'app.export'])
        ->and(fn () => Panel::make()->permissions(PanelPermission::make('sunrice.users.view', 'Mine'))->boot())->toThrow(PanelMisconfigured::class, 'registered twice');

    expect(fn () => Panel::make()->userResource(Panel::class))->toThrow(PanelMisconfigured::class, 'extend '.UserResource::class);
});

it('creates users with a hashed password and roles, and keeps the password unless a new one is given', function () {
    actingAs($this->super);

    get('/admin/users/create')->assertInertia(fn (Assert $page) => expect(formField($page, 'password'))->toMatchArray(['type' => 'password', 'required' => true])
        ->and(collect(formField($page, 'roles')['options'])->pluck('value')->all())->toBe(['boss', 'editor', 'manager', 'super-admin']));

    post('/admin/users', ['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'a-long-password', 'roles' => ['editor']])->assertRedirect();
    $dewi = User::query()->where('email', 'dewi@example.com')->firstOrFail();
    expect(Hash::check('a-long-password', $dewi->password))->toBeTrue()
        ->and($dewi->getRoleNames()->all())->toBe(['editor']);

    get("/admin/users/{$dewi->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('values.password', null)
        ->where('values.roles', ['editor'])
        ->where('sections.0.fields.2.required', false));

    put("/admin/users/{$dewi->id}", ['name' => 'Dewi S', 'email' => 'dewi@example.com', 'password' => '', 'roles' => ['editor', 'manager']])->assertSessionHasNoErrors();
    $dewi->refresh();
    expect($dewi->name)->toBe('Dewi S')
        ->and(Hash::check('a-long-password', $dewi->password))->toBeTrue()
        ->and($dewi->getRoleNames()->sort()->values()->all())->toBe(['editor', 'manager']);

    put("/admin/users/{$dewi->id}", ['name' => 'Dewi S', 'email' => 'dewi@example.com', 'password' => 'another-password', 'roles' => []]);
    expect(Hash::check('another-password', $dewi->fresh()->password))->toBeTrue()
        ->and($dewi->fresh()->getRoleNames()->all())->toBe([]);

    post('/admin/users', ['name' => 'X', 'email' => 'dewi@example.com', 'password' => '', 'roles' => ['nope']])
        ->assertSessionHasErrors(['email', 'password', 'roles.0']);
});

it('lists users with their roles, filtered by role, without a query per row', function () {
    actingAs($this->super);

    get('/admin/users?filters[role]=manager')->assertInertia(fn (Assert $page) => $page
        ->where('rows.total', 1)
        ->where('rows.data.0.cells', fn ($cells) => $cells['roles.name'] === 'manager' && $cells['email'] === 'manager@example.com'));
    get('/admin/users?filters[role]=nobody')->assertInertia(fn (Assert $page) => $page->where('rows.total', 4));

    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        get('/admin/users')->assertOk();

        return count(DB::getQueryLog());
    };
    $few = $count();
    foreach (range(1, 8) as $i) {
        person("Extra {$i}", 'editor', 'manager');
    }
    expect($count())->toBe($few);
});

it('lets users manage only users whose permissions they have', function () {
    actingAs($this->manager);

    get('/admin/users')->assertInertia(fn (Assert $page) => $page->where('rows.data', fn ($rows) => collect($rows)->mapWithKeys(fn ($row) => [$row['title'] => $row['can']])->all() === [
        'Boss' => ['update' => false, 'delete' => false],
        'Editor' => ['update' => true, 'delete' => true],
        'Manager' => ['update' => true, 'delete' => false],
        'Super' => ['update' => false, 'delete' => false],
    ]));

    foreach ([$this->super, $this->boss] as $other) {
        get("/admin/users/{$other->id}/edit")->assertForbidden();
        put("/admin/users/{$other->id}", ['name' => 'x', 'email' => 'x@example.com'])->assertForbidden();
        delete("/admin/users/{$other->id}")->assertForbidden();
    }
    post('/admin/users/bulk/delete', ['ids' => [$this->editor->id, $this->boss->id]])->assertForbidden();
    expect(User::query()->count())->toBe(4);

    // Only roles within their own permissions are offered or accepted.
    get("/admin/users/{$this->editor->id}/edit")->assertInertia(fn (Assert $page) => expect(collect(formField($page, 'roles')['options'])->pluck('value')->all())->toBe(['editor', 'manager']));
    put("/admin/users/{$this->editor->id}", ['name' => 'Editor', 'email' => 'editor@example.com', 'roles' => ['boss']])->assertSessionHasErrors('roles.0');
    put("/admin/users/{$this->editor->id}", ['name' => 'Editor', 'email' => 'editor@example.com', 'roles' => ['super-admin']])->assertSessionHasErrors('roles.0');
    post('/admin/users', ['name' => 'New', 'email' => 'new@example.com', 'password' => 'a-long-password', 'roles' => ['super-admin']])->assertSessionHasErrors('roles.0');
    put("/admin/users/{$this->editor->id}", ['name' => 'Editor', 'email' => 'editor@example.com', 'roles' => ['manager']])->assertSessionHasNoErrors();
    expect($this->editor->fresh()->getRoleNames()->all())->toBe(['manager']);

    delete("/admin/users/{$this->editor->id}")->assertRedirect();
    expect(User::query()->whereKey($this->editor->id)->exists())->toBeFalse();
});

it('never lets users delete themselves or change their own roles', function () {
    actingAs($this->super);
    delete("/admin/users/{$this->super->id}")->assertForbidden();

    get("/admin/users/{$this->super->id}/edit")->assertInertia(fn (Assert $page) => expect(formField($page, 'roles')['disabled'])->toBeTrue());
    put("/admin/users/{$this->super->id}", ['name' => 'Still super', 'email' => 'super@example.com', 'roles' => []])->assertSessionHasNoErrors();

    expect($this->super->fresh()->name)->toBe('Still super')
        ->and($this->super->fresh()->getRoleNames()->all())->toBe(['super-admin']);
});

it('edits roles and their permissions, within the editor\'s own', function () {
    actingAs($this->manager);

    get('/admin/roles')->assertInertia(fn (Assert $page) => $page->where('rows.data', fn ($rows) => collect($rows)->mapWithKeys(fn ($row) => [$row['title'] => [$row['can']['update'], $row['cells']['permissions_count']]])->all() === [
        'boss' => [false, 10],
        'editor' => [true, 2],
        'manager' => [true, 9],
        'super-admin' => [false, 0],
    ]));

    get('/admin/roles/create')->assertInertia(fn (Assert $page) => expect(collect(formField($page, 'permissions')['options'])->pluck('value')->all())->toEqualCanonicalizing(MANAGES_USERS)
        ->and(collect(formField($page, 'permissions')['options'])->firstWhere('value', 'sunrice.users.view'))->toBe(['value' => 'sunrice.users.view', 'label' => 'View Users', 'group' => 'Users']));

    post('/admin/roles', ['name' => 'helper', 'permissions' => [Panel::ACCESS, 'sunrice.users.view']])->assertRedirect();
    $helper = Role::findByName('helper', 'web');
    expect($helper->permissions->pluck('name')->sort()->values()->all())->toBe([Panel::ACCESS, 'sunrice.users.view']);

    // Permissions they don't have themselves are refused, the app's own too.
    put("/admin/roles/{$helper->id}", ['name' => 'helper', 'permissions' => ['reports.export']])->assertSessionHasErrors('permissions.0');
    put("/admin/roles/{$helper->id}", ['name' => 'manager', 'permissions' => []])->assertSessionHasErrors('name');

    foreach ([$this->bossRole, Role::findByName('super-admin', 'web')] as $role) {
        get("/admin/roles/{$role->id}/edit")->assertForbidden();
        delete("/admin/roles/{$role->id}")->assertForbidden();
    }
});

it('keeps permissions the form doesn\'t offer, and never edits the super admin role', function () {
    actingAs($this->super);

    put("/admin/roles/{$this->bossRole->id}", ['name' => 'boss', 'permissions' => [Panel::ACCESS]])->assertSessionHasNoErrors();
    expect($this->bossRole->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['reports.export', Panel::ACCESS]);

    $super = Role::findByName('super-admin', 'web');
    get("/admin/roles/{$super->id}/edit")->assertForbidden();
    delete("/admin/roles/{$super->id}")->assertForbidden();

    delete("/admin/roles/{$this->editorRole->id}")->assertRedirect();
    expect($this->editor->fresh()->getRoleNames()->all())->toBe([]);
});

it('ranks users by their permissions', function () {
    $rank = app(PermissionRank::class);

    expect($rank->canManageUser($this->super, $this->boss))->toBeTrue()
        ->and($rank->canManageUser($this->boss, $this->manager))->toBeTrue()
        ->and($rank->canManageUser($this->manager, $this->boss))->toBeFalse()
        ->and($rank->canManageUser($this->boss, $this->super))->toBeFalse()
        ->and($rank->canManageRole($this->boss, $this->managerRole))->toBeTrue()
        ->and($rank->canManageRole($this->manager, $this->bossRole))->toBeFalse()
        ->and($rank->canGrantPermissions($this->manager, ['reports.export']))->toBeFalse()
        ->and($rank->permissionsOf($this->editor))->toEqualCanonicalizing([Panel::ACCESS, 'sunrice.users.view']);
});
