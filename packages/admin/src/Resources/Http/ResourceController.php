<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Http;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Resources\SaveRecord;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Admin\Resources\Forms\RelationSelect;
use Sunrice\Admin\Resources\ListQuery;
use Sunrice\Admin\Resources\RegisteredResource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Security\SecurityLog;
use Sunrice\Admin\Support\Card;
use Sunrice\Admin\Support\Csv;
use Sunrice\Admin\Support\Say;
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
    public function __construct(private Panel $panel, private ActivityLog $activity, private Preferences $preferences) {}

    public function index(Request $request): Response
    {
        $resource = $this->resource($request);
        $user = $this->authorize($request, $resource, 'view');
        $table = $resource->table();
        $list = new ListQuery($resource, $request);
        // The user's columns and page size for this table (useTablePreferences).
        $preferencesKey = 'table.resources.'.$resource->key();
        $preferences = $user instanceof Authenticatable ? $this->preferences->table($user, $preferencesKey) : ['columns' => null, 'perPage' => null];

        $page = $list->builder()->paginate(ListQuery::perPage($request, $table->getPerPage(), $preferences['perPage']))->withQueryString();
        $rowActions = array_values(array_filter($table->getActions(), fn (Action $action) => ! $action->isBulk));
        $bulkActions = array_values(array_filter($table->getActions(), fn (Action $action) => $action->isBulk));

        $rows = [];
        foreach ($page->items() as $record) {
            $cells = [];
            foreach ($table->getColumns() as $column) {
                $cells[$column->name] = $column->value($record);
            }
            $offered = array_filter($rowActions, fn (Action $action) => $action->isVisibleFor($record)
                && $resource->allows($user, $action->getAbility(), $record) && $action->allows($user, $record)
                && (! $action->isUrl() || $action->urlFor($record) !== null));
            $urls = [];
            foreach ($offered as $action) {
                if ($action->isUrl()) {
                    $urls[$action->key] = $action->urlFor($record);
                }
            }
            $rows[] = [
                'id' => $record->getKey(),
                // In its addresses: /{path}/{key}/edit.
                'key' => $resource->recordKeyOf($record),
                // Where the title links, when not the edit page (Resource::recordUrl()).
                'url' => $resource->class::recordUrl($record),
                'title' => $resource->class::title($record),
                'cells' => $cells,
                'can' => ['update' => $resource->allows($user, 'update', $record), 'delete' => $resource->allows($user, 'delete', $record)],
                'actions' => array_values(array_map(fn (Action $action) => $action->key, $offered)),
                'urls' => (object) $urls,
                // In the trash: when it was deleted.
                'deletedAt' => self::deletedAt($record),
            ];
        }

        return Inertia::render('Resources/Index', [
            'resource' => $this->describe($resource),
            'columns' => array_map(fn (Column $column) => $column->toArray(), $table->getColumns()),
            'filters' => array_map(fn (Filter $filter) => $filter->toArray(), $table->getFilters()),
            'rowActions' => array_map(fn (Action $action) => $action->toArray(), $rowActions),
            'bulkActions' => [
                // A bulk action keyed "delete" replaces the built-in one.
                ...($resource->allows($user, 'delete') && $this->customAction($resource, 'delete', bulk: true) === null ? [$resource->softDeletes()
                    ? ['key' => 'delete', 'label' => __('Move to trash'), 'confirm' => __('Move the selected records to the trash? You can restore them.'), 'destructive' => true, 'fields' => []]
                    : ['key' => 'delete', 'label' => __('Delete'), 'confirm' => __('Delete the selected records? This can\'t be undone.'), 'destructive' => true, 'fields' => []]] : []),
                ...array_map(fn (Action $action) => $action->toArray(), $bulkActions),
            ],
            'can' => [
                'create' => $resource->allows($user, 'create'),
                'export' => $table->isExportable() && $resource->allows($user, 'export'),
                // A trash to look in and restore from: models with SoftDeletes.
                'trash' => $resource->softDeletes() && $resource->allows($user, 'delete'),
            ],
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
            // Outside the trash, how many are in it, for an empty list to say so.
            'trashedCount' => $resource->allows($user, 'delete') && ! $list->trashed ? $list->trashedCount() : 0,
            // The "⋮" menu's links (Resource::links()), labels said in the panel's language.
            'links' => array_map(fn (array $link): array => [
                'label' => Say::it($link['label']),
                'url' => preg_match('#^(https?:)?//#', $link['url']) === 1 || str_starts_with($link['url'], '/') ? $link['url'] : '/'.trim($this->panel->getPath(), '/').'/'.ltrim($link['url'], '/'),
                'group' => isset($link['group']) ? Say::it($link['group']) : null,
                'external' => $link['external'] ?? false,
            ], $resource->class::links($user)),
            'perPageOptions' => ListQuery::PER_PAGE,
            'preferences' => ['key' => $preferencesKey, 'columns' => $preferences['columns'], 'perPage' => $preferences['perPage']],
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

        $record = $resource->class::newRecord();
        app(SaveRecord::class)->handle($resource, $record, $request->all(), 'create');
        $this->activity->record('created', $resource->key(), $record, $resource->class::title($record));

        return redirect()->route($resource->routeName('edit'), $resource->recordKeyOf($record))
            ->with('success', __(':label created.', ['label' => Say::it($resource->class::singularLabel())]));
    }

    public function edit(Request $request, string $record): Response|RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $this->findOrById($resource, $record);
        if ($model === null) {
            abort(404);
        }
        // Reached by id (the activity log's links): on to its own address.
        if ($resource->recordKeyOf($model) !== $record) {
            return redirect()->route($resource->routeName('edit'), $resource->recordKeyOf($model));
        }
        $this->authorize($request, $resource, 'update', $model);

        return $this->form($resource, $model);
    }

    public function update(Request $request, string $record): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $this->find($resource, $record);
        $this->authorize($request, $resource, 'update', $model);

        $changed = app(SaveRecord::class)->handle($resource, $model, $request->all(), 'edit');
        $this->activity->updated($resource->key(), $model, $resource->class::title($model), $changed);

        return back()->with('success', __(':label saved.', ['label' => Say::it($resource->class::singularLabel())]));
    }

    public function destroy(Request $request, string $record): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $this->find($resource, $record);
        $this->authorize($request, $resource, 'delete', $model);

        try {
            $resource->class::deleteRecord($model);
        } catch (ActionFailed $failed) {
            return back()->with('error', $failed->getMessage());
        }
        $this->activity->record('deleted', $resource->key(), $model, $resource->class::title($model));

        return redirect()->route($resource->routeName('index'))
            ->with('success', __(':title deleted.', ['title' => $resource->class::title($model)]));
    }

    public function action(Request $request, string $record, string $action): RedirectResponse
    {
        $resource = $this->resource($request);
        $definition = $this->findAction($resource, $action, bulk: false);
        abort_if($definition->isUrl(), 404);
        $model = $this->find($resource, $record, $definition->isForTrashed());
        $user = $this->authorize($request, $resource, $definition->getAbility(), $model);
        abort_unless($definition->isVisibleFor($model) && $definition->allows($user, $model), 403);

        $editPage = route($resource->routeName('edit'), $resource->recordKeyOf($model));
        $response = $this->run(function () use ($definition, $model, $resource, $request): ?string {
            $message = $definition->run($model, $request->all());
            $this->activity->record($definition->key === 'delete' ? 'deleted' : $definition->key, $resource->key(), $model, $resource->class::title($model));

            return $message;
        });
        // Run from the edit page of a record that is gone now (a Delete): back to the list.
        if (url()->previous() === $editPage && ! $this->query($resource, false)->whereKey($model->getKey())->exists()) {
            return redirect()->route($resource->routeName('index')); // the message is flashed already
        }

        return $response;
    }

    /** Selected records ("ids", primary keys) and the "action": "delete", "restore" or "force-delete" (in the trash) or a bulk action's key. */
    public function bulk(Request $request): RedirectResponse
    {
        $resource = $this->resource($request);
        $user = $this->authorize($request, $resource, 'view');
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:1000'], 'action' => ['required', 'string']]);
        $action = $request->string('action')->toString();
        if ($action === 'restore' || $action === 'force-delete') {
            return $this->fromTrash($request, $resource, $user, $action === 'restore');
        }
        $ids = array_values(array_filter((array) $request->input('ids'), fn (mixed $id) => is_int($id) || is_string($id)));

        // Only records the resource can reach, and only if the user may act on each.
        $definition = $action === 'delete' ? $this->customAction($resource, 'delete', bulk: true) : $this->findAction($resource, $action, bulk: true);
        $records = $this->query($resource, $definition?->isForTrashed() ?? false)->whereKey($ids)->get();
        if ($definition?->isForTrashed() === true) {
            $records = $records->filter(fn (Model $record): bool => $definition->isVisibleFor($record))->values();
        }
        $ability = $definition?->getAbility() ?? 'delete';
        foreach ($records as $record) {
            abort_unless($resource->allows($user, $ability, $record) && ($definition === null || $definition->allows($user, $record)), 403);
        }

        if ($definition === null) {
            try {
                DB::transaction(fn () => $records->each(fn (Model $record) => $resource->class::deleteRecord($record)));
            } catch (ActionFailed $failed) {
                return back()->with('error', $failed->getMessage());
            }
            $records->each(fn (Model $record) => $this->activity->record('deleted', $resource->key(), $record, $resource->class::title($record)));

            return back()->with('success', __(':count deleted.', ['count' => $records->count()]));
        }

        return $this->run(function () use ($definition, $records, $resource, $request): ?string {
            $message = $definition->run($records, $request->all());
            $records->each(fn (Model $record) => $this->activity->record($definition->key === 'delete' ? 'deleted' : $definition->key, $resource->key(), $record, $resource->class::title($record)));

            return $message;
        });
    }

    /** Back from the trash. */
    public function restore(Request $request, string $record): RedirectResponse
    {
        return $this->trashed($request, $record, restore: true);
    }

    /** Deleted for good, from the trash. */
    public function forceDelete(Request $request, string $record): RedirectResponse
    {
        return $this->trashed($request, $record, restore: false);
    }

    private function trashed(Request $request, string $record, bool $restore): RedirectResponse
    {
        $resource = $this->resource($request);
        abort_unless($resource->softDeletes(), 404);
        $model = $this->find($resource, $record, trashed: true);
        abort_unless(self::inTrash($model), 404);
        $this->authorize($request, $resource, 'delete', $model);
        $title = $resource->class::title($model);

        return $this->run(function () use ($resource, $model, $restore, $title): string {
            $restore ? $resource->class::restoreRecord($model) : $resource->class::forceDeleteRecord($model);
            $this->activity->record($restore ? 'restored' : 'permanently deleted', $resource->key(), $model, $title);

            return $restore ? __(':title restored.', ['title' => $title]) : __(':title permanently deleted.', ['title' => $title]);
        });
    }

    /** Restores or permanently deletes the selected records in the trash. */
    private function fromTrash(Request $request, RegisteredResource $resource, Authorizable $user, bool $restore): RedirectResponse
    {
        abort_unless($resource->softDeletes(), 404);
        $ids = array_values(array_filter((array) $request->input('ids'), fn (mixed $id) => is_int($id) || is_string($id)));
        $records = $this->query($resource, true)->whereKey($ids)->get()->filter(fn (Model $record): bool => self::inTrash($record))->values();
        foreach ($records as $record) {
            abort_unless($resource->allows($user, 'delete', $record), 403);
        }
        $titles = $records->map(fn (Model $record): string => $resource->class::title($record));

        return $this->run(function () use ($resource, $records, $restore, $titles): string {
            DB::transaction(fn () => $records->each(fn (Model $record) => $restore ? $resource->class::restoreRecord($record) : $resource->class::forceDeleteRecord($record)));
            $records->each(fn (Model $record, int $i) => $this->activity->record($restore ? 'restored' : 'permanently deleted', $resource->key(), $record, $titles[$i]));

            return $restore ? __(':count restored.', ['count' => $records->count()]) : __(':count permanently deleted.', ['count' => $records->count()]);
        });
    }

    public function export(Request $request): StreamedResponse
    {
        $resource = $this->resource($request);
        $this->authorize($request, $resource, 'view');
        abort_unless($resource->table()->isExportable(), 404);
        $this->authorize($request, $resource, 'export');

        $columns = $resource->table()->getColumns();
        $query = (new ListQuery($resource, $request))->builder();
        // Exports can carry personal data out of the panel.
        $user = $request->user();
        app(SecurityLog::class)->record('exported', $user instanceof Model ? $user : null, null, ['note' => $resource->class::label().' (CSV)']);

        return response()->streamDownload(function () use ($columns, $query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            Csv::write($out, array_map(fn (Column $column): string => $column->toArray()['label'], $columns));
            foreach ($query->lazy(500) as $record) {
                Csv::write($out, array_map(fn (Column $column) => $column->value($record), $columns));
            }
            fclose($out);
        }, $resource->key().'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Records a relation field may choose, matching `q` (at most
     * RelationSelect::LIMIT): for anyone who may view the resource.
     */
    public function options(Request $request, string $field): JsonResponse
    {
        $resource = $this->resource($request);
        $this->authorize($request, $resource, 'view');

        $definition = null;
        foreach ([...$resource->form()->fields('create'), ...$resource->form()->fields('edit')] as $candidate) {
            if ($candidate->name === $field && $candidate instanceof RelationSelect) {
                $definition = $candidate;
                break;
            }
        }
        abort_if($definition === null, 404);
        $search = $request->query('q');

        return response()->json(['options' => $definition->search(
            $resource->class::newRecord(),
            is_string($search) ? mb_substr(trim($search), 0, 100) : '',
        )]);
    }

    private function form(RegisteredResource $resource, ?Model $record): Response
    {
        $operation = $record === null ? 'create' : 'edit';
        $sections = $resource->form()->toSections($operation, $record);

        $values = [];
        foreach ($resource->form()->fields($operation) as $field) {
            $values[$field->name] = $field->formValue($record);
        }

        return Inertia::render('Resources/Edit', [
            'resource' => $this->describe($resource),
            'record' => $record === null ? null : ['id' => $record->getKey(), 'key' => $resource->recordKeyOf($record), 'title' => $resource->class::title($record)],
            'sections' => $sections,
            'values' => $values,
            'can' => ['delete' => $record !== null && $resource->allows($this->user(), 'delete', $record)],
            // A row action keyed "delete" (with its questions) is the edit page's Delete too.
            'deleteAction' => $record === null ? null : $this->deleteAction($resource, $record)?->toArray($record),
            'aside' => $record === null ? [] : array_map(fn (Card $card) => $card->toArray(), $resource->class::aside($record, $this->user())),
        ]);
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

    private function find(RegisteredResource $resource, string $id, bool $trashed = false): Model
    {
        $query = $this->query($resource, $trashed);

        return $query->where($query->qualifyColumn($resource->recordKey()), $id)->firstOrFail();
    }

    private static function inTrash(Model $record): bool
    {
        return method_exists($record, 'trashed') && $record->trashed() === true;
    }

    /** When a record in the trash was deleted (ISO 8601), else null. */
    private static function deletedAt(Model $record): ?string
    {
        if (! self::inTrash($record) || ! method_exists($record, 'getDeletedAtColumn')) {
            return null;
        }
        $column = $record->getDeletedAtColumn();
        $deleted = is_string($column) ? $record->getAttribute($column) : null;

        return $deleted instanceof CarbonInterface ? $deleted->toIso8601String() : null;
    }

    /** By its address key, else (when that isn't the primary key) by a numeric id. */
    private function findOrById(RegisteredResource $resource, string $id): ?Model
    {
        $query = $this->query($resource, false);
        $model = (clone $query)->where($query->qualifyColumn($resource->recordKey()), $id)->first();
        if ($model === null && $resource->recordKey() !== $query->getModel()->getKeyName() && ctype_digit($id)) {
            $model = $query->whereKey($id)->first();
        }

        return $model;
    }

    /**
     * The resource's records; with $trashed, those in the trash too.
     *
     * @return Builder<Model>
     */
    private function query(RegisteredResource $resource, bool $trashed): Builder
    {
        $query = $resource->class::query();

        return $trashed ? $query->withoutGlobalScope(SoftDeletingScope::class) : $query;
    }

    /** The row action keyed "delete", when the user may run it on this record. */
    private function deleteAction(RegisteredResource $resource, ?Model $record): ?Action
    {
        $action = $record === null ? null : $this->customAction($resource, 'delete', bulk: false);
        if ($action === null || $action->isUrl()) {
            return null;
        }
        $user = $this->user();

        return $action->isVisibleFor($record) && $resource->allows($user, $action->getAbility(), $record) && $action->allows($user, $record) ? $action : null;
    }

    private function customAction(RegisteredResource $resource, string $key, bool $bulk): ?Action
    {
        foreach ($resource->table()->getActions() as $action) {
            if ($action->key === $key && $action->isBulk === $bulk) {
                return $action;
            }
        }

        return null;
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
        return ['key' => $resource->key(), 'path' => $resource->path(), 'label' => Say::it($resource->class::label()), 'singularLabel' => Say::it($resource->class::singularLabel())];
    }
}
