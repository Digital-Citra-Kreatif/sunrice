<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Users;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Auth\PasswordPolicy;
use Sunrice\Admin\Auth\WeakPasswordAtLogin;
use Sunrice\Admin\Security\SecurityLog;

/**
 * The signed-in user replaces the weak password they just signed in with
 * ("Choose a stronger password"): typed twice, following PasswordPolicy.
 * Their current password isn't asked again, since they've only just typed
 * it. Ends "Remember me" on other computers and the request to change
 * it in every session, and is the security event
 * "weak password changed".
 *
 * @internal
 */
final class ChooseStrongerPassword
{
    public function __construct(private SecurityLog $security, private WeakPasswordAtLogin $weak) {}

    /**
     * @param  array<mixed>  $input  password, password_confirmation
     *
     * @throws ValidationException
     */
    public function handle(Model&Authenticatable $user, array $input): void
    {
        /** @var array{password: string} $validated */
        $validated = Validator::make($input, [
            'password' => ['required', 'string', 'max:255', 'confirmed', ...PasswordPolicy::rules($user)],
        ], [], ['password' => __('new password')])->validate();

        $user->forceFill(['password' => Hash::make($validated['password']), 'remember_token' => Str::random(60)])->save();
        $this->weak->clear($user);
        $this->security->record('weak password changed', $user);
    }
}
