<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Dashboard\Widget;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Preferences\Preferences;
use Throwable;

/**
 * The dashboard: the widgets this user may see, defined up front so the
 * page paints at once, each one's data deferred (one request per plugin).
 * A widget whose data fails is reported and shown as unavailable.
 * Notices are resolved with the page instead (they decide whether they
 * show at all, so they're cheap checks), above the tiles; one that
 * returns nothing or fails (reported) is left out. The
 * widgets the user folded away are a preference (COLLAPSED), so they stay
 * folded on every device.
 *
 * @internal
 */
final class DashboardController
{
    /** The preference holding the keys of the widgets the user folded away. */
    public const COLLAPSED = 'dashboard.collapsed';

    public function __invoke(Request $request, Panel $panel, Preferences $preferences): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable, 403);

        $widgets = [];
        $notices = [];
        $props = [];
        foreach ($panel->widgetsFor($user) as $widget) {
            if ($widget->type === Widget::NOTICE) {
                $notice = self::notice($widget, $user);
                if ($notice !== null) {
                    $notices[] = $notice;
                }

                continue;
            }
            $widgets[] = $widget->definition();
            $props[$widget->prop()] = Inertia::defer(fn (): array => self::resolve($widget, $user), $widget->getGroup());
        }
        $keys = array_column($widgets, 'key');
        $saved = $preferences->get($user, self::COLLAPSED, []);
        // The ones this dashboard shows, in its order.
        $collapsed = is_array($saved) ? array_values(array_filter($keys, fn (string $key): bool => in_array($key, $saved, true))) : [];

        return Inertia::render('Dashboard', ['widgets' => $widgets, 'notices' => $notices, 'collapsed' => $collapsed, ...$props]);
    }

    /** @return array<string, mixed>|null the notice to show, or null: nothing to say, or it failed (reported) */
    private static function notice(Widget $widget, Authorizable $user): ?array
    {
        try {
            return $widget->resolve($user);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** @return array{ok: true, data: array<string, mixed>}|array{ok: false} */
    private static function resolve(Widget $widget, Authorizable $user): array
    {
        try {
            // Never null here: only a notice's data may be, and notices aren't deferred.
            $data = $widget->resolve($user);

            return $data === null ? ['ok' => false] : ['ok' => true, 'data' => $data];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false];
        }
    }
}
