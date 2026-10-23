<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Users;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Auth\PasswordPolicy;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecurityLog;

/**
 * The signed-in user changes their own name, email address or password
 * (the Your account page). A new email address or password needs the
 * current password; the password is typed twice. A new password or email
 * address is a security event ("password changed", "email changed"), and
 * a new password ends "Remember me" on other computers.
 *
 * @internal
 */
final class UpdateAccount
{
    public function __construct(private Panel $panel, private SecurityLog $security) {}

    /**
     * @param  array<mixed>  $input  name, email, current_password, password, password_confirmation
     * @return list<string> what changed: name, email, password
     *
     * @throws ValidationException
     */
    public function handle(Model $user, array $input): array
    {
        $email = $user->getAttribute('email');
        $changesEmail = is_string($input['email'] ?? null) && $input['email'] !== $email;
        $changesPassword = is_string($input['password'] ?? null) && $input['password'] !== '';

        /** @var array{name: string, email: string, password?: string|null} $validated */
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($user->getTable(), 'email')->ignore($user->getKey(), $user->getKeyName())],
            'current_password' => $changesEmail || $changesPassword ? ['required', 'string', 'current_password:'.$this->panel->getGuard()] : ['nullable'],
            'password' => ['nullable', 'string', 'max:255', 'confirmed', ...PasswordPolicy::rules($user instanceof Authenticatable ? $user : null)],
        ], [
            'current_password.required' => 'Type your current password to change your email address or password.',
            'current_password.current_password' => 'That isn\'t your current password.',
        ], ['password' => 'new password'])->validate();

        $changed = [];
        if ($validated['name'] !== $user->getAttribute('name')) {
            $changed[] = 'name';
        }
        if ($changesEmail) {
            $changed[] = 'email';
        }
        $user->setAttribute('name', $validated['name']);
        $user->setAttribute('email', $validated['email']);
        if ($changesPassword && is_string($validated['password'] ?? null)) {
            $user->setAttribute('password', Hash::make($validated['password']));
            $user->setAttribute('remember_token', Str::random(60));
            $changed[] = 'password';
        }
        $user->save();

        if ($changesEmail) {
            $this->security->record('email changed', $user, null, ['note' => (is_string($email) ? $email : '').' → '.$validated['email']]);
        }
        if ($changesPassword) {
            $this->security->record('password changed', $user);
        }

        return $changed;
    }
}
