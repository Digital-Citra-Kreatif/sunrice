<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Foundation\Events\VendorTagPublished;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\TokenScope;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Assets\Precompress;
use Sunrice\Admin\Auth\WeakPasswordAtLogin;
use Sunrice\Admin\Console\AiTokenCommand;
use Sunrice\Admin\Console\InstallCommand;
use Sunrice\Admin\Console\MakeAdminCommand;
use Sunrice\Admin\Console\MediaSizesCommand;
use Sunrice\Admin\Console\OptimizeImagesCommand;
use Sunrice\Admin\Console\PruneActivityCommand;
use Sunrice\Admin\Console\PublishAssetsCommand;
use Sunrice\Admin\Console\SeedRolesCommand;
use Sunrice\Admin\Console\SyncPermissionsCommand;
use Sunrice\Admin\Console\TwoFactorCommand;
use Sunrice\Admin\Console\UnlockCommand;
use Sunrice\Admin\Events\ActivityRecorded;
use Sunrice\Admin\Events\LoginThrottled;
use Sunrice\Admin\Events\SecurityEventRecorded;
use Sunrice\Admin\Events\SettingsSaved;
use Sunrice\Admin\Http\ErrorPages;
use Sunrice\Admin\Locks\KeptEdit;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityAlerts;
use Sunrice\Admin\Security\SecurityLog;
use Sunrice\Admin\Security\SecuritySettings;
use Sunrice\Admin\Settings\SettingsStore;
use Sunrice\Admin\Support\PackageConfig;
use Throwable;

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
        // A read-only AI token may only view. This must be the Gate's first
        // "before" check, ahead of spatie/laravel-permission's (added when the
        // Gate is resolved) and the super admin's, so nothing grants first.
        $this->callAfterResolving(GateContract::class, function (GateContract $gate): void {
            $gate->before(function (mixed $user, string $ability): ?bool {
                $scope = $this->app->make(TokenScope::class);

                return $scope->refuses($ability) ? false : null;
            });
        });

        // Registered here too, so the panel works in apps that turn off package discovery.
        $this->app->register(InertiaServiceProvider::class);
        // 403, 404, 419 and 500 under the panel's path: the panel's Error page.
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->renderable(fn (Throwable $e, Request $request) => ErrorPages::render($e, $request));
            }
        });
        $this->app->register(PermissionServiceProvider::class);
        $this->app->register(McpServiceProvider::class);
        // Key by key at every depth, so a config file published on an older version gets the keys added since.
        PackageConfig::merge($this->app, dirname(__DIR__).'/config/sunrice-admin.php', 'sunrice-admin', whole: ['locales', 'media.image_sizes', 'security.csp.sources', 'ai.commands']);
        // Inertia's DevTools record every page's props (users and their emails, settings) and, in
        // `local`, serve them at /_inertia/devtools to anyone, signed in or not. Off unless the app
        // turns them on itself (INERTIA_DEVTOOLS_ENABLED=true).
        if (config('inertia.devtools.enabled') === null) {
            config(['inertia.devtools.enabled' => false]);
        }
        // Settings are read once per request (or job).
        $this->app->scoped(SettingsStore::class);
        // Per request: its source and the lines it may merge.
        $this->app->scoped(ActivityLog::class);
        $this->app->scoped(AccountGuard::class);
        $this->app->scoped(WeakPasswordAtLogin::class);
        $this->app->scoped(TokenScope::class);
    }

    public function boot(): void
    {
        // Forgot / reset password, per IP: a few a minute, and a cap an hour so one address can't
        // send reset mail to address after address. Hitting a limit is a security event (and an alert).
        RateLimiter::for('sunrice-password', function (Request $request): array {
            $perMinute = config('sunrice-admin.auth.throttle.password_resets_per_minute', 5);
            $perHour = config('sunrice-admin.auth.throttle.password_resets_per_hour', 20);
            $blocked = function (Request $request, array $headers): never {
                if (Cache::add('sunrice-password-blocked-logged|'.$request->ip(), true, 60)) {
                    app(SecurityLog::class)->record('password reset blocked', null, null, ['note' => 'too many password reset requests from this IP']);
                }

                throw new ThrottleRequestsException('Too Many Attempts.', null, $headers);
            };

            return [
                Limit::perMinute(is_numeric($perMinute) ? (int) $perMinute : 5)->by((string) $request->ip())->response($blocked),
                Limit::perHour(is_numeric($perHour) ? (int) $perHour : 20)->by('hour|'.$request->ip())->response($blocked),
            ];
        });

        // Typing and resending login codes, per IP; each code also allows only a few guesses.
        RateLimiter::for('sunrice-login-code', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');

        // AI agents per token: 120 requests a minute by default.
        RateLimiter::for('sunrice-ai', function (Request $request): Limit {
            $perMinute = config('sunrice-admin.ai.requests_per_minute', 120);

            return Limit::perMinute(is_numeric($perMinute) ? (int) $perMinute : 120)->by(sha1((string) $request->bearerToken()));
        });

        // Super admins may do everything.
        Gate::before(function (mixed $user): ?bool {
            if (! $user instanceof Model || ! in_array(HasRoles::class, class_uses_recursive($user), true)) {
                return null;
            }

            return $user->hasRole(SyncPermissions::superAdminRole()) ? true : null; // @phpstan-ignore method.notFound (HasRoles is checked above)
        });

        // Keep the permissions in step with the code after every migrate.
        // Security alerts by email.
        Event::listen(SecurityEventRecorded::class, [SecurityAlerts::class, 'handleSecurityEvent']);
        Event::listen(ActivityRecorded::class, [SecurityAlerts::class, 'handleActivity']);
        // Switching two-factor login is a security event (turning it off alerts).
        Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
            if ($event->section === SecuritySettings::key() && in_array('twoFactor', $event->changed, true)) {
                $on = app(SecuritySettings::class)->twoFactor;
                $user = auth(app(Panel::class)->getGuard())->user();
                app(SecurityLog::class)->record('two-factor turned '.($on ? 'on' : 'off'), $user instanceof Model ? $user : null, null,
                    ['note' => app()->runningInConsole() ? 'from the console' : 'in Settings → Security']);
            }
        });

        // Login limits reached are security events.
        Event::listen(LoginThrottled::class, function (LoginThrottled $event): void {
            $security = $this->app->make(SecurityLog::class);
            $minutes = (int) ceil($event->seconds / 60);
            $account = $security->findAccount($event->email);
            $security->record(
                $event->scope === 'account' ? 'account locked' : 'login blocked',
                $account,
                $account === null ? $event->email : null,
                ['scope' => $event->scope, 'note' => match ($event->scope) {
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
        // The panel's words in other languages (lang/{code}.json, English text as keys; PanelLocale).
        $this->loadJsonTranslationsFrom(dirname(__DIR__).'/lang');
        View::composer('sunrice-admin::app', function (ViewInstance $view): void {
            $view->with('sunricePanel', $this->app->make(Panel::class));
        });

        // Old activity log lines go daily (when the app runs the scheduler).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('sunrice:activity-prune')->daily();
            // Unsaved changes kept from edit locks taken over, after a month.
            $schedule->command('model:prune', ['--model' => [KeptEdit::class]])->daily();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([AiTokenCommand::class, InstallCommand::class, SeedRolesCommand::class, PublishAssetsCommand::class, SyncPermissionsCommand::class, MakeAdminCommand::class, MediaSizesCommand::class, PruneActivityCommand::class, UnlockCommand::class, TwoFactorCommand::class, OptimizeImagesCommand::class]);
            $this->publishes([dirname(__DIR__).'/config/sunrice-admin.php' => config_path('sunrice-admin.php')], 'sunrice-admin-config');
            $this->publishes([AdminAssets::distPath() => public_path(AdminAssets::BUILD_DIRECTORY)], 'sunrice-admin-assets');
            // Compressed copies of what vendor:publish copies into the panel's or a plugin bundle's folder.
            Event::listen(VendorTagPublished::class, [Precompress::class, 'published']);
        }
    }
}
