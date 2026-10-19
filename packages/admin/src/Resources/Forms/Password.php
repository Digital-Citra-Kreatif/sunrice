<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * A new password, hashed before it's stored. It is never sent to the
 * browser. When editing, leaving it empty keeps the current password, so
 * ->required() applies only when creating. The rules are Laravel's
 * `Password::defaults()` (set them in your app's service provider).
 *
 * @api
 */
final class Password extends Field
{
    private bool $confirmed = false;

    /** Typed twice: the form asks for it again, and the two must match. */
    public function confirmed(bool $confirmed = true): self
    {
        $this->confirmed = $confirmed;

        return $this;
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

    protected function typeRules(): array
    {
        return ['string', 'max:255', PasswordRule::default(), ...($this->confirmed ? ['confirmed'] : [])];
    }

    protected function props(): array
    {
        return ['confirmed' => $this->confirmed];
    }
}
