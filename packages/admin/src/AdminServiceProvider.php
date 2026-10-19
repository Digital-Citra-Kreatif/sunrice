<?php

declare(strict_types=1);

namespace Sunrice\Admin;

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
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'sunrice-admin');
        View::composer('sunrice-admin::app', function (ViewInstance $view): void {
            $view->with('sunricePanel', $this->app->make(Panel::class));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([PublishAssetsCommand::class]);
            $this->publishes([AdminAssets::distPath() => public_path(AdminAssets::BUILD_DIRECTORY)], 'sunrice-admin-assets');
        }
    }
}
