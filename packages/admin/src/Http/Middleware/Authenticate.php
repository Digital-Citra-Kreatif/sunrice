<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Panel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a user signed in with the panel's guard, and makes that guard
 * the default for the request. Guests go to the login page.
 *
 * @internal
 */
final class Authenticate
{
    public function __construct(private Panel $panel) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->panel->getGuard();

        if (Auth::guard($guard)->guest()) {
            if ($request->expectsJson() || ! Route::has('sunrice.admin.login')) {
                abort(401);
            }

            return redirect()->guest(route('sunrice.admin.login'));
        }

        Auth::shouldUse($guard);

        return $next($request);
    }
}
