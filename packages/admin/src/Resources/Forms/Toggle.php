<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Database\Eloquent\Model;

/**
 * An on/off switch, stored as a boolean.
 *
 * @api
 */
final class Toggle extends Field
{
    protected mixed $default = false;

    public function type(): string
    {
        return 'toggle';
    }

    /** A switch always has a value: off is false, not missing. */
    public function validationRules(Model $record): array
    {
        return ['boolean', ...$this->rules];
    }

    public function formValue(?Model $record): mixed
    {
        return (bool) parent::formValue($record);
    }

    public function fill(Model $record, mixed $value): void
    {
        $record->setAttribute($this->name, (bool) $value);
    }

    protected function typeRules(): array
    {
        return ['boolean'];
    }
}
