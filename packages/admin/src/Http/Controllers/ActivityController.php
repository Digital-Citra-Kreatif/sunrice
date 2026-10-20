<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Activity\PruneActivity;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Support\Like;

/**
 * The activity log page: who changed what, newest first, with filters.
 * Needs "sunrice.activity.view".
 *
 * @internal
 */
final class ActivityController
{
    /** Lines per page: the default, and the choices (`per_page`). */
    public const PER_PAGE = 50;

    public const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    public function __construct(private Panel $panel) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable && $user->can('sunrice.activity.view'), 403);

        /** @var array<string, mixed> $query */
        $query = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', Rule::in(ActivityLog::SOURCES)],
            'type' => ['nullable', 'string', 'max:60'],
            'action' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
            'subject' => ['nullable', 'string', 'max:64'],
            'user' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $text = fn (string $key): ?string => is_string($query[$key] ?? null) && trim($query[$key]) !== '' ? trim($query[$key]) : null;
        $search = $text('search');
        $perPage = is_numeric($query['per_page'] ?? null) ? (int) $query['per_page'] : self::PER_PAGE;

        $page = Activity::query()
            ->when($text('source'), fn (Builder $q, string $v) => $q->where('source', $v))
            ->when($text('type'), fn (Builder $q, string $v) => $q->where('subject_type', $v))
            ->when($text('action'), fn (Builder $q, string $v) => $q->where('action', $v))
            ->when($text('type') !== null ? $text('subject') : null, fn (Builder $q, string $v) => $q->where('subject_id', $v))
            ->when($text('user'), fn (Builder $q, string $v) => $q->where('user_id', $v))
            ->when($text('from'), fn (Builder $q, string $v) => $q->where('created_at', '>=', now()->parse($v)->startOfDay()))
            ->when($text('to'), fn (Builder $q, string $v) => $q->where('created_at', '<=', now()->parse($v)->endOfDay()))
            ->when($search, fn (Builder $q, string $v) => $q->where(fn (Builder $w) => $w
                ->whereLike('subject_label', '%'.Like::escape($v).'%')
                ->orWhereLike('user_name', '%'.Like::escape($v).'%')
                ->orWhereLike('action', '%'.Like::escape($v).'%')))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $types = [];
        foreach (Activity::query()->distinct()->orderBy('subject_type')->pluck('subject_type') as $type) {
            if (is_string($type)) {
                $types[] = ['value' => $type, 'label' => $this->typeLabel($type)];
            }
        }

        $actions = [];
        foreach (Activity::query()->distinct()->orderBy('action')->limit(500)->pluck('action') as $action) {
            if (is_string($action)) {
                $actions[] = $action;
            }
        }

        return Inertia::render('Activity/Index', [
            'query' => [
                'search' => $search ?? '',
                'source' => $text('source'),
                'type' => $text('type'),
                'action' => $text('action'),
                'perPage' => $perPage,
                'subject' => $text('subject'),
                'user' => $text('user'),
                'from' => $text('from'),
                'to' => $text('to'),
            ],
            'types' => $types,
            'actions' => $actions,
            'sources' => ActivityLog::SOURCES,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'defaultPerPage' => self::PER_PAGE,
            'prune' => $user->can('sunrice.activity.prune') ? ['keepDays' => self::keepDays()] : null,
            'rows' => [
                'data' => array_map(fn (Activity $activity) => $this->describe($activity), $page->items()),
                'total' => $page->total(),
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'prevUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
        ]);
    }

    /** Prune: lines older than `days` days go (a "log pruned" security event and alert). */
    public function prune(Request $request, PruneActivity $prune): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable && $user->can('sunrice.activity.prune'), 403);
        $request->validate(['days' => ['required', 'integer', 'min:1', 'max:36500']]);
        $account = auth($this->panel->getGuard())->user();
        $deleted = $prune->handle($request->integer('days'), $account instanceof Model ? $account : null);

        return back()->with('success', $deleted === 0
            ? 'Nothing was older than that.'
            : "Deleted {$deleted} activity log ".Str::plural('line', $deleted).'.');
    }

    /** The config's `activity.keep_days`, the dialog's suggestion (null: kept forever). */
    private static function keepDays(): ?int
    {
        $days = config('sunrice-admin.activity.keep_days');

        return is_numeric($days) ? max(1, (int) $days) : null;
    }

    /** @return array<string, mixed> */
    private function describe(Activity $activity): array
    {
        $properties = $activity->properties ?? [];
        $changed = is_array($properties['changed'] ?? null) ? array_values(array_filter($properties['changed'], 'is_string')) : [];
        $note = is_string($properties['note'] ?? null) ? $properties['note'] : null;

        return [
            'id' => $activity->id,
            'at' => $activity->created_at?->toIso8601String(),
            'source' => $activity->source,
            'via' => $activity->via,
            'userId' => $activity->user_id,
            'user' => $activity->user_name,
            'action' => $activity->action,
            'type' => $activity->subject_type,
            'typeLabel' => $this->typeLabel($activity->subject_type),
            'subjectId' => $activity->subject_id,
            'subject' => $activity->subject_label,
            'url' => $activity->action === 'deleted' ? null : $this->subjectUrl($activity),
            'changed' => $changed,
            'note' => $note,
            'ip' => $activity->ip_address,
        ];
    }

    /** "Product" for the products resource, "Settings", "Media" … */
    private function typeLabel(string $type): string
    {
        $resource = $this->panel->getResources()[$type] ?? null;

        return $resource !== null ? $resource->class::singularLabel() : Str::ucfirst($type);
    }

    private function subjectUrl(Activity $activity): ?string
    {
        $id = $activity->subject_id;
        if ($id === null) {
            return null;
        }
        if (isset($this->panel->getResources()[$activity->subject_type])) {
            return route("sunrice.admin.resources.{$activity->subject_type}.edit", $id, false);
        }
        // Security events are about accounts: the user's page.
        if ($activity->subject_type === 'account' && isset($this->panel->getResources()['users'])) {
            return route('sunrice.admin.resources.users.edit', $id, false);
        }
        if ($activity->subject_type === 'settings' && isset($this->panel->getSettings()[$id])) {
            return route('sunrice.admin.settings.edit', $id, false);
        }

        return null;
    }
}
