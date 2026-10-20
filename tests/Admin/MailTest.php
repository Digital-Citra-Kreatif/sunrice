<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Notifications\TestMail;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Settings\Settings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    usePanel();
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'mail.example.com', 'port' => 587, 'password' => 'hunter2'],
        'mail.from' => ['address' => 'admin@example.com', 'name' => 'Acme'],
        'queue.default' => 'database',
    ]);
});

it('shows how mail is sent on Settings → Mail, without passwords or a form', function () {
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.mail'));

    get('/admin/settings')->assertRedirect('/admin/settings/mail');
    $response = get('/admin/settings/mail')->assertInertia(fn (Assert $page) => $page
        ->component('Settings/Edit', false)
        ->where('section.key', 'mail')
        ->where('sections', [['key' => 'mail', 'label' => 'Mail']])
        ->where('form', [])
        ->where('cards.0.title', 'How mail is sent')
        ->where('cards.0.blocks.0.rows', [
            ['label' => 'Mailer', 'value' => 'smtp', 'type' => 'text'],
            ['label' => 'Server', 'value' => 'mail.example.com:587', 'type' => 'text'],
            ['label' => 'From', 'value' => 'Acme <admin@example.com>', 'type' => 'text'],
            ['label' => 'Queue connection', 'value' => 'database (queued mail needs a running queue worker)', 'type' => 'text'],
        ])
        ->where('cards.0.buttons.0', fn ($button) => $button['label'] === 'Send a test email' && $button['url'] === '/admin/settings/mail/test'));
    expect($response->getContent())->not->toContain('hunter2');

    // A mailer that delivers nothing says so.
    config(['mail.default' => 'log', 'mail.mailers.log' => ['transport' => 'log']]);
    get('/admin/settings/mail')->assertInertia(fn (Assert $page) => $page
        ->where('cards.0.blocks.1.type', 'notice')
        ->where('cards.0.blocks.1.tone', 'warning'));
});

it('sends a test email to the signed-in user, rate limited', function () {
    Notification::fake();
    $user = adminUser(Panel::ACCESS, 'sunrice.settings.mail');
    actingAs($user);

    post('/admin/settings/mail/test')->assertRedirect()
        ->assertSessionHas('success', "Test email sent to {$user->email}. Check the inbox (and the spam folder).");
    Notification::assertSentOnDemand(TestMail::class, function (TestMail $mail, array $channels, object $notifiable) use ($user) {
        return $notifiable->routes['mail'] === $user->email
            && $mail->toMail($notifiable)->subject === 'Test email from Admin';
    });

    post('/admin/settings/mail/test');
    post('/admin/settings/mail/test');
    post('/admin/settings/mail/test')->assertSessionHas('error', fn (string $error) => str_starts_with($error, 'Too many test emails.'));
    Notification::assertSentOnDemandTimes(TestMail::class, 3);
});

it('flashes why a test email failed', function () {
    config(['mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.mail'));

    post('/admin/settings/mail/test')->assertSessionHas('error', fn (string $error) => str_starts_with($error, 'Sending failed: '));
});

it('guards the mail settings and the test email with sunrice.settings.mail', function () {
    Notification::fake();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.security'));

    get('/admin/settings/mail')->assertForbidden();
    post('/admin/settings/mail/test')->assertForbidden();
    Notification::assertNothingSent();
    expect(app(Panel::class)->getPermissions())->toHaveKey('sunrice.settings.mail')
        ->and(app(Panel::class)->isPrivilegedPermission('sunrice.settings.mail'))->toBeTrue();
});

it('keeps the mail key for the panel', function () {
    $panel = Panel::make()->settings(OwnMailSettings::class);
    expect(fn () => $panel->boot())->toThrow(PanelMisconfigured::class, 'The settings key "mail" belongs to the panel');
});

final class OwnMailSettings extends Settings
{
    public static function key(): string
    {
        return 'mail';
    }

    public static function form(Form $form): Form
    {
        return $form;
    }
}
