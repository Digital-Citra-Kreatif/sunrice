<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Sunrice\Admin\Resources\Tables\Column;

/**
 * A resource's list query from the request: search, filters and sort,
 * each only on what the table declares (anything else is ignored).
 *
 * @internal
 */
final class ListQuery
{
    public const PER_PAGE = [10, 25, 50, 100];

    public string $search = '';

    /** @var array<string, string> */
    public array $filters = [];

    public string $sort;

    public function __construct(private RegisteredResource $resource, Request $request)
    {
        $table = $resource->table();

        $search = $request->query('search');
        $this->search = is_string($search) ? mb_substr(trim($search), 0, 200) : '';

        $filters = $request->query('filters');
        foreach ($table->getFilters() as $filter) {
            $value = is_array($filters) ? ($filters[$filter->name] ?? null) : null;
            if (is_string($value) && $filter->accepts($value)) {
                $this->filters[$filter->name] = $value;
            }
        }

        $sortable = ['id'];
        foreach ($table->getColumns() as $column) {
            if ($column->isSortable()) {
                $sortable[] = $column->name;
            }
        }
        $sort = $request->query('sort');
        $this->sort = is_string($sort) && in_array(ltrim($sort, '-'), $sortable, true) ? $sort : $table->getDefaultSort();
    }

    /** @return Builder<Model> */
    public function builder(): Builder
    {
        $table = $this->resource->table();
        $query = $this->resource->class::query();

        $relations = [];
        foreach ($table->getColumns() as $column) {
            if ($column->relation() !== null) {
                $relations[] = $column->relation();
            }
        }
        if ($relations !== []) {
            $query->with(array_values(array_unique($relations)));
        }

        if ($this->search !== '') {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search).'%';
            $searchable = array_filter($table->getColumns(), fn (Column $column) => $column->isSearchable());
            if ($searchable !== []) {
                $query->where(function (Builder $where) use ($searchable, $term): void {
                    foreach ($searchable as $column) {
                        $relation = $column->relation();
                        $relation === null
                            ? $where->orWhereLike($where->qualifyColumn($column->name), $term)
                            // whereLike: case-insensitive on every driver (ILIKE on PostgreSQL).
                            : $where->orWhereHas($relation, fn (Builder $related) => $related->whereLike($related->qualifyColumn($column->attribute()), $term));
                    }
                });
            }
        }

        foreach ($table->getFilters() as $filter) {
            if (isset($this->filters[$filter->name])) {
                $filter->apply($query, $this->filters[$filter->name]);
            }
        }

        $column = ltrim($this->sort, '-');
        $query->orderBy($query->qualifyColumn($column), str_starts_with($this->sort, '-') ? 'desc' : 'asc');
        if ($column !== $query->getModel()->getKeyName()) {
            // A stable order for equal values, so pages don't repeat or skip rows.
            $query->orderBy($query->qualifyColumn($query->getModel()->getKeyName()));
        }

        return $query;
    }

    /** Rows per page: ?per_page= when it's one of PER_PAGE, else the user's saved choice, else the table's. */
    public static function perPage(Request $request, int $default, ?int $saved = null): int
    {
        $default = $saved !== null && in_array($saved, self::PER_PAGE, true) ? $saved : $default;
        $perPage = $request->integer('per_page', $default);

        return in_array($perPage, self::PER_PAGE, true) ? $perPage : $default;
    }

    /** @return array{search: string, filters: array<string, string>, sort: string} */
    public function state(): array
    {
        return ['search' => $this->search, 'filters' => $this->filters, 'sort' => $this->sort];
    }
}
