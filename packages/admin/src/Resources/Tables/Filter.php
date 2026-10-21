<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sunrice\Admin\Support\Say;

/**
 * A filter above a resource's list: a select of options, applied to the
 * query. By default it matches an attribute; ->query() applies it any way.
 *
 *     Filter::make('status')->options(['draft' => 'Draft', 'live' => 'Live']);
 *     Filter::make('active')->boolean();
 *     Filter::make('role')->options(fn () => Role::pluck('name', 'name')->all())
 *         ->query(fn (Builder $q, string $role) => $q->whereRelation('roles', 'name', $role));
 *     Filter::make('price')->options(['cheap' => 'Under 10'])
 *         ->query(fn (Builder $q, string $value) => $q->where('price', '<', 10));
 *     Filter::dateRange('created_at')->label('Created');
 *
 * Values that aren't one of the options are ignored. A date range's value
 * is "from,to" (dates as 2026-01-31, either may be empty); it matches the
 * attribute's date between them, both days included, or what ->query()
 * does with the value (Filter::range() reads it).
 *
 * @api
 */
final class Filter
{
    private string $label;

    /** @var array<string|int, string>|Closure(): array<string|int, string> */
    private array|Closure $options = [];

    /** @var (Closure(Builder<Model>, string): mixed)|null */
    private ?Closure $query = null;

    private bool $boolean = false;

    /** 'select' or 'date_range'. */
    private string $type = 'select';

    private function __construct(public readonly string $name)
    {
        $this->label = Str::headline($name);
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** From one date to another (either end open), on a date or datetime attribute. */
    public static function dateRange(string $name): self
    {
        $filter = new self($name);
        $filter->type = 'date_range';

        return $filter;
    }

    /**
     * A date range filter's value as its two dates (null for an open end),
     * or null when it isn't one.
     *
     * @return array{0: string|null, 1: string|null}|null
     */
    public static function range(string $value): ?array
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})?,(\d{4}-\d{2}-\d{2})?$/', $value, $match) !== 1) {
            return null;
        }
        $from = ($match[1] ?? '') === '' ? null : $match[1];
        $to = ($match[2] ?? '') === '' ? null : $match[2];
        foreach ([$from, $to] as $date) {
            if ($date !== null && ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                return null;
            }
        }

        return $from === null && $to === null ? null : [$from, $to];
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * value => label, or a closure returning them (called when the list
     * is shown).
     *
     * @param  array<string|int, string>|Closure(): array<string|int, string>  $options
     */
    public function options(array|Closure $options): self
    {
        $this->options = $options;

        return $this;
    }

    /** Yes / No for a boolean attribute. */
    public function boolean(string $yes = 'Yes', string $no = 'No'): self
    {
        $this->boolean = true;

        return $this->options(['1' => $yes, '0' => $no]);
    }

    /** @param Closure(Builder<Model>, string): mixed $query */
    public function query(Closure $query): self
    {
        $this->query = $query;

        return $this;
    }

    /** Whether the value is one of its options. */
    public function accepts(string $value): bool
    {
        if ($this->type === 'date_range') {
            return self::range($value) !== null;
        }

        return array_key_exists($value, $this->resolveOptions());
    }

    /** @param Builder<Model> $builder */
    public function apply(Builder $builder, string $value): void
    {
        if (! $this->accepts($value)) {
            return;
        }
        if ($this->query !== null) {
            ($this->query)($builder, $value);

            return;
        }
        if ($this->type === 'date_range') {
            [$from, $to] = self::range($value) ?? [null, null];
            $column = $builder->qualifyColumn($this->name);
            $builder->when($from !== null, fn (Builder $q) => $q->whereDate($column, '>=', (string) $from))
                ->when($to !== null, fn (Builder $q) => $q->whereDate($column, '<=', (string) $to));

            return;
        }
        $builder->where($builder->qualifyColumn($this->name), $this->boolean ? $value === '1' : $value);
    }

    /** @return array{name: string, label: string, type: string, options: list<array{value: string, label: string}>} */
    public function toArray(): array
    {
        $options = [];
        foreach ($this->type === 'date_range' ? [] : $this->resolveOptions() as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => Say::it($label)];
        }

        return ['name' => $this->name, 'label' => Say::it($this->label), 'type' => $this->type, 'options' => $options];
    }

    /** @return array<string, string> */
    private function resolveOptions(): array
    {
        $options = [];
        foreach ($this->options instanceof Closure ? ($this->options)() : $this->options as $value => $label) {
            $options[(string) $value] = $label;
        }

        return $options;
    }
}
