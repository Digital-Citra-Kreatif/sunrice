<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Auth\TwoFactorLogin;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Notifications\LoginCode;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecuritySettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\travel;

beforeEach(function () {
    usePanel();
    Notification::fake();
    $this->admin = adminUser(Panel::ACCESS);
    $this->admin->forceFill(['email' => 'rina@example.com', 'password' => 'secret-password'])->save();
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['twoFactor' => true]);
    RateLimiter::clear('sunrice-two-factor|'.$this->admin->id);
});

function passwordStep(): TestResponse
{
    return post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password']);
}

/** The code in the last login email. */
function sentCode(): string
{
    $code = null;
    Notification::assertSentTo(new AnonymousNotifiable, LoginCode::class, function (LoginCode $n, array $channels, AnonymousNotifiable $to) use (&$code) {
        $code = $n->code;

        return $to->routes['mail'] === 'rina@example.com';
    });

    return (string) $code;
}

it('asks for the emailed code after the right password', function () {
    passwordStep()->assertRedirect('/admin/login/code');
    assertGuest();
    get('/admin/login/code')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Auth/TwoFactor', shouldExist: false)->where('email', 'r•••@example.com')->where('minutes', 10));
    expect(sentCode())->toMatch('/^\d{6}$/');
    // Only a hash is kept.
    expect(json_encode(session(TwoFactorLogin::SESSION_KEY)))->not->toContain(sentCode());

    post('/admin/login/code', ['code' => substr(sentCode(), 0, 3).' '.substr(sentCode(), 3)])->assertRedirect('/admin');
    assertAuthenticatedAs($this->admin);
    expect(Activity::query()->where('action', 'logged in')->sole()->properties['note'])->toBe('with a login code');
});

it('refuses wrong, expired and too many codes', function () {
    passwordStep();
    $code = sentCode();
    post('/admin/login/code', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
    assertGuest();

    travel(11)->minutes();
    post('/admin/login/code', ['code' => $code])->assertRedirect('/admin/login')->assertSessionHas('error');
    assertGuest();

    travel(-11)->minutes();
    passwordStep();
    $code = '';
    Notification::assertSentTimes(LoginCode::class, 2);
    foreach (range(1, 5) as $i) {
        post('/admin/login/code', ['code' => '999999']);
    }
    get('/admin/login/code')->assertRedirect('/admin/login');
    expect(Activity::query()->where('action', 'login blocked')->exists())->toBeTrue();
});

it('locks the account\'s codes across fresh logins', function () {
    foreach (range(1, 2) as $round) {
        passwordStep();
        foreach (range(1, 5) as $i) {
            post('/admin/login/code', ['code' => '999999x']);
        }
    }
    passwordStep()->assertSessionHasErrors('email');
    assertGuest();
});

it('resends a code, but not too often', function () {
    passwordStep();
    post('/admin/login/code/resend')->assertSessionHas('error');
    travel(61)->seconds();
    post('/admin/login/code/resend')->assertSessionHas('success');
    Notification::assertSentTimes(LoginCode::class, 2);
});

it('doesn\'t sign in when the code can\'t be emailed', function () {
    Notification::swap(new class extends ChannelManager
    {
        public function __construct() {}

        public function sendNow($notifiables, $notification, ?array $channels = null): void
        {
            throw new RuntimeException('SMTP down');
        }
    });

    passwordStep()->assertSessionHasErrors(['email' => 'We couldn\'t email your login code. Ask an administrator to check that the app can send email.']);
    assertGuest();
    get('/admin/login/code')->assertRedirect('/admin/login');
});

it('signs in with the password alone when it\'s off, or forced off by config', function () {
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['twoFactor' => false]);
    passwordStep()->assertRedirect('/admin');
    assertAuthenticatedAs($this->admin);

    auth('web')->logout();
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['twoFactor' => true]);
    config(['sunrice-admin.auth.two_factor' => false]);
    passwordStep()->assertRedirect('/admin');
});

it('refuses to turn on while the mailer only writes to the log, which would lock everyone out', function () {
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['twoFactor' => false]);
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.security'));

    config(['mail.default' => 'log']);
    put('/admin/settings/security', ['twoFactor' => true])->assertSessionHasErrors(['twoFactor' => 'The app can\'t send email yet (the log mailer only writes to the log), so nobody would get their login code. Set up mail first: see Settings → Mail.']);
    expect(app(SecuritySettings::class)->twoFactor)->toBeFalse();
    put('/admin/settings/security', ['twoFactor' => false])->assertSessionHasNoErrors();

    config(['mail.default' => 'smtp']);
    put('/admin/settings/security', ['twoFactor' => true])->assertSessionHasNoErrors();
    // Already on: saving the page again isn't refused when mail breaks later.
    config(['mail.default' => 'log']);
    put('/admin/settings/security', ['twoFactor' => true])->assertSessionHasNoErrors();
    $this->artisan('sunrice:two-factor', ['state' => 'on'])->expectsOutputToContain('nobody will get a login code')->assertSuccessful();
});

it('is a setting, guarded, and turning it off is a security alert', function () {
    Mail::fake();
    config(['sunrice-admin.security.alerts.emails' => ['security@example.com']]);
    actingAs(adminUser(Panel::ACCESS));
    get('/admin/settings/security')->assertForbidden();

    $editor = adminUser(Panel::ACCESS, 'sunrice.settings.security');
    actingAs($editor);
    get('/admin/settings/security')->assertOk();
    put('/admin/settings/security', ['twoFactor' => false])->assertSessionHasNoErrors();

    expect(app(SecuritySettings::class)->twoFactor)->toBeFalse()
        ->and(Activity::query()->where('action', 'two-factor turned off')->sole()->user_id)->toEqual($editor->id);
    Mail::assertQueued(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->headline === 'Two-factor login was turned off');
});

it('switches from the console', function () {
    artisan('sunrice:two-factor')->expectsOutputToContain('on')->assertSuccessful();
    artisan('sunrice:two-factor off')->assertSuccessful();
    expect(TwoFactorLogin::enabled())->toBeFalse();
    artisan('sunrice:two-factor maybe')->assertFailed();
});
