<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Events\LoginThrottled;
use Sunrice\Admin\Notifications\ResetPanelPassword;
use Sunrice\Admin\Panel;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->routes(fn () => Route::get('reports', fn () => 'reports')->name('reports')));
    // Only @example.com accounts may open the panel; customers may not.
    Gate::define(Panel::ACCESS, fn (User $user) => str_ends_with((string) $user->getAttribute('email'), '@example.com'));
    $this->admin = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password']);
    RateLimiter::clear('sunrice-login|rina@example.com|127.0.0.1');
});

function login(string $email = 'rina@example.com', string $password = 'secret-password', string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->post('/admin/login', ['email' => $email, 'password' => $password]);
}

it('shows the login page to guests only', function () {
    get('/admin/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login', shouldExist: false));

    actingAs($this->admin);
    get('/admin/login')->assertRedirect('/admin');
});

it('signs in and sends people where they were going', function () {
    get('/admin/reports')->assertRedirect('/admin/login');
    login()->assertRedirect('/admin/reports');

    assertAuthenticatedAs($this->admin);
});

it('remembers the login when asked', function () {
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password', 'remember' => true])
        ->assertRedirect('/admin')
        ->assertCookie(Auth::guard('web')->getRecallerName());
});

it('refuses a wrong password, and accounts that may not open the panel', function () {
    Event::fake([Failed::class]);

    login(password: 'wrong')->assertSessionHasErrors(['email' => __('auth.failed')]);
    assertGuest();
    Event::assertDispatched(Failed::class);

    User::query()->create(['name' => 'Customer', 'email' => 'buyer@shop.test', 'password' => 'secret-password']);
    login('buyer@shop.test')->assertSessionHasErrors(['email' => 'This account can\'t use the admin panel.']);
    assertGuest();
});

it('blocks one email from one IP after a few wrong passwords', function () {
    config(['sunrice-admin.auth.throttle.attempts' => 3]);
    Event::fake([LoginThrottled::class]);

    foreach (range(1, 3) as $i) {
        login(password: 'wrong');
    }
    login()->assertSessionHasErrors('email');
    assertGuest();

    // Another IP still works.
    login(ip: '10.0.0.2')->assertRedirect('/admin');
    Event::assertDispatchedTimes(LoginThrottled::class, 1);
    Event::assertDispatched(LoginThrottled::class, fn (LoginThrottled $e) => $e->scope === 'pair' && $e->email === 'rina@example.com');
});

it('blocks an IP trying many emails, and locks an account tried from many IPs', function () {
    config(['sunrice-admin.auth.throttle' => ['attempts' => 50, 'per_ip' => 3, 'per_account' => 50]]);
    foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
        login($email, 'wrong', '10.0.0.9');
    }
    login(ip: '10.0.0.9')->assertSessionHasErrors('email');

    config(['sunrice-admin.auth.throttle' => ['attempts' => 50, 'per_ip' => 50, 'per_account' => 3, 'account_lock_seconds' => 900]]);
    foreach (['10.0.1.1', '10.0.1.2', '10.0.1.3'] as $ip) {
        login(password: 'wrong', ip: $ip);
    }
    login(ip: '10.0.1.4')->assertSessionHasErrors(['email' => 'Too many login attempts. Try again in 15 minutes.']);
});

it('forgives an email after a successful login', function () {
    config(['sunrice-admin.auth.throttle.attempts' => 3]);
    login(password: 'wrong');
    login(password: 'wrong');
    login()->assertRedirect('/admin');
    post('/admin/logout');

    login(password: 'wrong');
    login(password: 'wrong');
    login()->assertRedirect('/admin');
});

it('signs out', function () {
    actingAs($this->admin);

    post('/admin/logout')->assertRedirect('/admin/login');
    assertGuest();
});

it('emails a reset link to the panel without telling who has an account', function () {
    Notification::fake();

    post('/admin/forgot-password', ['email' => 'rina@example.com'])->assertSessionHas('success');
    post('/admin/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('success');

    Notification::assertSentOnDemand(ResetPanelPassword::class, function (ResetPanelPassword $notification, array $channels, object $notifiable) {
        $url = $notification->toMail($notifiable)->actionUrl;

        return $notifiable->routes['mail'] === 'rina@example.com'
            && str_contains($url, '/admin/reset-password/') && str_contains($url, 'email=rina%40example.com');
    });
    Notification::assertCount(1);
});

it('resets the password', function () {
    $token = Password::broker()->createToken($this->admin);

    get("/admin/reset-password/{$token}?email=rina@example.com")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword', shouldExist: false)->where('email', 'rina@example.com'));

    post('/admin/reset-password', ['token' => 'wrong', 'email' => 'rina@example.com', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertSessionHasErrors(['email' => __(Password::INVALID_TOKEN)]);
    // An email with no account gets the same answer: the form doesn't tell who has one.
    post('/admin/reset-password', ['token' => $token, 'email' => 'nobody@example.com', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertSessionHasErrors(['email' => __(Password::INVALID_TOKEN)]);
    post('/admin/reset-password', ['token' => $token, 'email' => 'rina@example.com', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertRedirect('/admin/login');

    login(password: 'new-password-1')->assertRedirect('/admin');
    assertAuthenticated();
});

it('limits the password forms per IP', function () {
    config(['sunrice-admin.auth.throttle.password_resets_per_minute' => 2]);
    Notification::fake();

    post('/admin/forgot-password', ['email' => 'rina@example.com'])->assertRedirect();
    post('/admin/forgot-password', ['email' => 'rina@example.com'])->assertRedirect();
    post('/admin/forgot-password', ['email' => 'rina@example.com'])->assertTooManyRequests();
});
