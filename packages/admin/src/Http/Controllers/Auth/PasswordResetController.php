<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Notifications\ResetPanelPassword;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecurityLog;

/**
 * "Forgot password?" and the reset form, through Laravel's password broker.
 *
 * @internal
 */
final class PasswordResetController
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request, SecurityLog $security): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $email = $request->string('email')->toString();
        $account = $security->findAccount($email);
        $security->record('password reset requested', $account, $account === null ? $email : null, $account === null ? ['note' => 'no account with this email'] : []);

        self::broker()->sendResetLink(
            ['email' => $request->string('email')->toString()],
            function (CanResetPassword $user, string $token): void {
                $email = $user->getEmailForPasswordReset();
                Notification::route('mail', $email)->notify(new ResetPanelPassword($token, $email, app(Panel::class)->brandName()));
            },
        );

        // The same answer whether or not the address has an account, so the
        // form can't be used to find out who has one.
        return back()->with('success', __('If an account exists for that email, a password reset link is on its way.'));
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
            // From an invitation: choosing a first password, not a new one (wording only).
            'invited' => $request->boolean('invited'),
        ]);
    }

    public function update(Request $request, SecurityLog $security): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'max:1000', PasswordRule::defaults()],
        ]);

        $status = self::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (CanResetPassword $user, string $password) use ($security): void {
                if (! $user instanceof Model || ! $user instanceof Authenticatable) {
                    return;
                }
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
                $security->record('password reset', $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // An unknown email reads like a wrong token, so the form can't be used to find out who has an account.
            throw ValidationException::withMessages(['email' => __($status === Password::INVALID_USER ? Password::INVALID_TOKEN : $status)]);
        }

        return redirect()->route('sunrice.admin.login')->with('success', $request->boolean('invited')
            ? __('Your password is set. You can log in now.')
            : __('Your password has been reset. You can log in now.'));
    }

    private static function broker(): PasswordBroker
    {
        $name = config('sunrice-admin.auth.passwords');
        $broker = Password::broker(is_string($name) ? $name : null);
        if (! $broker instanceof PasswordBroker) {
            throw new \LogicException('The panel needs Laravel\'s password broker for password resets.');
        }

        return $broker;
    }
}
