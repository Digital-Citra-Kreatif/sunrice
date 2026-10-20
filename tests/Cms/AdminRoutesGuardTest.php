<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Middleware\Authenticate;
use Sunrice\Admin\Http\Middleware\EnsureCanAccessPanel;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelRoutes;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Http\Middleware\AuthorizeContent;

/** @return Collection<int, RegisteredRoute> the admin routes whose name starts with `$prefix` */
function adminRoutes(string $prefix): Collection
{
    return collect(Route::getRoutes()->getRoutes())->filter(fn (RegisteredRoute $route) => str_starts_with((string) $route->getName(), $prefix))->values();
}

beforeEach(fn () => usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make())));

it('puts every admin route, the panel\'s and the CMS\'s, behind sign-in and panel access', function () {
    $routes = adminRoutes('sunrice.admin.');
    expect($routes->count())->toBeGreaterThan(50);

    $unguarded = $routes
        ->reject(fn (RegisteredRoute $route) => in_array($route->getName(), PanelRoutes::PUBLIC_ROUTES, true))
        ->reject(fn (RegisteredRoute $route) => in_array(Authenticate::class, $route->gatherMiddleware(), true)
            && in_array(EnsureCanAccessPanel::class, $route->gatherMiddleware(), true))
        ->map(fn (RegisteredRoute $route) => $route->getName())->all();

    expect($unguarded)->toBe([]);
});

it('declares a permission or content check on every CMS admin route', function () {
    $routes = adminRoutes('sunrice.admin.cms.');
    expect($routes->count())->toBeGreaterThan(40);

    $unguarded = $routes
        ->reject(fn (RegisteredRoute $route) => collect($route->gatherMiddleware())
            ->contains(fn (string $middleware) => str_starts_with($middleware, 'can:') || str_starts_with($middleware, AuthorizeContent::class)))
        ->map(fn (RegisteredRoute $route) => $route->getName())->all();

    expect($unguarded)->toBe([]);
});
