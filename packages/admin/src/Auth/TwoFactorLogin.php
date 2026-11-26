<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Sunrice\Admin\Notifications\LoginCode;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecurityLog;
use Sunrice\Admin\Security\SecuritySettings;
use Throwable;

/**
 * Two-factor login by email: after the right password the user isn't
 * signed in yet. A 6-digit code is emailed, and the pending login waits in
 * the session until the code is typed.
 *
 * The code is an EmailCodes one (purpose "two-factor"): only a keyed hash
 * of it is kept, it works for LIFETIME_MINUTES and allows MAX_ATTEMPTS
 * guesses; wrong codes also count per account across logins (LOCKOUT_*),
 * so logging in again for a fresh code doesn't give endless guesses.
 *
 * @internal
 */
final class TwoFactorLogin
{
    public const SESSION_KEY = 'sunrice_code_two_factor';

    /** Its EmailCodes purpose (session key SESSION_KEY, lockout "sunrice-code-two-factor|{user}"). */
    public const PURPOSE = 'two-factor';

    public const LIFETIME_MINUTES = EmailCodes::LIFETIME_MINUTES;

    public const MAX_ATTEMPTS = EmailCodes::MAX_ATTEMPTS;

    public const RESEND_SECONDS = EmailCodes::RESEND_SECONDS;

    public const LOCKOUT_ATTEMPTS = EmailCodes::LOCKOUT_ATTEMPTS;

    public const LOCKOUT_SECONDS = EmailCodes::LOCKOUT_SECONDS;

    public function __construct(private readonly Panel $panel, private readonly EmailCodes $codes) {}

    /** On in Settings → Security, unless the sunrice-admin.auth.two_factor config forces it on or off. */
    public static function enabled(): bool
    {
        $forced = config('sunrice-admin.auth.two_factor');

        return is_bool($forced) ? $forced : app(SecuritySettings::class)->twoFactor;
    }

    /** Seconds until this account may try codes again; 0 when it may. */
    public function lockedFor(Authenticatable $user): int
    {
        return $this->codes->lockedFor(self::PURPOSE, self::id($user));
    }

    /**
     * Starts a pending login and emails its code, once the response has gone
     * (a slow mail server doesn't hold the code page up). It throws only
     * when the account has no email address; a mail that fails afterwards is
     * reported and logged (`login code not sent`), and Resend tries again.
     */
    public function start(Request $request, Authenticatable $user, bool $remember): void
    {
        $this->send($request, $user, $remember);
    }

    /** Emails a new code for the pending login; the old one stops working. */
    public function resend(Request $request): void
    {
        $pending = $this->pending($request);
        $user = $this->user($request);
        if ($pending !== null && $user !== null) {
            $this->send($request, $user, $pending['remember']);
        }
    }

    /**
     * The pending login: user_id, remember, expires_at, sent_at, attempts.
     *
     * @return array{user_id: int|string, remember: bool, expires_at: int, sent_at: int, attempts: int}|null
     */
    public function pending(Request $request): ?array
    {
        $pending = $this->codes->pending($request, self::PURPOSE);
        if ($pending === null) {
            return null;
        }

        return [
            'user_id' => $pending['user_id'],
            'remember' => (bool) ($pending['data']['remember'] ?? false),
            'expires_at' => $pending['expires_at'],
            'sent_at' => $pending['sent_at'],
            'attempts' => $pending['attempts'],
        ];
    }

    public function user(Request $request): ?Authenticatable
    {
        $pending = $this->pending($request);

        return $pending === null ? null : $this->guard()->getProvider()->retrieveById($pending['user_id']);
    }

    /** Seconds until another code may be sent. */
    public function resendIn(Request $request): int
    {
        return $this->codes->resendIn($request, self::PURPOSE);
    }

    /**
     * Checks a code; the right one completes the pending login.
     *
     * @return 'ok'|'wrong'|'expired'|'locked'|'missing'
     */
    public function verify(Request $request, string $code): string
    {
        $pending = $this->pending($request);
        $user = $this->user($request);
        if ($pending === null || $user === null) {
            return 'missing';
        }

        $result = $this->codes->check($request, self::PURPOSE, $code);
        if ($result === 'ok') {
            $this->guard()->login($user, $pending['remember']);
            $request->session()->regenerate();
        }

        return $result;
    }

    public function forget(Request $request): void
    {
        $this->codes->forget($request, self::PURPOSE);
    }

    private function send(Request $request, Authenticatable $user, bool $remember): void
    {
        $email = data_get($user, 'email');
        if (! is_string($email) || $email === '') {
            throw new \RuntimeException('The account has no email address to send a login code to.');
        }
        $code = $this->codes->issue($request, self::PURPOSE, self::id($user), ['remember' => $remember]);
        $notification = new LoginCode($code, $this->panel->brandName());

        // After the response, not on the queue: it goes out even without a queue worker,
        // and a slow mail server doesn't keep the person waiting for the code page.
        defer(function () use ($email, $notification, $user): void {
            try {
                Notification::route('mail', $email)->notifyNow($notification);
            } catch (Throwable $e) {
                report($e);
                app(SecurityLog::class)->record('login code not sent', $user instanceof Model ? $user : null, null, ['note' => 'the mail failed: '.mb_substr($e->getMessage(), 0, 200)]);
            }
        }, 'sunrice-login-code', always: true);
    }

    private static function id(Authenticatable $user): int|string
    {
        $id = $user->getAuthIdentifier();

        return is_int($id) || is_string($id) ? $id : (is_scalar($id) ? (string) $id : '');
    }

    private function guard(): SessionGuard
    {
        $guard = Auth::guard($this->panel->getGuard());
        if (! $guard instanceof SessionGuard) {
            throw new \LogicException("The panel's guard \"{$this->panel->getGuard()}\" must be a session guard.");
        }

        return $guard;
    }
}
