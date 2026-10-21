<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Notifications\LoginCode;
use Sunrice\Admin\Notifications\ResetPanelPassword;
use Sunrice\Admin\Notifications\TestMail;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecuritySettings;
use Sunrice\Admin\Settings\BrandingSettings;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/*
 * What people read names the panel by its branded name (Settings →
 * Branding), not the name given in code: emails and the AI server too.
 */

beforeEach(function () {
    Mail::fake();
    Notification::fake();
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));
    app(SyncPermissions::class)->handle();
    app(BrandingSettings::class)->fill(['name' => 'Kebun Studio'])->save();
    $this->rina = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password']);
});

it('reads the branded name, else the panel\'s own', function () {
    expect(app(Panel::class)->brandName())->toBe('Kebun Studio')
        ->and(app(Panel::class)->getBrand())->toBe('Acme');

    app(BrandingSettings::class)->fill(['name' => '  '])->save();
    expect(app(Panel::class)->brandName())->toBe('Acme');
});

it('names the panel in the test email', function () {
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.mail'));
    post('/admin/settings/mail/test')->assertRedirect();

    Notification::assertSentOnDemand(TestMail::class, function (TestMail $mail, array $channels, object $notifiable) {
        $message = $mail->toMail($notifiable);

        return $message->subject === 'Test email from Kebun Studio'
            && in_array('This is a test email from the Kebun Studio admin panel.', $message->introLines, true)
            && str_contains((string) $message->salutation, 'Kebun Studio');
    });
});

it('names the panel in the password reset email', function () {
    $this->rina->givePermissionTo(Panel::ACCESS);
    post('/admin/forgot-password', ['email' => 'rina@example.com']);

    Notification::assertSentOnDemand(ResetPanelPassword::class, function (ResetPanelPassword $notification, array $channels, object $notifiable) {
        $message = $notification->toMail($notifiable);

        return $message->subject === 'Reset your Kebun Studio password' && str_contains((string) $message->salutation, 'Kebun Studio')
            && str_contains((string) $message->actionUrl, '/admin/reset-password/');
    });
});

it('names the panel in the login code email', function () {
    $this->rina->givePermissionTo(Panel::ACCESS);
    app(SaveSettings::class)->handle(app(SecuritySettings::class), ['twoFactor' => true]);
    post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password'])->assertRedirect('/admin/login/code');

    // Not in the subject, which locked phones show: only in the body, unless the app asks.
    Notification::assertSentTo(new AnonymousNotifiable, LoginCode::class, function (LoginCode $n, array $channels, AnonymousNotifiable $to) {
        $mail = $n->toMail($to);

        return $mail->subject === 'Your Kebun Studio login code' && in_array("**{$n->code}**", $mail->introLines, true);
    });
    config(['sunrice-admin.auth.two_factor_code_in_subject' => true]);
    Notification::assertSentTo(new AnonymousNotifiable, LoginCode::class, fn (LoginCode $n, array $channels, AnonymousNotifiable $to) => $n->toMail($to)->subject === "Your Kebun Studio login code: {$n->code}");
});

it('names the panel in security alerts', function () {
    config(['sunrice-admin.security.alerts.emails' => ['security@example.com'],
        'sunrice-admin.auth.throttle' => ['attempts' => 50, 'per_ip' => 50, 'per_account' => 2, 'account_lock_seconds' => 900]]);
    $this->rina->givePermissionTo(Panel::ACCESS);
    foreach (range(1, 3) as $i) {
        post('/admin/login', ['email' => 'rina@example.com', 'password' => 'wrong']);
    }

    $mail = Mail::queued(SecurityAlert::class)->first();
    expect($mail)->toBeInstanceOf(SecurityAlert::class)
        ->and($mail->envelope()->subject)->toBe('[Kebun Studio] Security: An account was locked after wrong passwords')
        ->and($mail->render())->toContain('Security alert · Kebun Studio')->not->toContain('Acme');
});

it('names the AI server and tells agents the panel\'s name', function () {
    Role::findOrCreate('agent-user', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect']);
    $this->rina->assignRole('agent-user');
    [, $plain] = AiToken::issue((string) $this->rina->id, 'Claude');
    $call = fn (string $method, array $params) => test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], ['Authorization' => "Bearer {$plain}"]);

    $init = $call('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']]);
    expect($init->json('result.serverInfo.name'))->toBe('Kebun Studio')
        ->and($init->json('result.instructions'))->toStartWith('The panel is called Kebun Studio.');

    $info = $call('tools/call', ['name' => 'get_panel_info', 'arguments' => []]);
    expect(json_decode((string) $info->json('result.content.0.text'), true)['panel'])->toBe('Kebun Studio');
});
