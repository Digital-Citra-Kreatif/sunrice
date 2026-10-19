<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Console\PublishAssetsCommand;

/**
 * Boots the admin panel package. The panel itself and its plugins are
 * configured in the app's panel provider (see PanelProvider).
 *
 * @api
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registered here too, so the panel works in apps that turn off package discovery.
        $this->app->register(InertiaServiceProvider::class);
        $this->mergeConfigFrom(dirname(__DIR__).'/config/sunrice-admin.php', 'sunrice-admin');
    }

    public function boot(): void
    {
        RateLimiter::for('sunrice-password', function (Request $request): Limit {
            $perMinute = config('sunrice-admin.auth.throttle.password_resets_per_minute', 5);

            return Limit::perMinute(is_numeric($perMinute) ? (int) $perMinute : 5)->by((string) $request->ip());
        });

        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'sunrice-admin');
        View::composer('sunrice-admin::app', function (ViewInstance $view): void {
            $view->with('sunricePanel', $this->app->make(Panel::class));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([PublishAssetsCommand::class]);
            $this->publishes([dirname(__DIR__).'/config/sunrice-admin.php' => config_path('sunrice-admin.php')], 'sunrice-admin-config');
            $this->publishes([AdminAssets::distPath() => public_path(AdminAssets::BUILD_DIRECTORY)], 'sunrice-admin-assets');
        }
    }
}
