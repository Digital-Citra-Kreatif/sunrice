<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecurityAlerts;
use Sunrice\Admin\Security\SecurityLog;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    Mail::fake();
    Storage::fake('public');
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));
    app(SyncPermissions::class)->handle();
    config(['sunrice-admin.security.alerts.emails' => ['security@example.com']]);
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password'])->assignRole('super-admin');
    Role::findOrCreate('member', 'web')->givePermissionTo([Panel::ACCESS]);
    $this->rina = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password'])->assignRole('member');
});

/** The headlines of the alerts sent, in order. */
function alertsSent(): array
{
    return Mail::sent(SecurityAlert::class)->map(fn (SecurityAlert $mail) => $mail->headline)->unique()->values()->all();
}

it('emails super admins and the listed addresses when an account is locked, once per cooldown', function () {
    config(['sunrice-admin.auth.throttle' => ['attempts' => 50, 'per_ip' => 50, 'per_account' => 2, 'account_lock_seconds' => 900]]);
    foreach (range(1, 4) as $i) {
        post('/admin/login', ['email' => 'rina@example.com', 'password' => 'wrong']);
    }

    expect(alertsSent())->toBe(['An account was locked after wrong passwords']);
    Mail::assertSent(SecurityAlert::class, 2);
    Mail::assertSent(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->hasTo('admin@example.com'));
    Mail::assertSent(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->hasTo('security@example.com'));
    expect(Activity::query()->where('action', 'alert sent')->sole()->properties['note'])->toBe('account-locked to 2 people');

    $mail = Mail::sent(SecurityAlert::class)->first();
    expect($mail->envelope()->subject)->toBe('[Acme] Security: An account was locked after wrong passwords')
        ->and($mail->render())->toContain('Rina')->toContain('What to do:')->toContain('/admin/activity?source=security');
});

it('alerts on one address trying many accounts', function () {
    config(['sunrice-admin.auth.throttle' => ['attempts' => 50, 'per_ip' => 3, 'per_account' => 50]]);
    foreach (['a', 'b', 'c'] as $name) {
        post('/admin/login', ['email' => "{$name}@example.com", 'password' => 'wrong']);
    }

    expect(alertsSent())->toBe(['One address is trying many accounts']);
});

it('alerts when a powerful account signs in from a new address, or anyone after a long absence', function () {
    $login = function (User $user, string $ip) {
        auth()->logout();
        test()->withServerVariables(['REMOTE_ADDR' => $ip])->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password'])->assertRedirect('/admin');
    };

    $login($this->admin, '10.0.0.1');
    $login($this->admin, '10.0.0.1');
    $login($this->rina, '10.0.0.1');
    $login($this->rina, '10.0.0.2'); // not powerful: no alert
    expect(alertsSent())->toBe([]);

    $login($this->admin, '10.0.0.9');
    expect(alertsSent())->toBe(['A powerful account signed in from a new address']);

    Activity::query()->where('subject_id', (string) $this->rina->id)->update(['created_at' => now()->subDays(120)]);
    $login($this->rina, '10.0.0.2');
    expect(alertsSent())->toContain('An account signed in after a long time');
});

it('alerts when someone gains power over users or roles, not for other roles', function () {
    Role::findOrCreate('editor', 'web');
    actingAs($this->admin);
    put("/admin/users/{$this->rina->id}", ['name' => 'Rina', 'email' => 'rina@example.com', 'roles' => ['member', 'editor']]);
    expect(alertsSent())->toBe([]);

    put("/admin/users/{$this->rina->id}", ['name' => 'Rina', 'email' => 'rina@example.com', 'roles' => ['member', 'super-admin']]);
    expect(alertsSent())->toBe(['Someone was given power over users or roles']);

    $member = Role::findByName('member', 'web');
    put("/admin/roles/{$member->id}", ['name' => 'member', 'permissions' => [Panel::ACCESS, 'sunrice.roles.update']]);
    expect(alertsSent())->toContain('A role was given power over users or roles');
});

it('alerts on dangerous uploads, and bursts of exports or deletes', function () {
    config(['sunrice-admin.security.alerts.exports' => 2, 'sunrice-admin.security.alerts.mass_deletes' => 3]);
    actingAs($this->admin);
    post('/admin/media', ['file' => UploadedFile::fake()->createWithContent('shell.php', '<?php')]);

    $security = app(SecurityLog::class);
    $security->record('exported', $this->admin, null, ['note' => 'Users (CSV)']);
    $security->record('exported', $this->admin, null, ['note' => 'Users (CSV)']);

    foreach (range(1, 3) as $i) {
        app(ActivityLog::class)->record('deleted', 'products', $i, "Product {$i}");
    }

    expect(alertsSent())->toBe(['A dangerous upload was blocked', 'Many exports in a short time', 'Many things deleted in a short time']);
});

it('sends nothing when alerts are off, and includes users with the listed roles', function () {
    $team = User::query()->create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'x'])->assignRole(Role::findOrCreate('security-team', 'web'));
    config(['sunrice-admin.security.alerts.roles' => ['security-team']]);
    app(SecurityLog::class)->record('upload blocked', $this->rina, null, ['note' => 'x.php']);
    Mail::assertSent(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->hasTo($team->email));

    config(['sunrice-admin.security.alerts.enabled' => false]);
    app(SecurityLog::class)->record('account locked', $this->admin);
    expect(alertsSent())->toBe(['A dangerous upload was blocked']);
});

it('raises a plugin\'s own alert, once per rule and key in the cooldown, and none when alerts are off', function () {
    $alerts = app(SecurityAlerts::class);
    $alerts->raise('shop-payout', '1', 'The payout account was changed', 'Ana changed where payouts go.', 'Change it back if nobody meant to.', ['Who' => 'Ana']);
    $alerts->raise('shop-payout', '1', 'The payout account was changed', 'Again.', 'Same.');
    $alerts->raise('shop-payout', '2', 'Another shop\'s payout account was changed', 'Budi did.', 'Same.');
    config(['sunrice-admin.security.alerts.enabled' => false]);
    $alerts->raise('shop-other', '1', 'Not sent', 'Off.', 'Off.');

    expect(alertsSent())->toBe(['The payout account was changed', 'Another shop\'s payout account was changed']);
    expect(Mail::sent(SecurityAlert::class)->first()->render())->toContain('Ana changed where payouts go.')->toContain('Change it back');
});
