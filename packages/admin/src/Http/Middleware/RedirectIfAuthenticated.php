<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Sunrice\Admin\Panel;
use Symfony\Component\HttpFoundation\Response;

/**
 * The login and password pages are for guests: someone already signed in
 * with the panel's guard goes to the dashboard.
 *
 * @internal
 */
final class RedirectIfAuthenticated
{
    public function __construct(private Panel $panel) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard($this->panel->getGuard())->check()) {
            return redirect()->route('sunrice.admin.dashboard');
        }

        return $next($request);
    }
}
