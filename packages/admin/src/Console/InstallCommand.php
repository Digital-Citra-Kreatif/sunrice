<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Admin\Panel;

/**
 * `php artisan sunrice:install`: everything a new app needs, safe to run
 * again: the panel's config, the migrations, the admin's assets (and the
 * plugins', tagged laravel-assets), the storage link for media,
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

    public function handle(Panel $panel): int
    {
        $this->components->task('Config', fn () => $this->callSilently('vendor:publish', ['--tag' => 'sunrice-admin-config']) === 0);
        $this->components->task('Migrations', fn () => $this->call('migrate', ['--force' => true]) === 0);
        $this->components->task('Assets', fn () => $this->callSilently('sunrice:assets') === 0
            && $this->callSilently('vendor:publish', ['--tag' => 'laravel-assets', '--force' => true]) === 0);
        if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            $this->components->task('Storage link', fn () => $this->callSilently('storage:link') === 0);
        }
        $this->components->task('Permissions', fn () => $this->callSilently('sunrice:sync-permissions') === 0);
        if ($this->option('roles') === true) {
            $this->call('sunrice:roles');
        }

        $model = $panel->getUserModel();
        if (! in_array(HasRoles::class, class_uses_recursive($model), true)) {
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
