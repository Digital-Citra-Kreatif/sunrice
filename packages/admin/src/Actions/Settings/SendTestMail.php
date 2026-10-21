<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Settings;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Sunrice\Admin\Notifications\TestMail;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Settings\MailSettings;
use Throwable;

/**
 * Sends the test email to an address right away (never queued), at most
 * PER_MINUTE times a minute per person. Returns what to tell them; throws
 * ActionFailed with the reason when it can't.
 *
 * @internal
 */
final class SendTestMail
{
    public const PER_MINUTE = 3;

    public function __construct(private Panel $panel) {}

    public function handle(string $email, string $limitKey): string
    {
        $key = 'sunrice-test-mail|'.$limitKey;
        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            throw new ActionFailed(__('Too many test emails. Try again in :seconds seconds.', ['seconds' => max(1, RateLimiter::availableIn($key))]));
        }
        RateLimiter::hit($key, 60);

        try {
            Notification::route('mail', $email)->notifyNow(new TestMail($this->panel->brandName()));
        } catch (Throwable $e) {
            report($e);

            throw new ActionFailed(__('Sending failed: :error', ['error' => $e->getMessage()]));
        }

        return MailSettings::info()['delivers']
            ? __('Test email sent to :email. Check the inbox (and the spam folder).', ['email' => $email])
            : __('Test email for :email written to the log: the current mailer doesn\'t deliver email.', ['email' => $email]);
    }
}
