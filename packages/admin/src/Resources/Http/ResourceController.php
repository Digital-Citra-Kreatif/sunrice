<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Http;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Resources\ListQuery;
use Sunrice\Admin\Resources\RegisteredResource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The screens of every resource: the list, the forms, delete, actions and
 * CSV export. Each route carries its resource's key; every request is
 * authorized against the resource (its policy or permissions).
 *
 * @internal
 */
final class ResourceController
{
    public function __construct(private Panel $panel) {}

    public function index(Request $request): Response
    {
        $resource = $this->resource($request);
        $user = $this->authorize($request, $resource, 'view');
        $table = $resource->table();
        $list = new ListQuery($resource, $request);

        $page = $list->builder()->paginate(ListQuery::perPage($request, $table->getPerPage()))->withQueryString();
        $rowActions = array_values(array_filter($table->getActions(), fn (Action $action) => ! $action->isBulk));
        $bulkActions = array_values(array_filter($table->getActions(), fn (Action $action) => $action->isBulk));

        $rows = [];
        foreach ($page->items() as $record) {
            $cells = [];
            foreach ($table->getColumns() as $column) {
                $cells[$column->name] = $column->value($record);
            }
            $rows[] = [
                'id' => $record->getKey(),
                'title' => $resource->class::title($record),
                'cells' => $cells,
                'can' => ['update' => $resource->allows($user, 'update', $record), 'delete' => $resource->allows($user, 'delete', $record)],
                'actions' => array_values(array_map(
                    fn (Action $action) => $action->key,
                    array_filter($rowActions, fn (Action $action) => $resource->allows($user, $action->getAbility(), $record)),
                )),
            ];
        }

        return Inertia::render('Resources/Index', [
            'resource' => $this->describe($resource),
            'columns' => array_map(fn (Column $column) => $column->toArray(), $table->getColumns()),
            'filters' => array_map(fn (Filter $filter) => $filter->toArray(), $table->getFilters()),
            'rowActions' => array_map(fn (Action $action) => $action->toArray(), $rowActions),
            'bulkActions' => [
                ...($resource->allows($user, 'delete') ? [['key' => 'delete', 'label' => 'Delete', 'confirm' => 'Delete the selected records? This can\'t be undone.', 'destructive' => true]] : []),
                ...array_map(fn (Action $action) => $action->toArray(), $bulkActions),
            ],
            'can' => ['create' => $resource->allows($user, 'create'), 'export' => $table->isExportable()],
            'query' => $list->state(),
            'rows' => [
                'data' => $rows,
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'perPage' => $page->perPage(),
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'prevUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'perPageOptions' => ListQuery::PER_PAGE,
        ]);
    }

    public function create(Request $request): Response
    {
        $resource = $this->resource($request);
        $this->authorize($request, $resource, 'create');

        return $this->form($resource, null);
    }

    public function store(Request $request): RedirectResponse
    {
        $resource = $this->resource($request);
        $this->authorize($request, $resource, 'create');

        $model = $resource->class::model();
        $record = new $model;
        $this->save($request, $resource, $record, 'create');

        return redirect()->route("sunrice.admin.resources.{$resource->key()}.edit", $record->getKey())
            ->with('success', $resource->class::singularLabel().' created.');
    }

    public function edit(Request $request, string $record): Response
    {
        $resource = $this->resource($request);
        $model = $this->find($resource, $record);
        $this->authorize($request, $resource, 'update', $model);

        return $this->form($resource, $model);
    }

    public function update(Request $request, string $record): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $this->find($resource, $record);
        $this->authorize($request, $resource, 'update', $model);

        $this->save($request, $resource, $model, 'edit');

        return back()->with('success', $resource->class::singularLabel().' saved.');
    }

    public function destroy(Request $request, string $record): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $this->find($resource, $record);
        $this->authorize($request, $resource, 'delete', $model);

        $model->delete();

        return redirect()->route("sunrice.admin.resources.{$resource->key()}.index")
            ->with('success', $resource->class::title($model).' deleted.');
    }

    public function action(Request $request, string $record, string $action): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $this->find($resource, $record);
        $definition = $this->findAction($resource, $action, bulk: false);
        $this->authorize($request, $resource, $definition->getAbility(), $model);

        return $this->run(fn () => $definition->run($model));
    }

    public function bulk(Request $request, string $action): RedirectResponse
    {
        $resource = $this->resource($request);
        $user = $this->authorize($request, $resource, 'view');
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:1000']]);
        $ids = array_values(array_filter((array) $request->input('ids'), fn (mixed $id) => is_int($id) || is_string($id)));

        // Only records the resource can reach, and only if the user may act on each.
        $records = $resource->class::query()->whereKey($ids)->get();
        $definition = $action === 'delete' ? null : $this->findAction($resource, $action, bulk: true);
        $ability = $definition?->getAbility() ?? 'delete';
        foreach ($records as $record) {
            abort_unless($resource->allows($user, $ability, $record), 403);
        }

        if ($definition === null) {
            DB::transaction(fn () => $records->each(fn (Model $record) => $record->delete()));

            return back()->with('success', $records->count().' deleted.');
        }

        return $this->run(fn () => $definition->run($records));
    }

    public function export(Request $request): StreamedResponse
    {
        $resource = $this->resource($request);
        $this->authorize($request, $resource, 'view');
        abort_unless($resource->table()->isExportable(), 404);

        $columns = $resource->table()->getColumns();
        $query = (new ListQuery($resource, $request))->builder();

        return response()->streamDownload(function () use ($columns, $query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, array_map(fn (Column $column) => $column->toArray()['label'], $columns), escape: '');
            foreach ($query->lazy(500) as $record) {
                fputcsv($out, array_map(fn (Column $column) => self::csvCell($column->value($record)), $columns), escape: '');
            }
            fclose($out);
        }, $resource->key().'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A spreadsheet cell: values starting with = + - @ (or a tab or
     * carriage return) are prefixed with ' so spreadsheet apps don't run
     * them as formulas.
     */
    public static function csvCell(string|int|float|bool|null $value): string
    {
        $value = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            default => (string) $value,
        };

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    private function form(RegisteredResource $resource, ?Model $record): Response
    {
        $operation = $record === null ? 'create' : 'edit';
        $sections = [];
        $loose = [];
        foreach ($resource->form()->getSchema() as $component) {
            if (! $component->shownOn($operation)) {
                continue;
            }
            $fields = array_values(array_filter($component->fields(), fn (Field $field) => $field->shownOn($operation)));
            if ($component instanceof Section) {
                $sections[] = [...$component->toArray(), 'fields' => array_map(fn (Field $field) => $field->toArray(), $fields)];
            } else {
                $loose = [...$loose, ...$fields];
            }
        }
        if ($loose !== []) {
            array_unshift($sections, ['kind' => 'section', 'title' => null, 'description' => null, 'columns' => 1, 'fields' => array_map(fn (Field $field) => $field->toArray(), $loose)]);
        }

        $values = [];
        foreach ($resource->form()->fields($operation) as $field) {
            $values[$field->name] = $field->formValue($record);
        }

        return Inertia::render('Resources/Form', [
            'resource' => $this->describe($resource),
            'record' => $record === null ? null : ['id' => $record->getKey(), 'title' => $resource->class::title($record)],
            'sections' => $sections,
            'values' => $values,
            'can' => ['delete' => $record !== null && $resource->allows($this->user(), 'delete', $record)],
        ]);
    }

    private function save(Request $request, RegisteredResource $resource, Model $record, string $operation): void
    {
        $fields = $resource->form()->fields($operation);
        $rules = [];
        $attributes = [];
        foreach ($fields as $field) {
            $rules[$field->name] = $field->validationRules($record);
            $attributes[$field->name] = $field->toArray()['label'];
        }
        /** @var array<string, mixed> $validated */
        $validated = $request->validate($rules, [], $attributes);

        // Only declared fields are written, whatever else the request sends.
        foreach ($fields as $field) {
            if (array_key_exists($field->name, $validated) || $field instanceof Toggle) {
                $field->fill($record, $validated[$field->name] ?? null);
            }
        }
        $record->save();
    }

    /** @param Closure(): ?string $run */
    private function run(Closure $run): RedirectResponse
    {
        try {
            $message = $run();
        } catch (ActionFailed $failed) {
            return back()->with('error', $failed->getMessage());
        }

        return back()->with('success', $message ?? 'Done.');
    }

    private function resource(Request $request): RegisteredResource
    {
        $key = $request->route()?->defaults['resource'] ?? null;
        $resource = is_string($key) ? ($this->panel->getResources()[$key] ?? null) : null;
        abort_if($resource === null, 404);

        return $resource;
    }

    private function find(RegisteredResource $resource, string $id): Model
    {
        return $resource->class::query()->whereKey($id)->firstOrFail();
    }

    private function findAction(RegisteredResource $resource, string $key, bool $bulk): Action
    {
        foreach ($resource->table()->getActions() as $action) {
            if ($action->key === $key && $action->isBulk === $bulk) {
                return $action;
            }
        }
        abort(404);
    }

    private function authorize(Request $request, RegisteredResource $resource, string $ability, ?Model $record = null): Authorizable
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable && $resource->allows($user, $ability, $record), 403);

        return $user;
    }

    private function user(): Authorizable
    {
        $user = request()->user();
        abort_unless($user instanceof Authorizable, 403);

        return $user;
    }

    /** @return array{key: string, label: string, singularLabel: string} */
    private function describe(RegisteredResource $resource): array
    {
        return ['key' => $resource->key(), 'label' => $resource->class::label(), 'singularLabel' => $resource->class::singularLabel()];
    }
}
