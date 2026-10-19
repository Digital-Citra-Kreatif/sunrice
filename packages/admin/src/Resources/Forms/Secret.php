<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Database\Eloquent\Model;

/**
 * A value that is written but never shown again, like an API key: the
 * form starts empty, and leaving it empty keeps the current value, so
 * ->required() applies only when creating. Store it encrypted (an
 * `encrypted` cast, or #[Encrypted] on a settings property).
 *
 * @api
 */
final class Secret extends Field
{
    private int $maxLength = 1000;

    public function type(): string
    {
        return 'password';
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;

        return $this;
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
            $record->setAttribute($this->name, $value);
        }
    }

    protected function typeRules(): array
    {
        return ['string', "max:{$this->maxLength}"];
    }

    protected function props(): array
    {
        return ['autocomplete' => 'off', 'maxLength' => $this->maxLength];
    }
}
