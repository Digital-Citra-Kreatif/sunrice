<?php

declare(strict_types=1);

namespace Sunrice\Admin\Activity;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sunrice\Admin\Events\ActivityRecorded;
use Sunrice\Admin\Panel;
use Throwable;

/**
 * Writes the activity log: who created, changed or deleted what in the
 * panel. The panel records its own screens (resources, users, roles,
 * settings, media); plugins and apps record theirs:
 *
 *     app(ActivityLog::class)->record('refunded', 'order', $order, "Order #{$order->number}", ['note' => 'Damaged']);
 *
 * Each line keeps who did it (the signed-in user's name as it was) and
 * where it came from: "admin", "ai" (an AI agent, with its token's name),
 * "system" (the console or the queue) or "security". Changes made by
 * nobody signed in outside the console (a visitor) aren't recorded.
 * Within one request, several "updated" lines for the same thing become
 * one. Values aren't stored, only which fields changed.
 *
 * @api
 */
final class ActivityLog
{
    public const SOURCES = ['admin', 'ai', 'system', 'security'];

    private int $paused = 0;

    /** @var array{0: string, 1: string|null}|null */
    private ?array $source = null;

    /** @var array<string, Activity> this request's "created"/"updated" lines by subject, for merging */
    private array $written = [];

    /** The request $written belongs to (the service may outlive one, e.g. in tests). */
    private ?int $request = null;

    /**
     * @param  Model|string|int|null  $subject  the model, or its id
     * @param  array<string, mixed>  $properties  e.g. ['changed' => ['name', 'price'], 'note' => '…']
     */
    public function record(string $action, string $subjectType, Model|string|int|null $subject = null, ?string $label = null, array $properties = []): ?Activity
    {
        if (! $this->enabled()) {
            return null;
        }
        $source = $this->source();
        if ($source === null) {
            return null;
        }
        $user = $this->user();
        $subjectId = $subject instanceof Model ? $subject->getKey() : $subject;
        $subjectId = is_scalar($subjectId) ? Str::limit((string) $subjectId, 64, '') : null;

        try {
            if ($this->request !== spl_object_id(request())) {
                $this->request = spl_object_id(request());
                $this->written = [];
            }
            $key = "{$subjectType}:{$subjectId}";
            $previous = $subjectId === null ? null : ($this->written[$key] ?? null);
            if ($previous !== null && $action === 'updated' && in_array($previous->action, ['created', 'updated'], true)) {
                $previous->properties = self::merge($previous->properties ?? [], $properties);
                $previous->subject_label = $label === null ? $previous->subject_label : Str::limit($label, 250, '');
                $previous->save();

                return $previous;
            }

            $activity = Activity::query()->create([
                'source' => $source[0],
                'via' => $source[1] === null ? null : Str::limit($source[1], 100, ''),
                'user_id' => $user === null ? null : Str::limit((string) $user[0], 64, ''),
                'user_name' => $user[1] ?? null,
                'action' => Str::limit($action, 60, ''),
                'subject_type' => Str::limit($subjectType, 60, ''),
                'subject_id' => $subjectId,
                'subject_label' => $label === null ? null : Str::limit($label, 250, ''),
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => $source[0] === 'system' ? null : request()->ip(),
                'created_at' => now(),
            ]);
            if ($subjectId !== null && in_array($action, ['created', 'updated'], true)) {
                $this->written[$key] = $activity;
            }
            ActivityRecorded::dispatch($activity);

            return $activity;
        } catch (Throwable $e) {
            // The log must never break what it records.
            Log::warning('Sunrice: could not write the activity log: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Record that `$model` changed, with the names of the fields that did.
     * Nothing is written when nothing changed.
     *
     * @param  list<string>  $changed
     */
    public function updated(string $subjectType, Model $model, ?string $label, array $changed): ?Activity
    {
        return $changed === [] ? null : $this->record('updated', $subjectType, $model, $label, ['changed' => array_values(array_unique($changed))]);
    }

    /**
     * Run something without logging it (imports, seeding).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutLogging(Closure $callback): mixed
    {
        $this->paused++;
        try {
            return $callback();
        } finally {
            $this->paused--;
        }
    }

    /**
     * Run something as coming from another source than the admin, e.g. an
     * AI agent's tool call: withSource('ai', 'Claude Desktop', fn () => …).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withSource(string $source, ?string $via, Closure $callback): mixed
    {
        $previous = $this->source;
        $this->source = [$source, $via];
        try {
            return $callback();
        } finally {
            $this->source = $previous;
        }
    }

    public function enabled(): bool
    {
        return $this->paused === 0 && config('sunrice-admin.activity.enabled', true) !== false;
    }

    /** @return array{0: string, 1: string|null}|null */
    private function source(): ?array
    {
        if ($this->source !== null) {
            return $this->source;
        }
        if ($this->user() !== null) {
            return ['admin', null];
        }

        return app()->runningInConsole() ? ['system', null] : null;
    }

    /** @return array{0: string, 1: string}|null the signed-in user's id and name */
    private function user(): ?array
    {
        $guard = app()->bound(Panel::class) ? app(Panel::class)->getGuard() : null;
        $user = auth($guard)->user();
        if (! $user instanceof Authenticatable) {
            return null;
        }
        $id = $user->getAuthIdentifier();
        $name = data_get($user, 'name') ?? data_get($user, 'email');

        return [is_scalar($id) ? (string) $id : '', is_string($name) && $name !== '' ? Str::limit($name, 250, '') : 'User #'.(is_scalar($id) ? $id : '?')];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private static function merge(array $old, array $new): array
    {
        $changed = array_filter([...(is_array($old['changed'] ?? null) ? $old['changed'] : []), ...(is_array($new['changed'] ?? null) ? $new['changed'] : [])], 'is_string');

        return [...$old, ...$new, ...($changed === [] ? [] : ['changed' => array_values(array_unique($changed))])];
    }
}
