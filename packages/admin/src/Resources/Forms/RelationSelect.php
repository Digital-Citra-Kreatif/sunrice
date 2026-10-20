<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Sunrice\Admin\Support\Like;

/**
 * A searchable choice of related records (BelongsToSelect,
 * BelongsToManySelect). The field's name is the model's relation; the
 * form searches the related records as the person types, through the
 * resource's options route (`GET admin/resources/{key}/options/{field}?q=`,
 * guarded by the resource's `view`), at most 50 at a time.
 *
 *     BelongsToSelect::make('category')->title('name');
 *     BelongsToSelect::make('author')->title(fn (User $user) => "{$user->name} ({$user->email})")->searchColumns('name', 'email');
 *     BelongsToManySelect::make('tags')->query(fn (Builder $query) => $query->where('active', true));
 *
 * Only records the related query reaches (narrowed with ->query()) can be
 * found or chosen. For resource forms only (not actions' or settings').
 *
 * @api
 */
abstract class RelationSelect extends Field
{
    /** The most options one search returns. */
    public const LIMIT = 50;

    /** @var string|Closure(Model): string */
    protected string|Closure $title = 'name';

    /** @var list<string> */
    protected array $searchColumns = [];

    /** @var (Closure(Builder<Model>): mixed)|null */
    protected ?Closure $scope = null;

    /**
     * How a related record is named: a column (searched and sorted by), or
     * a closure (then search ->searchColumns()). "name" by default.
     *
     * @param  string|Closure(Model): string  $title
     */
    public function title(string|Closure $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** The related model's columns a search looks in (the title column by default). */
    public function searchColumns(string ...$columns): static
    {
        $this->searchColumns = array_values($columns);

        return $this;
    }

    /**
     * Narrow the records that can be found and chosen.
     *
     * @param  Closure(Builder<Model>): mixed  $scope
     */
    public function query(Closure $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    /** @return Relation<Model, Model, mixed> */
    abstract protected function relation(Model $model): Relation;

    /**
     * The related records that may be chosen.
     *
     * @return Builder<Model>
     */
    public function relatedQuery(Model $model): Builder
    {
        $query = $this->relation($model)->getRelated()->newQuery();
        if ($this->scope !== null) {
            ($this->scope)($query);
        }

        return $query;
    }

    /**
     * Options matching what was typed (all, up to the limit, for nothing),
     * for a record of the resource's model.
     *
     * @return list<array{value: int|string, label: string}>
     */
    public function search(Model $model, string $term): array
    {
        $query = $this->relatedQuery($model);
        $columns = $this->searchColumns !== [] ? $this->searchColumns : (is_string($this->title) ? [$this->title] : []);
        if ($term !== '' && $columns !== []) {
            $like = '%'.Like::escape($term).'%';
            $query->where(function (Builder $where) use ($columns, $like): void {
                foreach ($columns as $column) {
                    $where->orWhereLike($where->qualifyColumn($column), $like);
                }
            });
        }
        $query->orderBy($query->qualifyColumn(is_string($this->title) ? $this->title : $query->getModel()->getKeyName()));

        return array_values($query->limit(self::LIMIT)->get()->map(fn (Model $related): array => $this->option($related))->all());
    }

    /** @return array{value: int|string, label: string} */
    protected function option(Model $related): array
    {
        $key = $related->getKey();
        $value = is_int($key) || is_string($key) ? $key : '';
        if ($this->title instanceof Closure) {
            $label = ($this->title)($related);
        } else {
            $title = $related->getAttribute($this->title);
            $label = is_scalar($title) ? (string) $title : '';
        }

        return ['value' => $value, 'label' => $label !== '' ? $label : '#'.$value];
    }

    /**
     * A rule accepting only keys of records the related query reaches, all
     * looked up in one query.
     *
     * @return Closure(string, mixed, Closure(string): void): void
     */
    protected function existsRule(?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            $keys = array_values(array_unique(array_filter(is_array($value) ? $value : [$value], fn (mixed $key): bool => is_int($key) || is_string($key))));
            $count = is_array($value) ? count($value) : 1;
            if ($record === null || count($keys) !== $count) {
                $fail('The selected :attribute is invalid.');

                return;
            }
            if ($keys !== [] && $this->relatedQuery($record)->whereKey($keys)->count() !== count($keys)) {
                $fail('The selected :attribute is invalid.');
            }
        };
    }

    /** @return Relation<Model, Model, mixed> */
    protected function resolveRelation(Model $model): Relation
    {
        if (! method_exists($model, $this->name)) {
            throw new InvalidArgumentException('Field "'.$this->name.'": '.$model::class.' has no relation of that name.');
        }
        $relation = $model->{$this->name}();
        if (! $relation instanceof Relation) {
            throw new InvalidArgumentException('Field "'.$this->name.'": '.$model::class.'::'.$this->name.'() isn\'t a relation.');
        }

        /** @var Relation<Model, Model, mixed> */
        return $relation;
    }

    public function toArray(?Model $record = null): array
    {
        return [...parent::toArray($record), 'selected' => $record === null ? [] : $this->selected($record)];
    }

    /**
     * The chosen records' options, so the form can name them before any search.
     *
     * @return list<array{value: int|string, label: string}>
     */
    abstract protected function selected(Model $record): array;
}
