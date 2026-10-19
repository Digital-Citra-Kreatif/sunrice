<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    usePanel();
    app(SyncPermissions::class)->handle();
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password'])->assignRole('super-admin');
    Role::findOrCreate('member', 'web')->givePermissionTo([Panel::ACCESS]);
    $this->rina = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password'])->assignRole('member');
});

/** The security actions about Rina, oldest first. */
function rinaEvents(User $rina): array
{
    return Activity::query()->where('source', 'security')->where('subject_id', (string) $rina->id)
        ->whereNotIn('action', ['logged in', 'logged out'])->orderBy('id')->pluck('action')->all();
}

it('locks an account: its session ends, it can\'t log in, until unlocked', function () {
    actingAs($this->admin);
    get('/admin/users')->assertInertia(fn (Assert $page) => $page->where('rows.data', fn ($rows) => collect($rows)->firstWhere('title', 'Rina')['actions'] === ['sign-out', 'lock']
        && collect($rows)->firstWhere('title', 'Rina')['cells']['locked'] === false
        && collect($rows)->firstWhere('title', 'Admin')['actions'] === ['sign-out']));

    post("/admin/users/{$this->rina->id}/actions/lock")->assertSessionHas('success', 'Account locked.');
    get('/admin/users')->assertInertia(fn (Assert $page) => $page->where('rows.data', fn ($rows) => collect($rows)->firstWhere('title', 'Rina')['actions'] === ['sign-out', 'unlock']
        && collect($rows)->firstWhere('title', 'Rina')['cells']['locked'] === true));
    post("/admin/users/{$this->rina->id}/actions/lock")->assertForbidden(); // already locked

    // Rina's open session ends on her next page.
    actingAs($this->rina);
    get('/admin')->assertRedirect('/admin/login')->assertSessionHas('error', 'This account is locked. Ask an administrator to unlock it.');
    expect(Auth::check())->toBeFalse();
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password'])
        ->assertSessionHasErrors(['email' => 'This account is locked. Ask an administrator to unlock it.']);
    expect(Auth::check())->toBeFalse();

    actingAs($this->admin);
    post("/admin/users/{$this->rina->id}/actions/unlock")->assertSessionHas('success', 'Account unlocked.');
    Auth::logout();
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password'])->assertRedirect('/admin');

    expect(rinaEvents($this->rina))->toBe(['account locked by admin', 'login blocked', 'login blocked', 'account unlocked']);
});

it('signs an account out everywhere, with any session driver', function () {
    $before = $this->rina->getRememberToken();
    actingAs($this->admin);
    post("/admin/users/{$this->rina->id}/actions/sign-out")->assertSessionHas('success', 'Signed out everywhere.');
    expect($this->rina->fresh()->getRememberToken())->not->toBe($before);

    // A session from before ends; one from after stays.
    actingAs($this->rina)->withSession([AccountGuard::LOGIN_AT => now()->subHour()->getTimestamp()]);
    get('/admin')->assertRedirect('/admin/login')->assertSessionHas('error', 'You were signed out. Please log in again.');
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password'])->assertRedirect('/admin');
    get('/admin')->assertOk();

    expect(rinaEvents($this->rina))->toBe(['signed out everywhere']);
});

it('deletes the other sessions at once with the database driver, and keeps your own', function () {
    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->longText('payload');
            $table->integer('last_activity');
        });
    }
    foreach ([['a', $this->admin->id], ['b', $this->admin->id], ['c', $this->rina->id]] as [$id, $user]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user, 'payload' => '', 'last_activity' => time()]);
    }
    config(['session.driver' => 'database']);

    actingAs($this->admin)->withSession([AccountGuard::LOGIN_AT => now()->subHour()->getTimestamp()]);
    post("/admin/users/{$this->admin->id}/actions/sign-out")->assertSessionHas('success', 'Signed out everywhere (2 sessions ended).');

    expect(DB::table('sessions')->pluck('id')->all())->not->toContain('a', 'b')->toContain('c');
    get('/admin')->assertOk(); // still signed in here
});

it('only lets people lock those they may manage, and never themselves', function () {
    $manager = User::query()->create(['name' => 'Manager', 'email' => 'manager@example.com', 'password' => 'x']);
    $manager->givePermissionTo([Panel::ACCESS, 'sunrice.users.view', 'sunrice.users.update']);

    actingAs($manager);
    post("/admin/users/{$this->admin->id}/actions/lock")->assertForbidden();
    post("/admin/users/{$manager->id}/actions/lock")->assertForbidden();
    post("/admin/users/{$this->rina->id}/actions/lock")->assertSessionHas('success');

    expect(app(AccountGuard::class)->isLocked($this->rina))->toBeTrue()
        ->and(app(AccountGuard::class)->isLocked($manager))->toBeFalse();
});

it('unlocks from the console, also after too many wrong passwords', function () {
    config(['sunrice-admin.auth.throttle' => ['attempts' => 50, 'per_ip' => 50, 'per_account' => 2, 'account_lock_seconds' => 900]]);
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'wrong']);
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'wrong']);
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password'])->assertSessionHasErrors('email');

    artisan('sunrice:unlock', ['email' => 'nobody@example.com'])->assertFailed();
    artisan('sunrice:unlock', ['email' => 'rina@example.com'])->expectsOutputToContain('rina@example.com can log in again.')->assertSuccessful();

    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password'])->assertRedirect('/admin');
});
