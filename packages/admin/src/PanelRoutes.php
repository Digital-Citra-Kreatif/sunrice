<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Controllers\Auth\LoginController;
use Sunrice\Admin\Http\Middleware\Authenticate;
use Sunrice\Admin\Http\Middleware\EnsureAccountActive;
use Sunrice\Admin\Http\Middleware\EnsureCanAccessPanel;
use Sunrice\Admin\Http\Middleware\HandleInertiaRequests;
use Sunrice\Admin\Http\Middleware\RedirectIfAuthenticated;
use Sunrice\Admin\Resources\Http\ResourceController;
use Sunrice\Admin\Resources\RegisteredResource;

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

    /** Route names reachable without Panel::ACCESS: the guest pages and logout. */
    public const PUBLIC_ROUTES = [
        'sunrice.admin.login', 'sunrice.admin.login.store', 'sunrice.admin.password.request', 'sunrice.admin.password.email',
        'sunrice.admin.password.reset', 'sunrice.admin.password.update', 'sunrice.admin.logout',
    ];

    /** @return list<class-string> */
    public static function authenticated(): array
    {
        return [Authenticate::class, EnsureAccountActive::class, EnsureCanAccessPanel::class];
    }

    /** The routes of one resource: "/admin/products", named "sunrice.admin.resources.products.*". */
    private static function resource(RegisteredResource $resource): void
    {
        $key = $resource->key();
        Route::prefix($key)->name("resources.{$key}.")->group(function () use ($key): void {
            $routes = [
                Route::get('/', [ResourceController::class, 'index'])->name('index'),
                Route::get('create', [ResourceController::class, 'create'])->name('create'),
                Route::post('/', [ResourceController::class, 'store'])->name('store'),
                Route::get('export', [ResourceController::class, 'export'])->name('export'),
                Route::post('bulk/{action}', [ResourceController::class, 'bulk'])->name('bulk'),
                Route::get('{record}/edit', [ResourceController::class, 'edit'])->name('edit'),
                Route::put('{record}', [ResourceController::class, 'update'])->name('update'),
                Route::delete('{record}', [ResourceController::class, 'destroy'])->name('destroy'),
                Route::post('{record}/actions/{action}', [ResourceController::class, 'action'])->name('action'),
            ];
            foreach ($routes as $route) {
                $route->defaults('resource', $key);
            }
        });
    }

    public static function register(Panel $panel): void
    {
        Route::middleware(self::middleware($panel))
            ->prefix($panel->getPath())
            ->name('sunrice.admin.')
            ->group(function () use ($panel): void {
                Route::middleware(RedirectIfAuthenticated::class)->group(dirname(__DIR__).'/routes/guest.php');

                // Signed in is enough to sign out (also for accounts that may not open the panel).
                Route::post('logout', [LoginController::class, 'destroy'])->middleware(Authenticate::class)->name('logout');

                Route::middleware(self::authenticated())->group(function () use ($panel): void {
                    require dirname(__DIR__).'/routes/admin.php';
                    if ($panel->hasMediaLibrary()) {
                        require dirname(__DIR__).'/routes/media.php';
                    }
                    foreach ($panel->getResources() as $resource) {
                        self::resource($resource);
                    }
                    foreach ($panel->getRoutes() as $routes) {
                        $routes();
                    }
                });
            });
    }
}
