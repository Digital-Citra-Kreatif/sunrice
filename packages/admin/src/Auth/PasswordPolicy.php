<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;
use Sunrice\Admin\Security\SecuritySettings;

/**
 * The rules for every password the panel sets: creating and editing
 * users, resetting, accepting an invitation, the "Your account" page,
 * the "Choose a stronger password" page and `sunrice:make-admin`.
 *
 * With **Require strong passwords** on (Settings → Security, on by
 * default; `sunrice-admin.auth.strong_passwords` forces it either way), a
 * password needs `auth.password_min` characters (12, never fewer than 8),
 * upper and lower case letters, a number and a symbol, mustn't be the
 * person's email address or name, and mustn't appear in a known data leak
 * (Have I Been Pwned, by k-anonymity; `auth.password_uncompromised`).
 * Off, it's the app's own `Password::defaults()`.
 *
 *     'password' => ['required', 'string', 'max:255', 'confirmed', ...PasswordPolicy::rules($user)],
 *
 * @api
 */
final class PasswordPolicy
{
    /** Whether strong passwords are required: the setting, unless the config forces it. */
    public static function strong(): bool
    {
        $forced = config('sunrice-admin.auth.strong_passwords');

        return is_bool($forced) ? $forced : app(SecuritySettings::class)->strongPasswords;
    }

    /** Whether people whose password fails the policy are asked to change it when they sign in. */
    public static function asksWeakToChange(): bool
    {
        return self::strong() && app(SecuritySettings::class)->changeWeakPasswords;
    }

    /** The shortest strong password: `auth.password_min`, at least 8. */
    public static function minLength(): int
    {
        $min = config('sunrice-admin.auth.password_min', 12);

        return max(8, is_numeric($min) ? (int) $min : 12);
    }

    /** Whether strong passwords are checked against known data leaks. */
    public static function checksLeaks(): bool
    {
        return config('sunrice-admin.auth.password_uncompromised', true) !== false;
    }

    /**
     * The validation rules for a new password (add 'required', 'string',
     * 'max:…' and 'confirmed' as needed). `$for` is the account it is for,
     * when it exists: its email address and name are refused. On a form
     * that also sends "email" and "name", those are refused too.
     *
     * @return list<ValidationRule|Rule>
     */
    public static function rules(?Authenticatable $for = null): array
    {
        return self::strong() ? [new StrongPassword($for)] : [Password::default()];
    }

    /**
     * What a password lacks, keyed by rule ("length", "mixed", "numbers",
     * "symbols", "personal", "leaked"), each with its message; empty when
     * it passes. The leak check runs last and only when everything else
     * passes. Always the strong rules, whether or not they're on.
     *
     * @param  array<mixed>  $form  other input of the form: its "email" and "name" are refused too
     * @return array<string, string>
     */
    public static function problems(string $password, ?Authenticatable $for = null, ?bool $leaks = null, array $form = []): array
    {
        $min = self::minLength();
        $problems = [];
        if (mb_strlen($password) < $min) {
            $problems['length'] = __('Use at least :min characters.', ['min' => $min]);
        }
        if (preg_match('/\p{Lu}/u', $password) !== 1 || preg_match('/\p{Ll}/u', $password) !== 1) {
            $problems['mixed'] = __('Use both upper and lower case letters.');
        }
        if (preg_match('/\p{N}/u', $password) !== 1) {
            $problems['numbers'] = __('Add a number.');
        }
        if (preg_match('/[\p{Z}\p{S}\p{P}]/u', $password) !== 1) {
            $problems['symbols'] = __('Add a symbol, such as ! or #.');
        }
        $typed = mb_strtolower(trim($password));
        foreach ([data_get($for, 'email'), data_get($for, 'name'), $form['email'] ?? null, $form['name'] ?? null] as $personal) {
            if (is_string($personal) && trim($personal) !== '' && mb_strtolower(trim($personal)) === $typed) {
                $problems['personal'] = __('Don\'t use the email address or name as the password.');
                break;
            }
        }
        if ($problems === [] && ($leaks ?? self::checksLeaks()) && app(LeakedPasswords::class)->leaked($password)) {
            $problems['leaked'] = __('This password has appeared in a data leak. Choose a different one.');
        }

        return $problems;
    }

    /**
     * The rules the screens show under a new password, so the checklist
     * matches what is enforced: the strong rules, or what the app's
     * `Password::defaults()` asks for.
     *
     * @return array{min: int, mixedCase: bool, letters: bool, numbers: bool, symbols: bool}
     */
    public static function checklist(): array
    {
        if (self::strong()) {
            return ['min' => self::minLength(), 'mixedCase' => true, 'letters' => false, 'numbers' => true, 'symbols' => true];
        }
        $applied = Password::default()->appliedRules();

        return [
            'min' => is_numeric($applied['min'] ?? null) ? (int) $applied['min'] : 8,
            'mixedCase' => ($applied['mixedCase'] ?? false) === true,
            'letters' => ($applied['letters'] ?? false) === true,
            'numbers' => ($applied['numbers'] ?? false) === true,
            'symbols' => ($applied['symbols'] ?? false) === true,
        ];
    }
}
