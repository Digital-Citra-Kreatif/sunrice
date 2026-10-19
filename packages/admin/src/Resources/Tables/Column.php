<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Tables;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnitEnum;

/**
 * A column of a resource's list. The name is an attribute, or a
 * relationship's attribute ("category.name", eager loaded). Values are
 * formatted on the server; the list only shows them.
 *
 *     Column::make('name')->searchable()->sortable();
 *     Column::make('active')->boolean();
 *     Column::make('status')->badge();
 *     Column::make('created_at')->date();
 *
 * @api
 */
final class Column
{
    private string $label;

    private bool $sortable = false;

    private bool $searchable = false;

    private string $display = 'text';

    private ?string $dateFormat = null;

    private function __construct(public readonly string $name)
    {
        $this->label = Str::headline(str_replace('.', ' ', $name));
    }

    public static function make(string $name): self
    {
        if (preg_match('/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i', $name) !== 1) {
            throw new InvalidArgumentException("Column \"{$name}\": use an attribute (\"name\") or a relationship's attribute (\"category.name\").");
        }

        return new self($name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** Sortable by clicking its header (attributes of the model itself only). */
    public function sortable(bool $sortable = true): self
    {
        if ($sortable && $this->relation() !== null) {
            throw new InvalidArgumentException("Column \"{$this->name}\": only the model's own attributes can be sorted.");
        }
        $this->sortable = $sortable;

        return $this;
    }

    /** Included in the list's search box. */
    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function badge(): self
    {
        $this->display = 'badge';

        return $this;
    }

    public function boolean(): self
    {
        $this->display = 'boolean';

        return $this;
    }

    /** Format dates: ->date() ("19 Oct 2026"), ->date('d/m/Y H:i'). */
    public function date(string $format = 'j M Y'): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /** The relationship to eager load ("category" for "category.name"), if any. */
    public function relation(): ?string
    {
        return str_contains($this->name, '.') ? explode('.', $this->name, 2)[0] : null;
    }

    /** The attribute on the model or the related model. */
    public function attribute(): string
    {
        return str_contains($this->name, '.') ? explode('.', $this->name, 2)[1] : $this->name;
    }

    /** The value shown for a record. */
    public function value(Model $record): string|int|float|bool|null
    {
        $value = data_get($record, $this->name);

        return match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => $value->format($this->dateFormat ?? 'Y-m-d H:i'),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $this->display === 'boolean' => (bool) $value,
            is_bool($value), is_int($value), is_float($value), is_string($value) => $value,
            $value instanceof \Stringable => (string) $value,
            default => json_encode($value) ?: null,
        };
    }

    /** @return array{name: string, label: string, sortable: bool, display: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'label' => $this->label, 'sortable' => $this->sortable, 'display' => $this->display];
    }
}
