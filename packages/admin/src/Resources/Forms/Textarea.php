<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

/** @api */
final class Textarea extends Field
{
    private int $rows = 4;

    private int $maxLength = 65535;

    public function type(): string
    {
        return 'textarea';
    }

    public function rows(int $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;

        return $this;
    }

    protected function typeRules(): array
    {
        return ['string', "max:{$this->maxLength}"];
    }

    protected function props(): array
    {
        return ['rows' => $this->rows, 'maxLength' => $this->maxLength];
    }
}
