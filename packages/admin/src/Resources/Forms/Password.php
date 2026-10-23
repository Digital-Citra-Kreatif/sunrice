<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Sunrice\Admin\Auth\PasswordPolicy;

/**
 * A new password, hashed before it's stored. It is never sent to the
 * browser. When editing, leaving it empty keeps the current password, so
 * ->required() applies only when creating. The rules are the panel's
 * PasswordPolicy (strong passwords, or the app's `Password::defaults()`
 * when they're off), and the form shows them as a checklist under the input.
 * ->confirmed() asks for it twice: the form shows a second input, sent as
 * "{name}_confirmation", which must match (not when it's left empty).
 *
 * @api
 */
final class Password extends Field
{
    private bool $confirmed = false;

    /** Ask for it twice, so a typo doesn't lock anyone out. */
    public function confirmed(bool $confirmed = true): self
    {
        $this->confirmed = $confirmed;

        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function type(): string
    {
        return 'password';
    }

    public function isRequired(?Model $record): bool
    {
        return $this->required && $record === null;
    }

    public function keepsValueWhenEmpty(): bool
    {
        return true;
    }

    public function formValue(?Model $record): mixed
    {
        return null;
    }

    public function fill(Model $record, mixed $value): void
    {
        if (is_string($value) && $value !== '') {
            $record->setAttribute($this->name, Hash::make($value));
        }
    }

    /** The policy's rules, which refuse the edited account's own email address and name too. */
    public function validationRules(?Model $record): array
    {
        $account = $record instanceof Authenticatable && $record->exists ? $record : null;

        return [...parent::validationRules($record), ...PasswordPolicy::rules($account)];
    }

    protected function typeRules(): array
    {
        return ['string', 'max:255', ...($this->confirmed ? ['confirmed'] : [])];
    }

    protected function props(): array
    {
        return ['confirmed' => $this->confirmed, 'passwordRules' => PasswordPolicy::checklist()];
    }
}
