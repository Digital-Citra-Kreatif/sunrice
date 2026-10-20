<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Sunrice\Admin\Support\PanelLocale;
use Symfony\Component\HttpFoundation\Response;

/**
 * Speaks the user's panel language on the panel's pages (PanelLocale).
 *
 * @internal
 */
final class SetPanelLocale
{
    public function __construct(private PanelLocale $locale) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->locale->resolve($request);
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        /** @var Response */
        return $next($request);
    }
}
