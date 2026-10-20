<?php

declare(strict_types=1);

namespace Sunrice\Admin\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Settings → Mail → Send a test email. Sent right away, never queued, so
 * a mail server problem shows on the page.
 *
 * @internal
 */
final class TestMail extends Notification
{
    public function __construct(public readonly string $brand) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Test email from {$this->brand}")
            ->line("This is a test email from the {$this->brand} admin panel.")
            ->line('If you can read it, outgoing mail works: login codes, password resets and security alerts can be delivered.');
    }
}
