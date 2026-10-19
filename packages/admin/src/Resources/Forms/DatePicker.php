<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A date, or a date and time with ->withTime().
 *
 * @api
 */
final class DatePicker extends Field
{
    private bool $withTime = false;

    public function type(): string
    {
        return 'date';
    }

    public function withTime(): self
    {
        $this->withTime = true;

        return $this;
    }

    public function formValue(?Model $record): mixed
    {
        $value = parent::formValue($record);

        return $value instanceof DateTimeInterface ? $value->format($this->withTime ? 'Y-m-d\TH:i' : 'Y-m-d') : $value;
    }

    protected function typeRules(): array
    {
        return ['date'];
    }

    protected function props(): array
    {
        return ['withTime' => $this->withTime];
    }
}
