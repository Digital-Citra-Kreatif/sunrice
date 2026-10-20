<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Sunrice\Admin\Ai\PanelServer;
use Sunrice\Admin\Exceptions\PanelMisconfigured;

/**
 * Extend this in your app to set up the panel, and register it like any
 * service provider:
 *
 *     final class AdminPanelProvider extends PanelProvider
 *     {
 *         public function panel(Panel $panel): Panel
 *         {
 *             return $panel->path('admin')->brand('Acme')->plugin(CmsPlugin::make());
 *         }
 *     }
 *
 * An app has one panel.
 *
 * @api
 */
abstract class PanelProvider extends ServiceProvider
{
    abstract public function panel(Panel $panel): Panel;

    public function register(): void
    {
        if ($this->app->bound(Panel::class)) {
            throw new PanelMisconfigured('An app has one panel, but '.static::class.' sets up a second one.');
        }

        $this->app->singleton(Panel::class, fn (): Panel => $this->panel(Panel::make()));
    }

    public function boot(): void
    {
        $panel = $this->app->make(Panel::class);
        $panel->boot();

        if (! $this->app->routesAreCached()) {
            PanelRoutes::register($panel);
        }
        // For an agent on the server itself: `php artisan mcp:start sunrice` (stdio), as sunrice-admin.ai.local_user.
        if ($this->app->runningInConsole() && $panel->hasAiAccess() && config('sunrice-admin.ai.enabled', true) !== false) {
            Mcp::local('sunrice', PanelServer::class);
        }
    }
}
