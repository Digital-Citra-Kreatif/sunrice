<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Admin\Actions\Install\AddHasRoles;
use Sunrice\Admin\Actions\Install\CreatePanelProvider;
use Sunrice\Admin\Media\MediaHtaccess;
use Sunrice\Admin\Panel;

/**
 * `php artisan sunrice:install`: everything a new app needs, safe to run
 * again: the panel provider when the app has none (CreatePanelProvider),
 * the HasRoles trait on the user model (AddHasRoles), the panel's config,
 * the migrations, the admin's assets (and the
 * plugins', tagged laravel-assets), the storage link for media, an
 * `.htaccess` with headers for the media directory (MediaHtaccess),
 * permissions, the default roles (--roles) and a super admin.
 *
 * @internal
 */
final class InstallCommand extends Command
{
    protected $signature = 'sunrice:install
        {--roles : Also create the default roles (administrator, editor …)}
        {--no-user : Don\'t create a super admin}';

    protected $description = 'Install the admin panel: config, migrations, assets, permissions and the first admin';

    public function handle(): int
    {
        if (! $this->laravel->bound(Panel::class)) {
            $file = app_path('Providers/AdminPanelProvider.php');
            $registered = app(CreatePanelProvider::class)->handle($file);
            $this->components->twoColumnDetail('Panel provider', $file);
            if (! $registered) {
                $this->components->warn('Add '.CreatePanelProvider::CLASS_NAME.'::class to bootstrap/providers.php.');
            }
            if (! class_exists(CreatePanelProvider::CLASS_NAME)) {
                require_once $file;
            }
            $this->laravel->register(CreatePanelProvider::CLASS_NAME);
        }
        $panel = $this->laravel->make(Panel::class);

        $this->components->task('Config', fn () => $this->callSilently('vendor:publish', ['--tag' => 'sunrice-admin-config']) === 0);
        $this->components->task('Migrations', fn () => $this->call('migrate', ['--force' => true]) === 0);
        $this->components->task('Assets', fn () => $this->callSilently('sunrice:assets') === 0
            && $this->callSilently('vendor:publish', ['--tag' => 'laravel-assets', '--force' => true]) === 0);
        if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            $this->components->task('Storage link', fn () => $this->callSilently('storage:link') === 0);
        }
        if (($htaccess = MediaHtaccess::write()) !== null) {
            $this->components->twoColumnDetail('Media headers for Apache', $htaccess);
        }
        $this->components->task('Permissions', fn () => $this->callSilently('sunrice:sync-permissions') === 0);
        if ($this->option('roles') === true) {
            $this->call('sunrice:roles');
        }

        $model = $panel->getUserModel();
        if (! in_array(HasRoles::class, class_uses_recursive($model), true)) {
            if ($this->confirm("Add the HasRoles trait (spatie/laravel-permission) to {$model}?", true) && app(AddHasRoles::class)->handle($model)) {
                $this->components->info("Added HasRoles to {$model}. Now create the first admin: php artisan sunrice:make-admin.");

                return self::SUCCESS;
            }
            $this->components->warn("Add the Spatie\\Permission\\Traits\\HasRoles trait to {$model}, then run sunrice:make-admin to create the first admin.");

            return self::SUCCESS;
        }
        if ($this->option('no-user') !== true && $this->input->isInteractive() && $this->confirm('Create a super admin now?', true)) {
            return $this->call('sunrice:make-admin');
        }
        $this->components->info('Done. Create the first admin with php artisan sunrice:make-admin.');

        return self::SUCCESS;
    }
}
