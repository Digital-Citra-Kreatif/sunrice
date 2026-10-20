<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every panel response: no framing by other sites
 * (clickjacking), no referrer sent elsewhere, no MIME sniffing, and a
 * Content-Security-Policy when the app sets one
 * (sunrice-admin.security.headers.csp; none by default, because Inertia
 * and Vite setups vary).
 *
 * @internal
 */
final class PanelSecurityHeaders
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('X-Content-Type-Options', 'nosniff');

        $csp = config('sunrice-admin.security.headers.csp');
        if (is_string($csp) && trim($csp) !== '' && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', trim($csp));
        }

        return $response;
    }
}
