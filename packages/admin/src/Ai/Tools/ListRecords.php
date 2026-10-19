<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Resources\ListQuery;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;

#[IsReadOnly]
#[Description('List a resource\'s records as its screen does: search, filters (see "filters" in the answer), sort ("name" or "-name") and pages. Each row has its id, title and the list\'s columns.')]
final class ListRecords extends ResourceTool
{
    protected string $name = 'list_records';

    public function handle(Request $request): Response
    {
        $resource = $this->findResource($request);
        if ($resource instanceof Response) {
            return $resource;
        }
        $table = $resource->table();
        $filters = $request->get('filters');
        $query = new ListQuery($resource, new HttpRequest(array_filter([
            'search' => $request->get('search'),
            'filters' => is_array($filters) ? $filters : null,
            'sort' => $request->get('sort'),
        ])));
        $perPage = $request->get('per_page');
        $number = $request->get('page');
        $page = $query->builder()->paginate(is_int($perPage) ? max(1, min(100, $perPage)) : 25, ['*'], 'page', is_int($number) ? max(1, $number) : 1);

        return $this->json([
            'resource' => $resource->key(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'rows' => array_map(fn (Model $record) => [
                'id' => $record->getKey(),
                'title' => $resource->class::title($record),
                'columns' => array_combine(
                    array_map(fn (Column $column) => $column->name, $table->getColumns()),
                    array_map(fn (Column $column) => $column->value($record), $table->getColumns()),
                ),
            ], $page->items()),
            'filters' => array_map(fn (Filter $filter) => $filter->toArray(), $table->getFilters()),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource\'s key, from get_panel_info.')->required(),
            'search' => $schema->string(),
            'filters' => $schema->object()->description('Filter name => option value.'),
            'sort' => $schema->string()->description('A sortable column, "-" first for descending.'),
            'page' => $schema->integer(),
            'per_page' => $schema->integer()->description('Up to 100; 25 by default.'),
        ];
    }
}
