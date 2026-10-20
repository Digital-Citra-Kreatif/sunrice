<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Tables;

/**
 * A resource's list: columns, filters, actions, sorting and page size.
 *
 * @api
 */
final class Table
{
    /** @var list<Column> */
    private array $columns = [];

    /** @var list<Filter> */
    private array $filters = [];

    /** @var list<Action> */
    private array $actions = [];

    private string $defaultSort = '-id';

    private int $perPage = 25;

    private bool $exportable = false;

    /** @param list<Column> $columns */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /** @param list<Filter> $filters */
    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /** @param list<Action> $actions row and bulk actions, besides edit and delete */
    public function actions(array $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    /** "name" (ascending) or "-created_at" (descending): a sortable column, or "id". */
    public function defaultSort(string $sort): self
    {
        $this->defaultSort = $sort;

        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = max(1, min(100, $perPage));

        return $this;
    }

    /**
     * Offer a CSV download of the list (as filtered and sorted), for users
     * who may view it and export it: the resource gets the permission
     * "<prefix>.export" (or the policy's export(), when it has one).
     */
    public function exportable(bool $exportable = true): self
    {
        $this->exportable = $exportable;

        return $this;
    }

    /** @return list<Column> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return list<Filter> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /** @return list<Action> */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function getDefaultSort(): string
    {
        return $this->defaultSort;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }
}
