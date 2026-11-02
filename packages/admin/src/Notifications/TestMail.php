<?php

declare(strict_types=1);

namespace Sunrice\Admin\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Sunrice\Admin\Panel;

/**
 * Settings → Mail → Send a test email. Sent right away, never queued, so
 * a mail server problem shows on the page.
 *
 * @internal
 */
final class TestMail extends Notification
{
    /** With the brand of Panel::mailBrand() at the top and bottom (its name signs the email too). */
    private const VIEW = 'sunrice-admin::mail.notification';

    public function __construct(public readonly string $brand) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->markdown(self::VIEW)
            ->subject(__('Test email from :brand', ['brand' => $this->brand]))
            ->line(__('This is a test email from the :brand admin panel.', ['brand' => $this->brand]))
            ->line(__('If you can read it, outgoing mail works: login codes, password resets and security alerts can be delivered.'))
            ->salutation(__('Regards,')."  \n".app(Panel::class)->mailBrand()->name);
    }
}
