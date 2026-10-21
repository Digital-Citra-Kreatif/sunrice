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
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Admin\Support\Like;
use Sunrice\Admin\Support\Say;

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

    /** The user's shown columns (useTablePreferences). */
    public const PREFERENCES = 'table.activity';

    public function __construct(private Panel $panel) {}

    /**
     * Lines from an address: exactly a whole one, or starting with a part ("192.168.", "2001:db8:"), which the index serves.
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    private static function whereIp(Builder $query, string $ip): Builder
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            ? $query->where('ip_address', $ip)
            : $query->whereLike('ip_address', Like::escape($ip).'%');
    }

    /** Whether a search could be (the start of) an IP address: digits with dots, or hex with colons. */
    private static function looksLikeIp(string $text): bool
    {
        return preg_match('/^(\d{1,3}\.){1,3}\d{0,3}$|^[0-9a-f]{1,4}:[0-9a-f:]*$/i', $text) === 1;
    }

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
            'ip' => ['nullable', 'string', 'max:45', 'regex:/^[0-9A-Fa-f:.]+$/'],
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
            ->when($text('ip'), fn (Builder $q, string $v) => self::whereIp($q, $v))
            ->when($search, fn (Builder $q, string $v) => $q->where(fn (Builder $w) => $w
                ->whereLike('subject_label', '%'.Like::escape($v).'%')
                ->orWhereLike('user_name', '%'.Like::escape($v).'%')
                ->orWhereLike('action', '%'.Like::escape($v).'%')
                // An address, or the start of one ("192.168."): by IP too.
                ->when(self::looksLikeIp($v), fn (Builder $ip) => $ip->orWhere(fn (Builder $by) => self::whereIp($by, $v)))))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $types = [];
        foreach (Activity::query()->distinct()->orderBy('subject_type')->pluck('subject_type') as $type) {
            if (is_string($type)) {
                $types[] = ['value' => $type, 'label' => $this->typeLabel($type)];
            }
        }
        // Two types with one name ("Menu": the menus resource and the CMS's menu links): tell them apart.
        $counts = array_count_values(array_column($types, 'label'));
        foreach ($types as $index => $type) {
            if ($counts[$type['label']] > 1) {
                $resource = $this->panel->getResources()[$type['value']] ?? null;
                $types[$index]['label'] = $resource !== null && $resource->class::navigationLabel() !== $type['label']
                    ? $resource->class::navigationLabel()
                    : "{$type['label']} ({$type['value']})";
            }
        }

        // Who: everyone who did something, by name (the latest name for each).
        $people = [];
        foreach (Activity::query()->whereNotNull('user_id')->selectRaw('user_id, max(id) as latest')->groupBy('user_id')->orderByDesc('latest')->limit(200)->pluck('latest') as $latest) {
            $people[] = $latest;
        }
        $names = Activity::query()->whereKey($people)->get(['id', 'user_id', 'user_name'])
            ->map(fn (Activity $line) => ['value' => (string) $line->user_id, 'label' => $line->user_name ?? ('#'.$line->user_id)])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

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
                'ip' => $text('ip'),
            ],
            'preferences' => ['key' => self::PREFERENCES, ...app(Preferences::class)->table($user, self::PREFERENCES)],
            'types' => $types,
            'actions' => $actions,
            'people' => $names,
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
            : ($deleted === 1 ? __('Deleted 1 activity log line.') : __('Deleted :count activity log lines.', ['count' => $deleted])));
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
        $values = is_array($properties['values'] ?? null) ? $properties['values'] : [];
        // "Close after days: 30 → 365", or the setting's name when its values aren't kept.
        $changed = is_array($properties['changed'] ?? null) ? array_values(array_map(function (string $name) use ($values): string {
            $label = Str::ucfirst(str_replace('_', ' ', Str::snake($name)));
            $value = $values[$name] ?? null;

            return is_array($value) && is_string($value['from'] ?? null) && is_string($value['to'] ?? null) ? "{$label}: {$value['from']} → {$value['to']}" : $label;
        }, array_filter($properties['changed'], 'is_string'))) : [];
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
            // About their own account ("Rina logged in"): the subject isn't named again.
            'self' => $activity->isAboutItsActor(),
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

        return Say::it($resource !== null ? $resource->class::singularLabel() : Str::ucfirst($type));
    }

    private function subjectUrl(Activity $activity): ?string
    {
        $id = $activity->subject_id;
        if ($id === null) {
            return null;
        }
        // By id: the edit page sends it on to the record's own address.
        $resources = $this->panel->getResources();
        if (isset($resources[$activity->subject_type])) {
            return route($resources[$activity->subject_type]->routeName('edit'), $id, false);
        }
        // Security events are about accounts: the user's page.
        if ($activity->subject_type === 'account' && isset($resources['users'])) {
            return route($resources['users']->routeName('edit'), $id, false);
        }
        if ($activity->subject_type === 'settings' && isset($this->panel->getSettings()[$id])) {
            return route('sunrice.admin.settings.edit', $id, false);
        }

        return null;
    }
}
