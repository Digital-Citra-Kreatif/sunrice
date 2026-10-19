<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
 *
 * Values that aren't one of the options are ignored.
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

    private function __construct(public readonly string $name)
    {
        $this->label = Str::headline($name);
    }

    public static function make(string $name): self
    {
        return new self($name);
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
        $builder->where($builder->qualifyColumn($this->name), $this->boolean ? $value === '1' : $value);
    }

    /** @return array{name: string, label: string, options: list<array{value: string, label: string}>} */
    public function toArray(): array
    {
        $options = [];
        foreach ($this->resolveOptions() as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return ['name' => $this->name, 'label' => $this->label, 'options' => $options];
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
