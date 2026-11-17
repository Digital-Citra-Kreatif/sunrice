<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sunrice\Admin\Activity\ActivityLog;

/**
 * Who did what an event tells about, and from where: the panel's and the
 * plugins' events carry them as `$event->user` and `$event->source`.
 * They're read when the event is made (before a transaction commits, for
 * an event fired after it), so a queued listener gets them too.
 *
 * @api
 */
trait RecordsActor
{
    /**
     * The signed-in user who did it (an AI agent acts as its token's
     * user, a visitor signed in to the site as theirs); null for a guest
     * visitor, the console or the scheduler.
     */
    public private(set) ?Authenticatable $user;

    /**
     * Where it came from: "admin" (the panel's screens), "ai" (an AI
     * agent), "system" (the console, the queue, the scheduler: structure
     * imports, starter installs and demo content too) or "site" (a
     * visitor, for forms and comments). See ActivityLog::currentSource().
     */
    public private(set) string $source;

    /** Sets $user and $source: the given ones, else the current ones. */
    protected function recordActor(?Authenticatable $user = null, ?string $source = null): void
    {
        $log = app(ActivityLog::class);
        $this->user = $user ?? $log->currentUser();
        $this->source = $source ?? $log->currentSource();
    }
}
