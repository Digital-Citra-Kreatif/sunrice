<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Sunrice\Admin\Ai\PanelServer;
use Sunrice\Admin\Http\Controllers\Auth\LoginController;
use Sunrice\Admin\Http\Controllers\MissingPageController;
use Sunrice\Admin\Http\Middleware\Authenticate;
use Sunrice\Admin\Http\Middleware\AuthenticateAiToken;
use Sunrice\Admin\Http\Middleware\EnsureAccountActive;
use Sunrice\Admin\Http\Middleware\EnsureCanAccessPanel;
use Sunrice\Admin\Http\Middleware\HandleInertiaRequests;
use Sunrice\Admin\Http\Middleware\PanelSecurityHeaders;
use Sunrice\Admin\Http\Middleware\RecordRefusedPages;
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
        return ['web', PanelSecurityHeaders::class, HandleInertiaRequests::class, RecordRefusedPages::class, ...$panel->getMiddleware()];
    }

    /** Route names reachable without Panel::ACCESS: the guest pages and logout. */
    public const PUBLIC_ROUTES = [
        'sunrice.admin.login', 'sunrice.admin.login.store', 'sunrice.admin.login.code', 'sunrice.admin.login.code.verify', 'sunrice.admin.login.code.resend',
        'sunrice.admin.password.request', 'sunrice.admin.password.email',
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
        // What a relation field may choose: "/admin/resources/products/options/category?q=".
        Route::get("resources/{$key}/options/{field}", [ResourceController::class, 'options'])
            ->name("resources.{$key}.options")->defaults('resource', $key);
    }

    public static function register(Panel $panel): void
    {
        // The MCP endpoint: a bearer token, no session or CSRF, per-token limits.
        if ($panel->hasAiAccess() && config('sunrice-admin.ai.enabled', true) !== false) {
            $path = config('sunrice-admin.ai.path', 'mcp');
            Mcp::web(is_string($path) ? $path : 'mcp', PanelServer::class)
                ->middleware([AuthenticateAiToken::class, 'throttle:sunrice-ai'])
                ->name('sunrice.ai.mcp');
        }

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
                    if ($panel->hasAiAccess()) {
                        require dirname(__DIR__).'/routes/ai.php';
                    }
                    foreach ($panel->getResources() as $resource) {
                        self::resource($resource);
                    }
                    foreach ($panel->getRoutes() as $routes) {
                        $routes();
                    }
                    // Matched after every other route: an unknown panel address is the panel's 404 page.
                    Route::fallback(MissingPageController::class)->name('missing');
                    // Other methods too, so an unknown address answers 404, not 405.
                    Route::match(['POST', 'PUT', 'PATCH', 'DELETE'], '{fallbackPlaceholder}', MissingPageController::class)
                        ->where('fallbackPlaceholder', '.*')->fallback()->name('missing.other');
                });
            });
    }
}
