<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Sunrice\Admin\Support\Say;

/**
 * A choice from fixed options: ->options(['draft' => 'Draft', 'live' => 'Live']),
 * or a closure returning them (called when the form is shown and when it's
 * saved, so building the form runs no query), which gets the record being
 * edited (null when creating): ->options(fn (?Model $record) => …). Only
 * the listed values are accepted. ->searchable() adds a search box over
 * the options, for long lists.
 *
 * @api
 */
final class Select extends Field
{
    /** @var array<string|int, string>|Closure(?Model): array<string|int, string> */
    private array|Closure $options = [];

    private bool $searchable = false;

    /** Its option labels are data, shown as written. */
    private bool $verbatim = false;

    /** The record the form is shown or saved for, for an options closure. */
    private ?Model $record = null;

    /** In an action's dialog: the records it runs on aren't offered. */
    private bool $withoutTargets = false;

    /** @param array<string|int, string>|Closure(?Model): array<string|int, string> $options value => label */
    public function options(array|Closure $options): self
    {
        $this->options = $options;

        return $this;
    }

    /** A search box over the options (they're all sent, filtered as one types). */
    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Shows the option labels as written instead of in the panel's
     * language: for options that are data (blueprint titles, roles).
     */
    public function verbatim(bool $verbatim = true): self
    {
        $this->verbatim = $verbatim;

        return $this;
    }

    /**
     * In an action's dialog, leaves out the options whose value is the key
     * of a record the action runs on (a Delete dialog's "Give it to"). The
     * handler still gets to refuse one, for input sent by hand.
     */
    public function withoutTargets(bool $without = true): self
    {
        $this->withoutTargets = $without;

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
            fn (string|int $value, string $label) => ['value' => (string) $value, 'label' => $this->verbatim ? $label : Say::it($label)],
            array_keys($options = $this->resolve()),
            array_values($options),
        )] + ($this->searchable ? ['searchable' => true] : []) + ($this->withoutTargets ? ['withoutTargets' => true] : []);
    }

    /** @return array<string|int, string> */
    private function resolve(): array
    {
        return $this->options instanceof Closure ? ($this->options)($this->record?->exists === true ? $this->record : null) : $this->options;
    }
}
