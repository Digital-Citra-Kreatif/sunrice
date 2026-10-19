<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Mail\TestEmail;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->brand('Acme'));
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com', 'mail.mailers.smtp.port' => 587, 'mail.from' => ['address' => 'hello@example.com', 'name' => 'Acme']]);
});

it('shows how email is sent and sends a test', function () {
    Mail::fake();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.email.test'));

    get('/admin/email')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Email/Index', shouldExist: false)
        ->where('mailer', 'smtp')->where('server', 'smtp.example.com:587')->where('sends', true)
        ->where('from', ['address' => 'hello@example.com', 'name' => 'Acme'])->where('to', fn ($to) => str_ends_with((string) $to, '@example.com')));

    post('/admin/email', ['to' => 'ops@example.com'])->assertSessionHas('success', 'Sent a test email to ops@example.com.');
    Mail::assertSent(TestEmail::class, fn (TestEmail $mail) => $mail->hasTo('ops@example.com') && $mail->brand === 'Acme' && $mail->sender === 'Rina');
    post('/admin/email', ['to' => 'not an address'])->assertSessionHasErrors('to');
});

it('says when the mailer only logs, and when sending fails', function () {
    config(['mail.default' => 'log']);
    actingAs(adminUser(Panel::ACCESS, 'sunrice.email.test'));
    get('/admin/email')->assertInertia(fn (Assert $page) => $page->where('sends', false)->where('server', null));
    post('/admin/email', ['to' => 'ops@example.com'])->assertSessionHas('success', 'Wrote the test email to ops@example.com to the log: this mailer doesn\'t send.');

    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    post('/admin/email', ['to' => 'ops@example.com'])->assertSessionHas('error', fn (string $error) => str_starts_with($error, 'The email could not be sent'));
});

it('is for those allowed only', function () {
    actingAs(adminUser(Panel::ACCESS));
    get('/admin/email')->assertForbidden();
    post('/admin/email', ['to' => 'ops@example.com'])->assertForbidden();
});
