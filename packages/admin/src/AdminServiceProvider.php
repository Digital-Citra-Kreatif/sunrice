<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Console\MakeAdminCommand;
use Sunrice\Admin\Console\MediaSizesCommand;
use Sunrice\Admin\Console\PruneActivityCommand;
use Sunrice\Admin\Console\PublishAssetsCommand;
use Sunrice\Admin\Console\SyncPermissionsCommand;
use Sunrice\Admin\Events\LoginThrottled;
use Sunrice\Admin\Security\SecurityLog;
use Sunrice\Admin\Settings\SettingsStore;

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
        $this->app->register(PermissionServiceProvider::class);
        $this->mergeConfigFrom(dirname(__DIR__).'/config/sunrice-admin.php', 'sunrice-admin');
        // Settings are read once per request (or job).
        $this->app->scoped(SettingsStore::class);
        // Per request: its source and the lines it may merge.
        $this->app->scoped(ActivityLog::class);
    }

    public function boot(): void
    {
        RateLimiter::for('sunrice-password', function (Request $request): Limit {
            $perMinute = config('sunrice-admin.auth.throttle.password_resets_per_minute', 5);

            return Limit::perMinute(is_numeric($perMinute) ? (int) $perMinute : 5)->by((string) $request->ip());
        });

        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');

        // Super admins may do everything.
        Gate::before(function (mixed $user): ?bool {
            if (! $user instanceof Model || ! in_array(HasRoles::class, class_uses_recursive($user), true)) {
                return null;
            }

            return $user->hasRole(SyncPermissions::superAdminRole()) ? true : null; // @phpstan-ignore method.notFound (HasRoles is checked above)
        });

        // Keep the permissions in step with the code after every migrate.
        // Login limits reached are security events.
        Event::listen(LoginThrottled::class, function (LoginThrottled $event): void {
            $security = $this->app->make(SecurityLog::class);
            $minutes = (int) ceil($event->seconds / 60);
            $account = $security->findAccount($event->email);
            $security->record(
                $event->scope === 'account' ? 'account locked' : 'login blocked',
                $account,
                $account === null ? $event->email : null,
                ['note' => match ($event->scope) {
                    'account' => "too many wrong passwords from any IP; locked for {$minutes} min",
                    'ip' => "too many wrong passwords from this IP; blocked for {$minutes} min",
                    default => "too many wrong passwords; blocked for {$minutes} min",
                }],
            );
        });

        Event::listen([MigrationsEnded::class, NoPendingMigrations::class], function (): void {
            $table = config('permission.table_names.permissions');
            if ($this->app->bound(Panel::class) && is_string($table) && Schema::hasTable($table)) {
                $this->app->make(SyncPermissions::class)->handle();
            }
        });

        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'sunrice-admin');
        View::composer('sunrice-admin::app', function (ViewInstance $view): void {
            $view->with('sunricePanel', $this->app->make(Panel::class));
        });

        // Old activity log lines go daily (when the app runs the scheduler).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('sunrice:activity-prune')->daily();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([PublishAssetsCommand::class, SyncPermissionsCommand::class, MakeAdminCommand::class, MediaSizesCommand::class, PruneActivityCommand::class]);
            $this->publishes([dirname(__DIR__).'/config/sunrice-admin.php' => config_path('sunrice-admin.php')], 'sunrice-admin-config');
            $this->publishes([AdminAssets::distPath() => public_path(AdminAssets::BUILD_DIRECTORY)], 'sunrice-admin-assets');
        }
    }
}
