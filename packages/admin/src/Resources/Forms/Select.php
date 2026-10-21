<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Validation\Rule;
use Sunrice\Admin\Support\Say;

/**
 * A choice from fixed options: ->options(['draft' => 'Draft', 'live' => 'Live']),
 * or a closure returning them (called when the form is shown and when it's
 * saved, so building the form runs no query). Only the listed values are
 * accepted.
 *
 * @api
 */
final class Select extends Field
{
    /** @var array<string|int, string>|Closure(): array<string|int, string> */
    private array|Closure $options = [];

    /** Its option labels are data, shown as written. */
    private bool $verbatim = false;

    /** @param array<string|int, string>|Closure(): array<string|int, string> $options value => label */
    public function options(array|Closure $options): self
    {
        $this->options = $options;

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
            fn (string|int $value, string $label) => ['value' => (string) $value, 'label' => $this->verbatim ? $label : Say::it($label)],
            array_keys($options = $this->resolve()),
            array_values($options),
        )];
    }

    /** @return array<string|int, string> */
    private function resolve(): array
    {
        return $this->options instanceof Closure ? ($this->options)() : $this->options;
    }
}
