<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

/**
 * A number: ->integer(), ->min(0), ->max(100), ->step(0.01).
 *
 * @api
 */
final class NumberInput extends Field
{
    private bool $integer = false;

    private int|float|null $min = null;

    private int|float|null $max = null;

    private int|float|null $step = null;

    public function type(): string
    {
        return 'number';
    }

    public function integer(): self
    {
        $this->integer = true;

        return $this;
    }

    public function min(int|float $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function step(int|float $step): self
    {
        $this->step = $step;

        return $this;
    }

    protected function typeRules(): array
    {
        return [
            $this->integer ? 'integer' : 'numeric',
            ...($this->min === null ? [] : ["min:{$this->min}"]),
            ...($this->max === null ? [] : ["max:{$this->max}"]),
        ];
    }

    protected function props(): array
    {
        return ['min' => $this->min, 'max' => $this->max, 'step' => $this->step ?? ($this->integer ? 1 : 'any')];
    }
}
