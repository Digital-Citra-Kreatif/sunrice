<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Actions\Users\DeleteUser;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Auth\LoginThrottle;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Locks\KeptEdit;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission as PanelPermission;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;
use Sunrice\Admin\Users\PermissionRank;
use Sunrice\Admin\Users\UserResource;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;
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
        ->and(array_keys($panel->getPermissions()))->toBe([Panel::ACCESS, 'sunrice.activity.view', 'sunrice.settings.security']);

    // The panel's own permissions come first, whoever registers theirs earlier.
    $panel = Panel::make()->mediaLibrary(false)->aiAccess(false)->permissions(PanelPermission::make('app.export', 'Export'));
    $panel->boot();
    expect(array_keys($panel->getPermissions()))->toBe([Panel::ACCESS, 'sunrice.activity.view', 'sunrice.settings.security', 'sunrice.users.view', 'sunrice.users.create', 'sunrice.users.update', 'sunrice.users.delete', 'sunrice.roles.view', 'sunrice.roles.create', 'sunrice.roles.update', 'sunrice.roles.delete', 'app.export'])
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

it('lets users manage only users strictly below them, and edit their own account', function () {
    // Another manager with the same access: side by side, not below.
    $peer = person('Peer', 'manager');
    actingAs($this->manager);

    get('/admin/users')->assertInertia(fn (Assert $page) => $page->where('rows.data', fn ($rows) => collect($rows)->mapWithKeys(fn ($row) => [$row['title'] => $row['can']])->all() === [
        'Boss' => ['update' => false, 'delete' => false],
        'Editor' => ['update' => true, 'delete' => true],
        'Manager' => ['update' => true, 'delete' => false],
        'Peer' => ['update' => false, 'delete' => false],
        'Super' => ['update' => false, 'delete' => false],
    ]));

    foreach ([$this->super, $this->boss, $peer] as $other) {
        get("/admin/users/{$other->id}/edit")->assertForbidden();
        put("/admin/users/{$other->id}", ['name' => 'x', 'email' => 'x@example.com'])->assertForbidden();
        delete("/admin/users/{$other->id}")->assertForbidden();
    }
    post('/admin/users/bulk/delete', ['ids' => [$this->editor->id, $this->boss->id]])->assertForbidden();
    expect(User::query()->count())->toBe(5);

    // Only roles strictly below their own access are offered or accepted: not their own role.
    get("/admin/users/{$this->editor->id}/edit")->assertInertia(fn (Assert $page) => expect(collect(formField($page, 'roles')['options'])->pluck('value')->all())->toBe(['editor']));
    foreach (['boss', 'manager', 'super-admin'] as $role) {
        put("/admin/users/{$this->editor->id}", ['name' => 'Editor', 'email' => 'editor@example.com', 'roles' => [$role]])->assertSessionHasErrors('roles.0');
    }
    post('/admin/users', ['name' => 'New', 'email' => 'new@example.com', 'password' => 'a-long-password', 'roles' => ['super-admin']])->assertSessionHasErrors('roles.0');

    // Roles each below them, but together as much as they have: refused too.
    Role::findOrCreate('people', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.users.view', 'sunrice.users.create', 'sunrice.users.update', 'sunrice.users.delete']);
    Role::findOrCreate('roles', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.roles.view', 'sunrice.roles.create', 'sunrice.roles.update', 'sunrice.roles.delete']);
    put("/admin/users/{$this->editor->id}", ['name' => 'Editor', 'email' => 'editor@example.com', 'roles' => ['people', 'roles']])->assertSessionHasErrors('roles');
    expect($this->editor->fresh()->getRoleNames()->all())->toBe(['editor']);
    put("/admin/users/{$this->editor->id}", ['name' => 'Editor', 'email' => 'editor@example.com', 'roles' => ['people']])->assertSessionHasNoErrors();
    expect($this->editor->fresh()->getRoleNames()->all())->toBe(['people']);

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
        'manager' => [false, 9],
        'super-admin' => [false, 0],
    ]));

    get('/admin/roles/create')->assertInertia(fn (Assert $page) => expect(collect(formField($page, 'permissions')['options'])->pluck('value')->all())->toEqualCanonicalizing(MANAGES_USERS)
        ->and(collect(formField($page, 'permissions')['options'])->firstWhere('value', 'sunrice.users.view'))->toBe(['value' => 'sunrice.users.view', 'label' => 'View Users', 'group' => 'Users']));

    post('/admin/roles', ['name' => 'helper', 'permissions' => [Panel::ACCESS, 'sunrice.users.view']])->assertRedirect();
    $helper = Role::findByName('helper', 'web');
    expect($helper->permissions->pluck('name')->sort()->values()->all())->toBe([Panel::ACCESS, 'sunrice.users.view']);

    // Permissions they don't have themselves are refused, the app's own too; so is a role as powerful as they are.
    put("/admin/roles/{$helper->id}", ['name' => 'helper', 'permissions' => ['reports.export']])->assertSessionHasErrors('permissions.0');
    put("/admin/roles/{$helper->id}", ['name' => 'helper', 'permissions' => MANAGES_USERS])->assertSessionHasErrors('permissions');
    post('/admin/roles', ['name' => 'twin', 'permissions' => MANAGES_USERS])->assertSessionHasErrors('permissions');
    expect($helper->fresh()->permissions->count())->toBe(2)
        ->and(Role::query()->where('name', 'twin')->exists())->toBeFalse();
    put("/admin/roles/{$helper->id}", ['name' => 'manager', 'permissions' => []])->assertSessionHasErrors('name');

    foreach ([$this->bossRole, $this->managerRole, Role::findByName('super-admin', 'web')] as $role) {
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

    // A role still given to someone can't be deleted, one by one or in bulk.
    delete("/admin/roles/{$this->editorRole->id}")->assertRedirect()->assertSessionHas('error', 'The role "editor" is still given to 1 user. Take it away from them first.');
    post('/admin/roles/bulk/delete', ['ids' => [$this->editorRole->id]])->assertSessionHas('error');
    expect($this->editorRole->fresh())->not->toBeNull()
        ->and($this->editor->fresh()->getRoleNames()->all())->toBe(['editor']);

    $this->editor->syncRoles([]);
    delete("/admin/roles/{$this->editorRole->id}")->assertRedirect()->assertSessionHas('success');
    expect($this->editorRole->fresh())->toBeNull();
});

it('ranks users by their permissions, strictly', function () {
    $rank = app(PermissionRank::class);
    $peer = person('Peer', 'manager');

    expect($rank->canManageUser($this->super, $this->boss))->toBeTrue()
        ->and($rank->canManageUser($this->boss, $this->manager))->toBeTrue()
        ->and($rank->canManageUser($this->manager, $this->boss))->toBeFalse()
        ->and($rank->canManageUser($this->manager, $peer))->toBeFalse()
        ->and($rank->canManageUser($this->boss, $this->super))->toBeFalse()
        ->and($rank->canManageRole($this->boss, $this->managerRole))->toBeTrue()
        ->and($rank->canManageRole($this->manager, $this->managerRole))->toBeFalse()
        ->and($rank->canManageRole($this->manager, $this->bossRole))->toBeFalse()
        ->and($rank->canGrantPermissions($this->manager, ['reports.export']))->toBeFalse()
        ->and($rank->canGrantPermissions($this->manager, MANAGES_USERS))->toBeTrue()
        ->and($rank->isStrictlyBelow($this->manager, MANAGES_USERS))->toBeFalse()
        ->and($rank->isStrictlyBelow($this->manager, [Panel::ACCESS]))->toBeTrue()
        ->and($rank->isStrictlyBelow($this->super, [...MANAGES_USERS, 'reports.export']))->toBeTrue()
        ->and($rank->permissionsOf($this->editor))->toEqualCanonicalizing([Panel::ACCESS, 'sunrice.users.view']);
});

it('deletes a user and gives their content to someone else, keeps it or deletes it', function () {
    actingAs($this->super);
    $uploads = fn (User $user) => Media::query()->where('uploaded_by', (string) $user->id)->count();
    $photo = fn (User $user) => Media::query()->create(['disk' => 'public', 'path' => uniqid().'.jpg', 'filename' => 'x.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'sizes' => [], 'version' => 1, 'uploaded_by' => (string) $user->id]);
    $photo($this->editor);
    $photo($this->editor);
    $photo($this->manager);
    KeptEdit::query()->create(['type' => 'shop.product', 'item_id' => '1', 'locale' => '', 'user_id' => (string) $this->editor->id, 'content' => []]);

    // The row's Delete replaces the built-in one and asks what to do.
    get('/admin/users')->assertInertia(fn (Assert $page) => $page
        ->where('rowActions', fn ($actions) => collect($actions)->firstWhere('key', 'delete')['fields'][0]['name'] === 'content'
            && collect($actions)->firstWhere('key', 'delete')['fields'][0]['default'] === 'keep'
            && collect(collect($actions)->firstWhere('key', 'delete')['fields'][1]['options'])->pluck('label')->contains('Boss'))
        ->where('rows.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['title'] === 'Super' ? ! in_array('delete', $row['actions'], true) : in_array('delete', $row['actions'], true))));

    post("/admin/users/{$this->editor->id}/actions/delete", ['content' => 'nope'])->assertSessionHasErrors('content');
    post("/admin/users/{$this->editor->id}/actions/delete", ['content' => 'reassign'])->assertSessionHas('error', 'Choose another user to give their content to.');
    post("/admin/users/{$this->editor->id}/actions/delete", ['content' => 'reassign', 'heir' => $this->editor->id])->assertSessionHas('error');
    expect($this->editor->fresh())->not->toBeNull();

    post("/admin/users/{$this->editor->id}/actions/delete", ['content' => 'reassign', 'heir' => $this->boss->id])
        ->assertSessionHas('success', 'Editor deleted. Their 2 uploads went to Boss.');
    expect($this->editor->fresh())->toBeNull()
        ->and($uploads($this->boss))->toBe(2)
        ->and(KeptEdit::query()->count())->toBe(0)
        ->and(Activity::query()->where('action', 'deleted')->where('subject_type', 'users')->count())->toBe(1);

    post("/admin/users/{$this->manager->id}/actions/delete", ['content' => 'delete'])->assertSessionHas('success', 'Manager deleted. Their 1 upload got deleted, apart from uploads, which stay without an uploader.');
    expect(Media::query()->whereNull('uploaded_by')->count())->toBe(1);

    // The edit page's and bulk delete keep the content without an owner.
    $photo($this->boss);
    delete("/admin/users/{$this->boss->id}")->assertRedirect('/admin/users');
    expect($this->boss->fresh())->toBeNull()
        ->and(Media::query()->whereNull('uploaded_by')->count())->toBe(4);
    post("/admin/users/{$this->super->id}/actions/delete", ['content' => 'keep'])->assertForbidden();

    expect(fn () => app(DeleteUser::class)->handle($this->super, 'shred'))->toThrow(InvalidArgumentException::class);
});

it('lets plugins register what users own, under their own id', function () {
    $panel = Panel::make()->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->userContent('shop.orders', 'orders', fn () => 3, fn () => null)));
    $panel->boot();
    expect(array_keys($panel->getUserContent()))->toBe(['media', 'shop.orders'])
        ->and(app(DeleteUser::class)->labels())->toBe(['uploads']);

    expect(fn () => Panel::make()->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->userContent('orders', 'orders', fn () => 0, fn () => null)))->boot())
        ->toThrow(PanelMisconfigured::class, 'start with its id');
    expect(fn () => Panel::make()->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->userContent('shop.a', 'a', fn () => 0, fn () => null)->userContent('shop.a', 'a', fn () => 0, fn () => null)))->boot())
        ->toThrow(PanelMisconfigured::class, 'twice');
});

it('shows a Security card on the user\'s page, and locks with a reason', function () {
    actingAs($this->super);
    app(SecurityLog::class)->record('logged in', $this->editor);
    Activity::query()->where('action', 'logged in')->update(['ip_address' => '203.0.113.9']);
    AiToken::query()->create(['user_id' => (string) $this->editor->id, 'name' => 'Agent', 'token_hash' => hash('sha256', 'x')]);

    post("/admin/users/{$this->editor->id}/actions/lock", ['reason' => str_repeat('x', 251)])->assertSessionHasErrors('reason');
    post("/admin/users/{$this->editor->id}/actions/lock", ['reason' => 'Password leaked'])->assertSessionHas('success', 'Account locked.');
    expect(app(AccountGuard::class)->state($this->editor))
        ->locked_reason->toBe('Password leaked')
        ->locked_by->toBe('Super');

    get("/admin/users/{$this->editor->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->component('Resources/Form', false)
        ->has('aside', 1)
        ->where('aside.0.title', 'Security')
        ->where('aside.0.blocks.0.type', 'notice')
        ->where('aside.0.blocks.0.tone', 'danger')
        ->where('aside.0.blocks.0.text', fn (string $text) => str_starts_with($text, 'Locked ') && str_ends_with($text, ' by Super'))
        ->where('aside.0.blocks.0.detail', fn (string $detail) => str_starts_with($detail, 'Password leaked · '))
        ->where('aside.0.blocks.1.rows', fn ($rows) => collect($rows)->pluck('value', 'label')->only(['Last login', 'AI access tokens'])->map(fn ($v) => str_contains($v, ' from ') ? 'from' : $v)->all() === ['Last login' => 'from', 'AI access tokens' => '0']
            && collect($rows)->firstWhere('label', 'Signed out everywhere')['type'] === 'datetime')
        ->where('aside.0.blocks.2.heading', 'Latest security events')
        ->where('aside.0.blocks.2.items', fn ($items) => collect($items)->firstWhere('label', 'Account locked by admin')['meta'] === 'Password leaked · by Super · IP 127.0.0.1')
        ->where('aside.0.buttons.0.url', "/admin/users/{$this->editor->id}/actions/sign-out")
        ->where('aside.0.links.0.url', "/admin/activity?type=account&subject={$this->editor->id}"));

    // Creating a user has no card; a plain resource has none.
    get('/admin/users/create')->assertInertia(fn (Assert $page) => $page->where('aside', []));
});

it('shows the wrong-password lock on the Security card', function () {
    config(['sunrice-admin.auth.throttle.per_account' => 2]);
    $throttle = app(LoginThrottle::class);
    $throttle->failed('editor@example.com', '1.1.1.1');
    $throttle->failed('editor@example.com', '1.1.1.2');

    actingAs($this->super);
    get("/admin/users/{$this->editor->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('aside.0.blocks.0.text', 'Locked after too many wrong passwords')
        ->where('aside.0.blocks.0.tone', 'warning')
        ->where('aside.0.blocks.1.rows.0', ['label' => 'Last login', 'value' => 'No login recorded', 'type' => 'text']));
});
