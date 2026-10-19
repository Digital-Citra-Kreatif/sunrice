<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Sunrice\Admin\Panel;

/**
 * The workbench app (`composer serve`): its users may all open the panel
 * until roles arrive. Sign in at /_workbench/login/admin@example.com.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(AdminPanelProvider::class);
    }

    public function boot(): void
    {
        Gate::define(Panel::ACCESS, fn () => true);
    }
}
