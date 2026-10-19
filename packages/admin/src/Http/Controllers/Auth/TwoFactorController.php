<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Auth\TwoFactorLogin;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;

/**
 * The second step of two-factor login: the code from the email.
 *
 * @internal
 */
final class TwoFactorController
{
    public function __construct(private readonly TwoFactorLogin $twoFactor) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->twoFactor->user($request);
        if ($user === null) {
            return redirect()->route('sunrice.admin.login');
        }
        $email = data_get($user, 'email');

        return Inertia::render('Auth/TwoFactor', [
            'email' => self::mask(is_string($email) ? $email : ''),
            'resendIn' => $this->twoFactor->resendIn($request),
            'minutes' => TwoFactorLogin::LIFETIME_MINUTES,
        ]);
    }

    public function store(Request $request, SecurityLog $security): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $code = $request->string('code')->toString();

        // Who is logging in, read before verify() ends the pending login.
        $account = $this->twoFactor->user($request);
        $account = $account instanceof Model ? $account : null;
        $result = $this->twoFactor->verify($request, $code);
        match ($result) {
            'ok' => $security->record('logged in', $account, null, ['note' => 'with a login code']),
            'wrong' => $security->record('login failed', $account, null, ['note' => 'wrong login code']),
            'locked' => $security->record('login blocked', $account, null, ['note' => 'too many wrong login codes']),
            default => null,
        };

        if ($result === 'ok') {
            // Sessions that signed in before "sign out everywhere" end (AccountGuard).
            $request->session()->put(AccountGuard::LOGIN_AT, now()->getTimestamp());

            return redirect()->intended(route('sunrice.admin.dashboard'));
        }
        if ($result === 'wrong') {
            throw ValidationException::withMessages(['code' => __('That code isn\'t right. Check the latest email and try again.')]);
        }

        return redirect()->route('sunrice.admin.login')->with('error', match ($result) {
            'expired' => __('The login code expired. Log in again for a new one.'),
            'locked' => __('Too many wrong codes. Log in again later for a new one.'),
            default => __('Log in first.'),
        });
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($this->twoFactor->pending($request) === null) {
            return redirect()->route('sunrice.admin.login');
        }
        if (($wait = $this->twoFactor->resendIn($request)) > 0) {
            return back()->with('error', __('Wait :seconds seconds before asking for another code.', ['seconds' => $wait]));
        }
        try {
            $this->twoFactor->resend($request);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('We couldn\'t email a new code. Ask an administrator to check that the app can send email.'));
        }

        return back()->with('success', __('A new code is on its way.'));
    }

    /** j•••@example.com: enough to recognise the inbox without spelling it out. */
    public static function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).str_repeat('•', max(2, min(6, mb_strlen($name) - 1))).($domain !== '' ? '@'.$domain : '');
    }
}
