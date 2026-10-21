<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Validation\Rule;

/**
 * A choice from fixed options: ->options(['draft' => 'Draft', 'live' => 'Live']),
 * or a closure returning them (called when the form is shown and when it's
 * saved, so building the form runs no query). Only the listed values are
 * accepted. ->searchable() adds a search box over the options, for long
 * lists.
 *
 * @api
 */
final class Select extends Field
{
    /** @var array<string|int, string>|Closure(): array<string|int, string> */
    private array|Closure $options = [];

    private bool $searchable = false;

    /** @param array<string|int, string>|Closure(): array<string|int, string> $options value => label */
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

    public function type(): string
    {
        return 'select';
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
        )] + ($this->searchable ? ['searchable' => true] : []);
    }

    /** @return array<string|int, string> */
    private function resolve(): array
    {
        return $this->options instanceof Closure ? ($this->options)() : $this->options;
    }
}
