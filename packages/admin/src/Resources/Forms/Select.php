<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Validation\Rule;

/**
 * A choice from fixed options: ->options(['draft' => 'Draft', 'live' => 'Live']).
 * Only the listed values are accepted.
 *
 * @api
 */
final class Select extends Field
{
    /** @var array<string|int, string> */
    private array $options = [];

    /** @param array<string|int, string> $options value => label */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function type(): string
    {
        return 'select';
    }

    protected function typeRules(): array
    {
        return [Rule::in(array_map('strval', array_keys($this->options)))];
    }

    protected function props(): array
    {
        return ['options' => array_map(
            fn (string|int $value, string $label) => ['value' => (string) $value, 'label' => $label],
            array_keys($this->options),
            array_values($this->options),
        )];
    }
}
