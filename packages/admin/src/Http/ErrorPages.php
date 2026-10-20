<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Sunrice\Admin\Http\Middleware\HandleInertiaRequests;
use Sunrice\Admin\Panel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * 403, 404, 419 and 500 under the panel's path as the panel's Error page,
 * inside its layout for someone signed in, instead of the framework's
 * plain pages. JSON requests keep their JSON answer, and a 500 with
 * APP_DEBUG keeps the framework's page with the stack trace.
 *
 * It runs inside the route's middleware when the error comes from a
 * route (so RecordRefusedPages still sees a 403); an address the panel
 * doesn't know reaches the panel's fallback route (signed in) first.
 *
 * @internal
 */
final class ErrorPages
{
    public const STATUSES = [403, 404, 419, 500];

    public static function render(Throwable $e, Request $request): ?Response
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        if (! in_array($status, self::STATUSES, true) || $request->expectsJson() || ! app()->bound(Panel::class)) {
            return null;
        }
        // Not HTTP errors: the framework redirects to the login or back with errors.
        if (! $e instanceof HttpExceptionInterface && ! self::isServerError($e)) {
            return null;
        }
        if ($status === 500 && config('app.debug') === true) {
            return null;
        }
        $path = trim(app(Panel::class)->getPath(), '/');
        if (! $request->is($path) && ! $request->is($path.'/*')) {
            return null;
        }

        try {
            // Without the panel's middleware (an error before it ran), share its page data here.
            $shared = Inertia::getShared('sunrice') === null ? app(HandleInertiaRequests::class)->share($request) : [];

            return Inertia::render('Error', [...$shared, 'status' => $status])
                ->rootView('sunrice-admin::app')
                ->toResponse($request)
                ->setStatusCode($status);
        } catch (Throwable) {
            // The error page itself failed: the framework's plain page.
            return null;
        }
    }

    /** Anything but the framework's own redirecting exceptions. */
    private static function isServerError(Throwable $e): bool
    {
        return ! $e instanceof AuthenticationException
            && ! $e instanceof ValidationException
            && ! $e instanceof HttpResponseException;
    }
}
