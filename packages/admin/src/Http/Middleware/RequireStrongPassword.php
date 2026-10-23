<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Sunrice\Admin\Auth\WeakPasswordAtLogin;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone who signed in with a weak password (WeakPasswordAtLogin) is
 * sent to "Choose a stronger password" from every panel page until they
 * do, in every session ("Remember me" too). Logging out stays open (it
 * isn't behind this middleware). Requests by AI access tokens have no
 * session and aren't affected.
 *
 * @internal
 */
final class RequireStrongPassword
{
    /** The routes open while a stronger password is awaited. */
    public const ALLOWED = ['sunrice.admin.password.stronger', 'sunrice.admin.password.stronger.update', 'sunrice.admin.logout', 'sunrice.admin.language.update'];

    public function __construct(private readonly WeakPasswordAtLogin $weak) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $request->hasSession() || ! $user instanceof Authenticatable || $request->routeIs(...self::ALLOWED) || ! $this->weak->flagged($user)) {
            return $next($request);
        }
        // The panel's own fetch() calls (not Inertia visits) get an answer they can show.
        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json(['message' => __('Choose a stronger password first.')], 409);
        }

        // Remembers the page asked for, to go on to once the new password is saved.
        return redirect()->guest(route('sunrice.admin.password.stronger'));
    }
}
