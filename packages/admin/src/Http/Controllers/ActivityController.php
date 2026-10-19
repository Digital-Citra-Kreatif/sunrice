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
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecurityLog;

/**
 * The activity log page: who changed what, newest first, with filters.
 * Needs "sunrice.activity.view".
 *
 * @internal
 */
final class ActivityController
{
    public const PER_PAGE = 50;

    public function __construct(private Panel $panel) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable && $user->can('sunrice.activity.view'), 403);

        /** @var array<string, mixed> $query */
        $query = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', Rule::in(ActivityLog::SOURCES)],
            'type' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:64'],
            'user' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $text = fn (string $key): ?string => is_string($query[$key] ?? null) && trim($query[$key]) !== '' ? trim($query[$key]) : null;
        $search = $text('search');

        $page = Activity::query()
            ->when($text('source'), fn (Builder $q, string $v) => $q->where('source', $v))
            ->when($text('type'), fn (Builder $q, string $v) => $q->where('subject_type', $v))
            ->when($text('type') !== null ? $text('subject') : null, fn (Builder $q, string $v) => $q->where('subject_id', $v))
            ->when($text('user'), fn (Builder $q, string $v) => $q->where('user_id', $v))
            ->when($text('from'), fn (Builder $q, string $v) => $q->where('created_at', '>=', now()->parse($v)->startOfDay()))
            ->when($text('to'), fn (Builder $q, string $v) => $q->where('created_at', '<=', now()->parse($v)->endOfDay()))
            ->when($search, fn (Builder $q, string $v) => $q->where(fn (Builder $w) => $w
                ->whereLike('subject_label', '%'.self::escapeLike($v).'%')
                ->orWhereLike('user_name', '%'.self::escapeLike($v).'%')
                ->orWhereLike('action', '%'.self::escapeLike($v).'%')))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $types = [];
        foreach (Activity::query()->distinct()->orderBy('subject_type')->pluck('subject_type') as $type) {
            if (is_string($type)) {
                $types[] = ['value' => $type, 'label' => $this->typeLabel($type)];
            }
        }

        return Inertia::render('Activity/Index', [
            'query' => [
                'search' => $search ?? '',
                'source' => $text('source'),
                'type' => $text('type'),
                'subject' => $text('subject'),
                'user' => $text('user'),
                'from' => $text('from'),
                'to' => $text('to'),
            ],
            'types' => $types,
            'sources' => ActivityLog::SOURCES,
            'rows' => [
                'data' => array_map(fn (Activity $activity) => $this->describe($activity), $page->items()),
                'total' => $page->total(),
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'prevUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'can' => ['prune' => $request->user() instanceof Authorizable && $request->user()->can('sunrice.activity.prune')],
        ]);
    }

    /** Deletes lines older than some days: a security event, which alerts (it could hide tracks). */
    public function prune(Request $request, SecurityLog $security): RedirectResponse
    {
        $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650']]);
        $days = $request->integer('days');
        $deleted = Activity::prune($days);
        $security->record('activity pruned', $request->user() instanceof Model ? $request->user() : null, null, ['note' => "{$deleted} lines older than {$days} days"]);

        return back()->with('success', "Deleted {$deleted} ".($deleted === 1 ? 'line' : 'lines')." older than {$days} days.");
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

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
