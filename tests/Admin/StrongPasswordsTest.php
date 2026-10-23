<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Actions\Users\DeleteUser;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Auth\PasswordPolicy;
use Sunrice\Admin\Auth\TwoFactorLogin;
use Sunrice\Admin\Auth\WeakPasswordAtLogin;
use Sunrice\Admin\Notifications\LoginCode;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountSecurity;
use Sunrice\Admin\Security\SecuritySettings;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

const STRONG = 'Plum-tree-Sky-42!';

beforeEach(function () {
    usePanel();
    app(SyncPermissions::class)->handle();
    Http::preventStrayRequests();
    $this->super = User::query()->create(['name' => 'Super', 'email' => 'super@example.com', 'password' => STRONG])->assignRole('super-admin');
});

/** The validation messages for a password under the panel's policy, or [] when it passes. */
function passwordErrors(string $password, ?User $for = null, array $form = []): array
{
    $validator = Validator::make([...$form, 'password' => $password], ['password' => ['required', 'string', ...PasswordPolicy::rules($for)]]);

    return $validator->errors()->get('password');
}

/** Have I Been Pwned's answer for a range, with `$leaked` among the suffixes. */
function fakeLeaks(string ...$leaked): void
{
    Http::fake(['api.pwnedpasswords.com/range/*' => function (HttpRequest $request) use ($leaked) {
        $lines = ['0018A45C4D1DEF81644B54AB7F969B88D65:0']; // padding
        foreach ($leaked as $password) {
            $hash = strtoupper(sha1($password));
            if (str_ends_with($request->url(), substr($hash, 0, 5))) {
                $lines[] = substr($hash, 5).':3861493';
            }
        }

        return Http::response(implode("\r\n", $lines));
    }]);
}

it('refuses each missing rule with its own message, and accepts a strong password', function () {
    expect(PasswordPolicy::strong())->toBeTrue()
        ->and(passwordErrors('Sh0rt!'))->toBe(['Use at least 12 characters.'])
        ->and(passwordErrors('all-lower-case-42'))->toBe(['Use both upper and lower case letters.'])
        ->and(passwordErrors('ALL-UPPER-CASE-42'))->toBe(['Use both upper and lower case letters.'])
        ->and(passwordErrors('No-numbers-here!'))->toBe(['Add a number.'])
        ->and(passwordErrors('NoSymbolsHere42'))->toBe(['Add a symbol, such as ! or #.'])
        ->and(passwordErrors('secret'))->toBe(['Use at least 12 characters.', 'Use both upper and lower case letters.', 'Add a number.', 'Add a symbol, such as ! or #.'])
        ->and(passwordErrors(STRONG))->toBe([])
        // Unicode letters and symbols count, as the checklist counts them.
        ->and(passwordErrors('Ébène-ÇA-ça-2026'))->toBe([]);

    // The length follows the config, never below 8.
    config(['sunrice-admin.auth.password_min' => 20]);
    expect(passwordErrors(STRONG))->toBe(['Use at least 20 characters.']);
    config(['sunrice-admin.auth.password_min' => 4]);
    expect(PasswordPolicy::minLength())->toBe(8)
        ->and(passwordErrors('Ab3!efgh'))->toBe([]);
});

it('refuses the person\'s own email address or name, whatever the case', function () {
    $rina = User::query()->create(['name' => 'Rina-Wijaya-2026!', 'email' => 'Rina.W-2026@Example.com', 'password' => STRONG]);

    expect(passwordErrors('RINA-wijaya-2026!', $rina))->toBe(['Don\'t use the email address or name as the password.'])
        ->and(passwordErrors('rina.w-2026@example.COM', $rina))->toBe(['Don\'t use the email address or name as the password.'])
        // A new account: the form's own email and name.
        ->and(passwordErrors('Dewi-Lestari-99!', null, ['name' => 'Dewi-Lestari-99!']))->toBe(['Don\'t use the email address or name as the password.'])
        ->and(passwordErrors(STRONG, $rina))->toBe([]);
});

it('falls back to the app\'s Password::defaults() when strong passwords are off', function () {
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['strongPasswords' => false]);

    expect(PasswordPolicy::strong())->toBeFalse()
        ->and(passwordErrors('secret-password'))->toBe([])
        ->and(PasswordPolicy::checklist())->toBe(['min' => 8, 'mixedCase' => false, 'letters' => false, 'numbers' => false, 'symbols' => false]);

    Password::defaults(fn () => Password::min(10)->numbers());
    expect(passwordErrors('secret-password'))->not->toBe([])
        ->and(passwordErrors('secret-password-1'))->toBe([])
        ->and(PasswordPolicy::checklist())->toBe(['min' => 10, 'mixedCase' => false, 'letters' => false, 'numbers' => true, 'symbols' => false]);
    Password::$defaultCallback = null;
});

it('lets the config force strong passwords on or off, whatever the setting says', function () {
    config(['sunrice-admin.auth.strong_passwords' => false]);
    expect(PasswordPolicy::strong())->toBeFalse()->and(passwordErrors('secret-password'))->toBe([]);

    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['strongPasswords' => false]);
    config(['sunrice-admin.auth.strong_passwords' => true]);
    expect(PasswordPolicy::strong())->toBeTrue()->and(passwordErrors('secret-password'))->not->toBe([]);

    // The setting can't be changed while the config decides.
    actingAs($this->super);
    get('/admin/settings/security')->assertInertia(fn (Assert $page) => $page
        ->where('form.1.fields.0', fn ($field) => $field['name'] === 'strongPasswords' && $field['disabled'] === true));
});

it('refuses passwords from known data leaks, by k-anonymity', function () {
    config(['sunrice-admin.auth.password_uncompromised' => true]);
    fakeLeaks('Password-123!');

    expect(passwordErrors('Password-123!'))->toBe(['This password has appeared in a data leak. Choose a different one.'])
        ->and(passwordErrors(STRONG))->toBe([]);
    // Only the hash's first 5 characters were sent, with padding asked for.
    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.pwnedpasswords.com/range/'.substr(strtoupper(sha1(STRONG)), 0, 5)
        && $request->hasHeader('Add-Padding', 'true'));
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), substr(strtoupper(sha1(STRONG)), 5)));

    // Weak passwords are refused without asking.
    Http::assertSentCount(2);
    passwordErrors('weak');
    Http::assertSentCount(2);

    // Off in the config: never asked.
    config(['sunrice-admin.auth.password_uncompromised' => false]);
    expect(passwordErrors('Password-123!'))->toBe([]);
    Http::assertSentCount(2);
});

it('accepts passwords when the leak check can\'t be reached, and warns once', function () {
    config(['sunrice-admin.auth.password_uncompromised' => true]);
    Log::spy();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('Service unavailable', 503)]);

    expect(passwordErrors('Password-123!'))->toBe([])
        ->and(passwordErrors(STRONG))->toBe([]);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'data leaks') && str_contains($message, 'HTTP 503'));

    Http::fake(fn () => throw new ConnectionException('timed out'));
    expect(passwordErrors('Another-Strong-1!'))->toBe([]);
    Log::shouldHaveReceived('warning')->once();
});

it('uses the policy when creating and editing users, with the rules for the checklist', function () {
    actingAs($this->super);
    get('/admin/users/create')->assertInertia(fn (Assert $page) => $page
        ->where('sections.0.fields.2', fn ($field) => $field['name'] === 'password'
            && $field['passwordRules'] === ['min' => 12, 'mixedCase' => true, 'letters' => false, 'numbers' => true, 'symbols' => true]));

    post('/admin/users', ['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'dewi-password', 'password_confirmation' => 'dewi-password', 'roles' => []])
        ->assertSessionHasErrors(['password' => 'Use both upper and lower case letters.']);
    post('/admin/users', ['name' => 'Dewi', 'email' => 'Dewi-2026@example.com', 'password' => 'dewi-2026@example.com', 'password_confirmation' => 'dewi-2026@example.com', 'roles' => []])
        ->assertSessionHasErrors(['password' => 'Don\'t use the email address or name as the password.']);
    post('/admin/users', ['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => STRONG, 'password_confirmation' => STRONG, 'roles' => []])->assertSessionHasNoErrors();
    $dewi = User::query()->where('email', 'dewi@example.com')->firstOrFail();

    put("/admin/users/{$dewi->id}", ['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'short', 'password_confirmation' => 'short', 'roles' => []])
        ->assertSessionHasErrors(['password' => 'Use at least 12 characters.']);
    put("/admin/users/{$dewi->id}", ['name' => 'Dewi-Lestari-99!', 'email' => 'dewi@example.com', 'password' => 'DEWI-lestari-99!', 'password_confirmation' => 'DEWI-lestari-99!', 'roles' => []])
        ->assertSessionHasErrors(['password' => 'Don\'t use the email address or name as the password.']);
    put("/admin/users/{$dewi->id}", ['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'New-'.STRONG, 'password_confirmation' => 'New-'.STRONG, 'roles' => []])->assertSessionHasNoErrors();
    expect(Hash::check('New-'.STRONG, $dewi->fresh()->password))->toBeTrue();
});

it('uses the policy on Your account', function () {
    $rina = adminUser(Panel::ACCESS);
    $rina->forceFill(['password' => STRONG])->save();
    actingAs($rina);

    get('/admin/account')->assertInertia(fn (Assert $page) => $page->where('passwordRules.min', 12));
    put('/admin/account', ['name' => 'Rina', 'email' => $rina->email, 'current_password' => STRONG, 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasErrors(['password' => 'Use both upper and lower case letters.']);
    put('/admin/account', ['name' => 'Rina', 'email' => $rina->email, 'current_password' => STRONG, 'password' => 'New-'.STRONG, 'password_confirmation' => 'New-'.STRONG])
        ->assertSessionHasNoErrors();
});

it('uses the policy when resetting a password and accepting an invitation', function () {
    $rina = User::query()->create(['name' => 'Rina-Wijaya-2026!', 'email' => 'rina@example.com', 'password' => STRONG]);
    $token = PasswordBroker::broker()->createToken($rina);

    get("/admin/reset-password/{$token}?email=rina@example.com&invited=1")->assertInertia(fn (Assert $page) => $page
        ->component('Auth/ResetPassword', false)->where('passwordRules.symbols', true));
    post('/admin/reset-password', ['token' => $token, 'email' => 'rina@example.com', 'password' => 'new-password', 'password_confirmation' => 'new-password', 'invited' => true])
        ->assertSessionHasErrors(['password' => 'Use both upper and lower case letters.']);
    // The name is refused with a valid link only.
    post('/admin/reset-password', ['token' => 'wrong', 'email' => 'rina@example.com', 'password' => 'RINA-wijaya-2026!', 'password_confirmation' => 'RINA-wijaya-2026!'])
        ->assertSessionDoesntHaveErrors('password');
    post('/admin/reset-password', ['token' => $token, 'email' => 'rina@example.com', 'password' => 'RINA-wijaya-2026!', 'password_confirmation' => 'RINA-wijaya-2026!'])
        ->assertSessionHasErrors(['password' => 'Don\'t use the email address or name as the password.']);
    post('/admin/reset-password', ['token' => $token, 'email' => 'rina@example.com', 'password' => 'New-'.STRONG, 'password_confirmation' => 'New-'.STRONG, 'invited' => true])
        ->assertRedirect('/admin/login')->assertSessionHasNoErrors();
    expect(Hash::check('New-'.STRONG, $rina->fresh()->password))->toBeTrue();
});

it('uses the policy in sunrice:make-admin, and explains it when asked interactively', function () {
    artisan('sunrice:make-admin', ['--name' => 'Boss', '--email' => 'boss@example.com', '--password' => 'boss-password', '--no-interaction' => true])
        ->expectsOutputToContain('Use both upper and lower case letters.')
        ->doesntExpectOutputToContain('A password needs')
        ->assertFailed();
    artisan('sunrice:make-admin', ['--name' => 'Boss', '--email' => 'boss@example.com'])
        ->expectsQuestion('Password', 'boss')
        ->expectsOutputToContain('A password needs 12 characters or more, upper and lower case letters, a number and a symbol')
        ->assertFailed();
    expect(User::query()->where('email', 'boss@example.com')->exists())->toBeFalse();

    artisan('sunrice:make-admin', ['--name' => 'Boss', '--email' => 'boss@example.com', '--password' => STRONG])->assertSuccessful();
});

it('never lets AI tools read or set passwords', function () {
    [, $token] = AiToken::issue((string) $this->super->id, 'Claude');
    $call = function (string $tool, array $arguments) use ($token): string {
        $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);

        return (string) ($response->json('result.content.0.text') ?? $response->json('error.message'));
    };

    $form = json_decode($call('get_record', ['resource' => 'users', 'id' => (string) $this->super->id]), true);
    expect(array_column($form['fields'], 'name'))->not->toContain('password')
        ->and($form['values'])->not->toHaveKey('password');

    expect($call('save_record', ['resource' => 'users', 'id' => (string) $this->super->id, 'values' => ['password' => STRONG.'x']]))->toContain('Passwords can\'t be set with AI access')
        ->and($call('save_record', ['resource' => 'users', 'values' => ['name' => 'Bot', 'email' => 'bot@example.com', 'password_confirmation' => STRONG, 'roles' => []]]))->toContain('Passwords can\'t be set with AI access')
        ->and(Hash::check(STRONG, $this->super->fresh()->password))->toBeTrue();
});

it('asks someone who signs in with a weak password to choose a stronger one first', function () {
    $rina = adminUser(Panel::ACCESS, 'sunrice.settings.security');
    $rina->forceFill(['email' => 'rina@example.com', 'password' => 'secret-password'])->save();

    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password'])->assertRedirect('/admin');
    expect(Activity::query()->where('action', 'weak password at sign-in')->where('subject_id', (string) $rina->id)->first()?->properties['note'])
        ->toBe('fails: mixed, numbers');

    // Every panel page leads to the change page.
    foreach (['/admin', '/admin/account', '/admin/settings/security', '/admin/activity', '/admin/no/such/page'] as $url) {
        get($url)->assertRedirect('/admin/stronger-password');
    }
    put('/admin/settings/security', ['strongPasswords' => false])->assertRedirect('/admin/stronger-password');
    expect(app(SecuritySettings::class)->strongPasswords)->toBeTrue();
    put('/admin/preferences/table.users', ['value' => []], ['Accept' => 'application/json'])->assertStatus(409)
        ->assertJson(['message' => 'Choose a stronger password first.']);

    get('/admin/stronger-password')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/StrongerPassword', false)
        ->where('email', 'rina@example.com')->where('passwordRules.min', 12));
    put('/admin/stronger-password', ['password' => 'still-weak', 'password_confirmation' => 'still-weak'])->assertSessionHasErrors('password');
    put('/admin/stronger-password', ['password' => STRONG, 'password_confirmation' => 'other'])->assertSessionHasErrors('password');
    get('/admin/activity')->assertRedirect('/admin/stronger-password');

    // Then on to the page they asked for.
    put('/admin/stronger-password', ['password' => STRONG, 'password_confirmation' => STRONG])
        ->assertRedirect('/admin/activity')->assertSessionHas('success', 'Your new password is saved.');
    expect(Hash::check(STRONG, $rina->fresh()->password))->toBeTrue()
        ->and(Activity::query()->where('action', 'weak password changed')->where('subject_id', (string) $rina->id)->exists())->toBeTrue();
    get('/admin')->assertOk();
    get('/admin/stronger-password')->assertRedirect('/admin');
});

it('keeps logging out open, and forgets the request with the session', function () {
    $rina = adminUser(Panel::ACCESS);
    $rina->forceFill(['email' => 'rina@example.com', 'password' => 'secret-password'])->save();

    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password']);
    get('/admin')->assertRedirect('/admin/stronger-password');
    post('/admin/logout')->assertRedirect('/admin/login');
    expect(auth()->check())->toBeFalse();

    // Signing in as someone else on the same browser isn't affected.
    post('/admin/login', ['email' => 'super@example.com', 'password' => STRONG])->assertRedirect('/admin');
    get('/admin')->assertOk();
    expect(Activity::query()->where('action', 'weak password at sign-in')->count())->toBe(1);
});

it('leaves strong passwords, sessions without a login and the setting turned off alone', function () {
    post('/admin/login', ['email' => 'super@example.com', 'password' => STRONG])->assertRedirect('/admin');
    get('/admin')->assertOk();
    post('/admin/logout');

    // Signed in some other way (no password typed here): nothing to check.
    $rina = adminUser(Panel::ACCESS);
    actingAs($rina);
    get('/admin')->assertOk();
    auth()->logout();

    // Off: weak passwords still sign in as before.
    $rina->forceFill(['password' => 'secret-password'])->save();
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['changeWeakPasswords' => false]);
    post('/admin/login', ['email' => $rina->email, 'password' => 'secret-password'])->assertRedirect('/admin');
    get('/admin')->assertOk();
    post('/admin/logout');

    // Strong passwords off: there's nothing to ask for.
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['strongPasswords' => false, 'changeWeakPasswords' => true]);
    post('/admin/login', ['email' => $rina->email, 'password' => 'secret-password'])->assertRedirect('/admin');
    get('/admin')->assertOk();
    expect(Activity::query()->where('action', 'weak password at sign-in')->exists())->toBeFalse();
});

it('checks the password at the first step of two-factor login, and asks once the code signs in', function () {
    Notification::fake();
    $rina = adminUser(Panel::ACCESS);
    $rina->forceFill(['email' => 'rina@example.com', 'password' => 'secret-password'])->save();
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['twoFactor' => true]);

    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password'])->assertRedirect('/admin/login/code');
    expect(session(WeakPasswordAtLogin::SESSION_KEY)['user'])->toBe((string) $rina->id)
        ->and(Activity::query()->where('action', 'weak password at sign-in')->exists())->toBeFalse();

    $code = null;
    Notification::assertSentOnDemand(LoginCode::class, function (LoginCode $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });
    post('/admin/login/code', ['code' => $code])->assertRedirect('/admin');
    expect(Activity::query()->where('action', 'weak password at sign-in')->where('subject_id', (string) $rina->id)->exists())->toBeTrue();
    get('/admin')->assertRedirect('/admin/stronger-password');
    expect(TwoFactorLogin::enabled())->toBeTrue();
});

/** A new browser session, signed back in by the "Remember me" cookie alone. */
function comeBackRemembered(User $user): void
{
    $user = $user->fresh();
    Auth::forgetGuards();
    test()->flushSession();
    test()->withCookie(Auth::guard('web')->getRecallerName(), $user->id.'|'.$user->getRememberToken().'|'.$user->getAuthPassword());
}

/** The account's kept mark (a fingerprint), or null. */
function weakMark(User $user): ?string
{
    return AccountSecurity::query()->where('user_id', (string) $user->id)->value('weak_password');
}

it('keeps asking in a new session signed in by "Remember me", until a stronger password is chosen', function () {
    $rina = adminUser(Panel::ACCESS);
    $rina->forceFill(['email' => 'rina@example.com', 'password' => 'secret-password'])->save();

    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password', 'remember' => true])->assertRedirect('/admin');
    expect(weakMark($rina))->toBeString()->not->toContain('secret');

    // The session is gone; the cookie signs them back in, and the request is still there.
    comeBackRemembered($rina);
    get('/admin')->assertRedirect('/admin/stronger-password');
    expect(auth()->user()?->is($rina))->toBeTrue()->and(Auth::guard('web')->viaRemember())->toBeTrue();
    get('/admin/stronger-password')->assertOk();

    // Another session of theirs (signed in some other way) is asked too, until they choose one.
    put('/admin/stronger-password', ['password' => STRONG, 'password_confirmation' => STRONG])->assertRedirect('/admin');
    expect(weakMark($rina))->toBeNull();
    Auth::forgetGuards();
    actingAs($rina->fresh());
    get('/admin')->assertOk();
});

it('asks every session of the account, and stops when the setting is turned off', function () {
    $rina = adminUser(Panel::ACCESS);
    $rina->forceFill(['email' => 'rina@example.com', 'password' => 'secret-password'])->save();
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password']);
    post('/admin/logout');

    // Signed in without typing the password here: still asked, the account is marked.
    actingAs($rina);
    get('/admin')->assertRedirect('/admin/stronger-password');

    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['changeWeakPasswords' => false]);
    get('/admin')->assertOk();
    get('/admin/stronger-password')->assertRedirect('/admin');

    // On again, the same weak password is asked about again.
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['changeWeakPasswords' => true]);
    get('/admin')->assertRedirect('/admin/stronger-password');
});

it('stops asking once an administrator sets a new password', function () {
    $rina = adminUser(Panel::ACCESS);
    $rina->forceFill(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password'])->save();
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password']);
    post('/admin/logout');
    expect(weakMark($rina))->not->toBeNull();

    actingAs($this->super);
    put("/admin/users/{$rina->id}", ['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'New-'.STRONG, 'password_confirmation' => 'New-'.STRONG, 'roles' => $rina->roles->pluck('name')->all()])
        ->assertSessionHasNoErrors();

    actingAs($rina->fresh());
    get('/admin')->assertOk();
    expect(weakMark($rina))->toBeNull();
});

it('stops asking once the password is reset, and forgets the mark with the user', function () {
    $rina = adminUser(Panel::ACCESS);
    $rina->forceFill(['email' => 'rina@example.com', 'password' => 'secret-password'])->save();
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password']);
    post('/admin/logout');

    $token = PasswordBroker::broker()->createToken($rina);
    post('/admin/reset-password', ['token' => $token, 'email' => 'rina@example.com', 'password' => 'New-'.STRONG, 'password_confirmation' => 'New-'.STRONG])
        ->assertRedirect('/admin/login')->assertSessionHasNoErrors();
    actingAs($rina->fresh());
    get('/admin')->assertOk();
    expect(weakMark($rina))->toBeNull();

    // Weak again (set outside the panel), signed in, then deleted: nothing is left.
    $rina->forceFill(['password' => 'secret-password'])->save();
    post('/admin/logout');
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password']);
    expect(weakMark($rina))->not->toBeNull();
    app(DeleteUser::class)->handle($rina->fresh());
    expect(AccountSecurity::query()->where('user_id', (string) $rina->id)->exists())->toBeFalse();
});

it('checks leaks at sign-in too, when the check is on', function () {
    config(['sunrice-admin.auth.password_uncompromised' => true]);
    fakeLeaks('Password-123!');
    $rina = adminUser(Panel::ACCESS);
    $rina->forceFill(['email' => 'rina@example.com', 'password' => 'Password-123!'])->save();

    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'Password-123!'])->assertRedirect('/admin');
    get('/admin')->assertRedirect('/admin/stronger-password');
    expect(Activity::query()->where('action', 'weak password at sign-in')->first()?->properties['note'])->toBe('fails: leaked');
});

it('shows the password settings in Settings → Security, guarded by its permission', function () {
    $rina = adminUser(Panel::ACCESS);
    actingAs($rina);
    get('/admin/settings/security')->assertForbidden();
    put('/admin/settings/security', ['strongPasswords' => false])->assertForbidden();

    actingAs($this->super);
    get('/admin/settings/security')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('form.1.title', 'Passwords')
        ->where('form.1.fields.0.name', 'strongPasswords')->where('form.1.fields.0.disabled', false)
        ->where('form.1.fields.1.name', 'changeWeakPasswords')->where('form.1.fields.1.showWhen', ['field' => 'strongPasswords', 'value' => true])
        ->where('values.strongPasswords', true)->where('values.changeWeakPasswords', true));
    put('/admin/settings/security', ['twoFactor' => false, 'strongPasswords' => false, 'changeWeakPasswords' => false, 'alertRoles' => [], 'alertEmails' => ''])->assertSessionHasNoErrors();
    expect(PasswordPolicy::strong())->toBeFalse()->and(PasswordPolicy::asksWeakToChange())->toBeFalse();
});
