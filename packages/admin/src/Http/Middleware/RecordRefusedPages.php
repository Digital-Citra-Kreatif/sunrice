<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecurityLog;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A panel page refused (403) to someone signed in is a "page refused"
 * security event, at most `security.alerts.forbidden` per account per
 * 10 minutes so the log can't be flooded; reaching that many sends an
 * alert (SecurityAlerts).
 *
 * @internal
 */
final class RecordRefusedPages
{
    public function __construct(private readonly Panel $panel, private readonly SecurityLog $log) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() === 403) {
            $this->record($request);
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $user = auth($this->panel->getGuard())->user();
        if (! $user instanceof Model) {
            return;
        }
        try {
            $limit = config('sunrice-admin.security.alerts.forbidden', 15);
            $limit = is_numeric($limit) ? max(1, (int) $limit) : 15;
            $key = 'sunrice-admin.refused:'.sha1((string) json_encode($user->getKey()));
            Cache::add($key, 0, now()->addMinutes(10));
            $count = Cache::increment($key);
            if ((int) $count <= $limit) {
                $this->log->record('page refused', $user, null, ['note' => '/'.$request->path()]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
