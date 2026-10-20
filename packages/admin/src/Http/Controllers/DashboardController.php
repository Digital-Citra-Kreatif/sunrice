<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Dashboard\Widget;
use Sunrice\Admin\Panel;
use Throwable;

/**
 * The dashboard: the widgets this user may see, defined up front so the
 * page paints at once, each one's data deferred (one request per plugin).
 * A widget whose data fails is reported and shown as unavailable.
 *
 * @internal
 */
final class DashboardController
{
    public function __invoke(Request $request, Panel $panel): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable, 403);

        $widgets = [];
        $props = [];
        foreach ($panel->widgetsFor($user) as $widget) {
            $widgets[] = $widget->definition();
            $props[$widget->prop()] = Inertia::defer(fn (): array => self::resolve($widget, $user), $widget->getGroup());
        }

        return Inertia::render('Dashboard', ['widgets' => $widgets, ...$props]);
    }

    /** @return array{ok: true, data: array<string, mixed>}|array{ok: false} */
    private static function resolve(Widget $widget, Authorizable $user): array
    {
        try {
            return ['ok' => true, 'data' => $widget->resolve($user)];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false];
        }
    }
}
