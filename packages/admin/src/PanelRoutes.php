<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Middleware\Authenticate;
use Sunrice\Admin\Http\Middleware\EnsureCanAccessPanel;
use Sunrice\Admin\Http\Middleware\HandleInertiaRequests;

/**
 * Registers the panel's routes: the panel's own and the plugins', all
 * under the panel's path and names "sunrice.admin.*", and all behind
 * sign-in and Panel::ACCESS.
 *
 * @internal
 */
final class PanelRoutes
{
    /** @return list<string|class-string> */
    public static function middleware(Panel $panel): array
    {
        return ['web', HandleInertiaRequests::class, ...$panel->getMiddleware()];
    }

    /** @return list<class-string> */
    public static function authenticated(): array
    {
        return [Authenticate::class, EnsureCanAccessPanel::class];
    }

    public static function register(Panel $panel): void
    {
        Route::middleware(self::middleware($panel))
            ->prefix($panel->getPath())
            ->name('sunrice.admin.')
            ->group(function () use ($panel): void {
                Route::middleware(self::authenticated())->group(function () use ($panel): void {
                    require dirname(__DIR__).'/routes/admin.php';
                    foreach ($panel->getRoutes() as $routes) {
                        $routes();
                    }
                });
            });
    }
}
