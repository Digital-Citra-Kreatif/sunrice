<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * A choice from fixed options: ->options(['draft' => 'Draft', 'live' => 'Live']),
 * or a closure returning them (called when the form is shown and when it's
 * saved, so building the form runs no query), which gets the record being
 * edited (null when creating): ->options(fn (?Model $record) => …). Only
 * the listed values are accepted.
 *
 * @api
 */
final class Select extends Field
{
    /** @var array<string|int, string>|Closure(?Model): array<string|int, string> */
    private array|Closure $options = [];

    /** The record the form is shown or saved for, for an options closure. */
    private ?Model $record = null;

    /** @param array<string|int, string>|Closure(?Model): array<string|int, string> $options value => label */
    public function options(array|Closure $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function type(): string
    {
        return 'select';
    }

    public function toArray(?Model $record = null): array
    {
        $this->record = $record;

        return parent::toArray($record);
    }

    public function validationRules(?Model $record): array
    {
        $this->record = $record;

        return parent::validationRules($record);
    }

    protected function typeRules(): array
    {
        return [Rule::in(array_map('strval', array_keys($this->resolve())))];
    }

    protected function props(): array
    {
        return ['options' => array_map(
            fn (string|int $value, string $label) => ['value' => (string) $value, 'label' => $label],
            array_keys($options = $this->resolve()),
            array_values($options),
        )];
    }

    /** @return array<string|int, string> */
    private function resolve(): array
    {
        return $this->options instanceof Closure ? ($this->options)($this->record?->exists === true ? $this->record : null) : $this->options;
    }
}
