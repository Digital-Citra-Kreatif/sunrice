<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Sunrice\Admin\Security\SecurityLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts the pages a signed-in person is refused (403). Many in a short
 * time (config `sunrice-admin.security.alerts.forbidden`, in 10 minutes)
 * looks like someone exploring what a taken-over account can reach: it is
 * a "pages refused" security event, which alerts.
 *
 * @internal
 */
final class WatchRefusedPages
{
    private const MINUTES = 10;

    public function __construct(private SecurityLog $log) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();
        if ($response->getStatusCode() !== 403 || ! $user instanceof Model || ! is_scalar($user->getKey())) {
            return $response;
        }
        $limit = config('sunrice-admin.security.alerts.forbidden', 15);
        $limit = is_numeric($limit) ? max(1, (int) $limit) : 15;
        $key = 'sunrice-admin.refused:'.$user->getKey();
        Cache::add($key, 0, now()->addMinutes(self::MINUTES));
        $count = Cache::increment($key);
        if ($count === $limit) {
            $name = $user->getAttribute('name');
            $this->log->record('pages refused', $user, is_string($name) ? $name : null,
                ['note' => "{$count} pages within ".self::MINUTES.' minutes (the last: /'.ltrim($request->path(), '/').')']);
        }

        return $response;
    }
}
