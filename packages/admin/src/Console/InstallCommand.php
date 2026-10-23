<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Sunrice\Admin\Actions\Install\AddHasRoles;
use Sunrice\Admin\Actions\Install\AddPanelPlugins;
use Sunrice\Admin\Actions\Install\CreatePanelProvider;
use Sunrice\Admin\Media\MediaHtaccess;
use Sunrice\Admin\Panel;

/**
 * `php artisan sunrice:install`: everything a new app needs, safe to run
 * again: the panel provider when the app has none (CreatePanelProvider),
 * plugins installed since that the panel doesn't have (AddPanelPlugins),
 * the HasRoles trait on the user model (AddHasRoles), the panel's config,
 * the migrations, the admin's assets (and the
 * plugins', tagged laravel-assets), the storage link for media, an
 * `.htaccess` with headers for the media directory (MediaHtaccess),
 * permissions, the default roles (--roles), the plugins' install steps
 * (Panel::installStep()) and a super admin.
 *
 * @internal
 */
final class InstallCommand extends Command
{
    protected $signature = 'sunrice:install
        {--roles : Also create the default roles (administrator, editor …)}
        {--plugins : Add installed plugins the panel doesn\'t have to its provider without asking}
        {--no-user : Don\'t create a super admin}';

    protected $description = 'Install the admin panel: config, migrations, assets, permissions and the first admin';

    public function handle(): int
    {
        if (! $this->laravel->bound(Panel::class)) {
            // Before the panel boots (it needs HasRoles) and before anything loads the user model.
            if (! $this->userModelHasRoles()) {
                return self::FAILURE;
            }
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
        // Added to the file now, they're on the panel from the next command on (this app has booted).
        $pluginsAdded = $this->addMissingPlugins();

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
        if ($pluginsAdded) {
            $this->inFreshProcess();
        } else {
            $this->components->task('Permissions', fn () => $this->callSilently('sunrice:sync-permissions') === 0);
            if ($this->option('roles') === true) {
                $this->call('sunrice:roles');
            }
        }

        $output = new InstallOutput($this, $this->input->isInteractive());
        foreach ($this->laravel->make(Panel::class)->getInstallSteps() as $step) {
            $step($output);
        }

        if ($this->option('no-user') !== true && $this->input->isInteractive() && $this->confirm('Create a super admin now?', true)) {
            return $this->call('sunrice:make-admin');
        }
        $this->components->info('Done. Create the first admin with php artisan sunrice:make-admin.');

        return self::SUCCESS;
    }

    /**
     * Offers to add the plugins installed packages offer that the panel
     * doesn't have to its provider. Whether it did.
     */
    private function addMissingPlugins(): bool
    {
        $add = app(AddPanelPlugins::class);
        $missing = $add->missing($this->laravel->make(Panel::class));
        if ($missing === []) {
            return false;
        }
        $this->components->warn('Installed plugins the panel doesn\'t have yet:');
        $this->components->bulletList($missing);
        $file = $add->file();
        if ($file === null) {
            $this->components->warn('The panel provider isn\'t an app file it can edit.');
            $this->pluginInstructions($add, $missing, null);

            return false;
        }
        $asked = $this->option('plugins') === true || ($this->input->isInteractive() && $this->confirm('Add them to '.$this->relative($file).'?', true));
        if (! $asked) {
            $this->pluginInstructions($add, $missing, $file);

            return false;
        }
        if (! $add->handle($file, $missing)) {
            $this->components->warn($this->relative($file).' has no `return $panel->…;` in panel() it recognises: it was left as it is.');
            $this->pluginInstructions($add, $missing, $file);

            return false;
        }
        $this->components->twoColumnDetail('Plugins added', $this->relative($file));

        return true;
    }

    /** @param list<string> $plugins */
    private function pluginInstructions(AddPanelPlugins $add, array $plugins, ?string $file): void
    {
        $lines = $add->instructions($plugins);
        $this->line('  To add them, in '.($file === null ? 'your panel provider' : $this->relative($file)).', import them:');
        foreach ($lines['imports'] as $line) {
            $this->line("    {$line}");
        }
        $this->line('  and add to the end of `return $panel->…;` in panel(), before the `;`:');
        foreach ($lines['calls'] as $line) {
            $this->line("    {$line}");
        }
        $this->line('  Then run php artisan sunrice:install again.');
        $this->newLine();
    }

    /**
     * Plugins just added to the provider aren't on this run's panel (it
     * booted before): their permissions and roles are synced by a fresh
     * `php artisan`, which loads the provider as it is now.
     */
    private function inFreshProcess(): void
    {
        $commands = ['sunrice:sync-permissions', ...($this->option('roles') === true ? ['sunrice:roles'] : [])];
        foreach ($commands as $command) {
            $result = null;
            $this->components->task($command === 'sunrice:roles' ? 'Roles' : 'Permissions', function () use ($command, &$result) {
                $result = Process::path(base_path())->timeout(600)->run([PHP_BINARY, 'artisan', $command, '--no-interaction']);

                return $result->successful();
            });
            if ($result === null || ! $result->successful()) {
                $this->components->warn('Run php artisan '.implode(' and php artisan ', $commands).' to finish.');

                return;
            }
            if ($command === 'sunrice:roles') {
                $this->output->write($result->output());
            }
        }
    }

    private function relative(string $file): string
    {
        $base = rtrim(base_path(), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    /** The user model of the panel this command sets up (on the default "web" guard), with HasRoles. */
    private function userModelHasRoles(): bool
    {
        $provider = config('auth.guards.web.provider');
        $model = is_string($provider) ? config("auth.providers.{$provider}.model") : null;
        if (! is_string($model)) {
            $this->components->error('The "web" guard has no user model in config/auth.php.');

            return false;
        }
        $roles = app(AddHasRoles::class);
        if ($roles->missing($model)) {
            if (! $this->confirm("Add the HasRoles trait (spatie/laravel-permission) to {$model}?", true) || ! $roles->handle($model)) {
                $this->components->warn("Add the Spatie\\Permission\\Traits\\HasRoles trait to {$model}, then run sunrice:install again.");

                return false;
            }
            $this->components->twoColumnDetail('HasRoles', $model);
        }
        if (! $roles->ready($model)) {
            $this->components->warn("{$model} doesn't have the Spatie\\Permission\\Traits\\HasRoles trait yet: add it, then run sunrice:install again.");

            return false;
        }

        return true;
    }
}
