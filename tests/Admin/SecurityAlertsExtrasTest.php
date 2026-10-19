<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecuritySettings;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    Mail::fake();
    usePanel(fn (Panel $panel) => $panel->brand('Acme')->routes(fn () => Route::get('shop/secret', fn () => abort(403))));
    app(SyncPermissions::class)->handle();
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password'])->assignRole('super-admin');
    Role::findOrCreate('member', 'web')->givePermissionTo([Panel::ACCESS]);
    $this->rina = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password'])->assignRole('member');
});

function headlinesTo(string $email): array
{
    return Mail::sent(SecurityAlert::class)->filter(fn (SecurityAlert $mail) => $mail->hasTo($email))->map(fn (SecurityAlert $mail) => $mail->headline)->values()->all();
}

it('alerts when someone keeps opening pages they may not see', function () {
    config(['sunrice-admin.security.alerts.forbidden' => 3]);
    actingAs($this->rina);
    get('/admin/shop/secret')->assertForbidden();
    get('/admin/users')->assertForbidden();
    expect(headlinesTo('admin@example.com'))->toBe([]);

    get('/admin/settings/security')->assertForbidden();
    get('/admin/shop/secret')->assertForbidden();
    expect(headlinesTo('admin@example.com'))->toBe(['Someone keeps opening pages they may not see']);
    expect(Mail::sent(SecurityAlert::class)->first()->what)->toBe('Rina was refused 3 pages within 10 minutes (the last: /admin/settings/security).');
});

it('counts unpublishing and purging as taking things away', function () {
    config(['sunrice-admin.security.alerts.mass_deletes' => 3]);
    actingAs($this->rina);
    foreach (['unpublished', 'purged', 'trashed'] as $action) {
        app(ActivityLog::class)->record($action, 'entry', null, 'Post');
    }

    expect(headlinesTo('admin@example.com'))->toBe(['Many things deleted in a short time']);
});

it('tells the account\'s owner too when their account is locked', function () {
    config(['sunrice-admin.auth.throttle' => ['attempts' => 50, 'per_ip' => 50, 'per_account' => 2, 'account_lock_seconds' => 900]]);
    foreach (range(1, 4) as $i) {
        post('/admin/login', ['email' => 'rina@example.com', 'password' => 'wrong']);
    }

    expect(headlinesTo('rina@example.com'))->toBe(['Your account was locked'])
        ->and(headlinesTo('admin@example.com'))->toBe(['An account was locked after wrong passwords']);
});

it('sends alerts to the roles and addresses chosen in Settings → Security', function () {
    Role::findOrCreate('ops', 'web');
    $ops = User::query()->create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'secret-password'])->assignRole('ops');
    actingAs($this->admin);
    put('/admin/settings/security', ['twoFactor' => false, 'alertRoles' => ['ops'], 'alertEmails' => "night@example.com\nday@example.com"])->assertSessionHasNoErrors();
    put('/admin/settings/security', ['alertEmails' => 'not an address'])->assertSessionHasErrors('alertEmails');
    expect(app(SecuritySettings::class)->alertRoles)->toBe(['ops']);

    config(['sunrice-admin.security.alerts.mass_deletes' => 1]);
    app(ActivityLog::class)->record('deleted', 'product', null, 'Mug');

    foreach (['admin@example.com', 'ops@example.com', 'night@example.com', 'day@example.com'] as $email) {
        expect(headlinesTo($email))->toBe(['Many things deleted in a short time']);
    }
    expect($ops->email)->toBe('ops@example.com');
});
