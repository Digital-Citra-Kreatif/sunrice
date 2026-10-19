<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Sunrice\Admin\Panel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the permission to open the panel (Panel::ACCESS). Being signed
 * in is not enough: an app's customers share the users table.
 *
 * @internal
 */
final class EnsureCanAccessPanel
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof Authorizable && $user->can(Panel::ACCESS), 403);

        return $next($request);
    }
}
