<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Install\AddHasRoles;
use Sunrice\Admin\Actions\Install\AddPanelPlugins;
use Sunrice\Admin\Actions\Install\CreatePanelProvider;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\Plugins\BlogPlugin;
use Sunrice\Tests\Admin\Fixtures\Plugins\ShopPlugin;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\artisan;

/** A package manifest built from these packages' `extra.laravel`, in a folder of its own. */
function packagesOffering(string $dir, array $packages): PackageManifest
{
    @mkdir("{$dir}/vendor/composer", 0755, true);
    $installed = array_map(fn (string $name, array $laravel) => ['name' => $name, 'extra' => ['laravel' => $laravel]], array_keys($packages), $packages);
    file_put_contents("{$dir}/vendor/composer/installed.json", json_encode(['packages' => $installed]));

    return new PackageManifest(new Filesystem, $dir, "{$dir}/packages.php");
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/sunrice-install-'.uniqid();
    mkdir($this->dir, 0755, true);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->dir);
});

it('adds HasRoles to a user model as Laravel ships it', function () {
    $code = <<<'PHP'
        <?php

        namespace App\Models;

        // use Illuminate\Contracts\Auth\MustVerifyEmail;
        use Database\Factories\UserFactory;
        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use Illuminate\Foundation\Auth\User as Authenticatable;
        use Illuminate\Notifications\Notifiable;

        class User extends Authenticatable
        {
            /** @use HasFactory<UserFactory> */
            use HasFactory, Notifiable;
        }
        PHP;

    expect(app(AddHasRoles::class)->edit($code, 'User'))->toBe(<<<'PHP'
        <?php

        namespace App\Models;

        // use Illuminate\Contracts\Auth\MustVerifyEmail;
        use Database\Factories\UserFactory;
        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use Illuminate\Foundation\Auth\User as Authenticatable;
        use Illuminate\Notifications\Notifiable;
        use Spatie\Permission\Traits\HasRoles;

        class User extends Authenticatable
        {
            use HasRoles;
            /** @use HasFactory<UserFactory> */
            use HasFactory, Notifiable;
        }
        PHP);
});

it('adds the import after the namespace when there are none, and leaves an unknown file alone', function () {
    $edited = app(AddHasRoles::class)->edit("<?php\n\nnamespace App;\n\nfinal class Member extends \\Illuminate\\Foundation\\Auth\\User\n{\n}\n", 'Member');

    expect($edited)->toBe("<?php\n\nnamespace App;\n\nuse Spatie\\Permission\\Traits\\HasRoles;\n\nfinal class Member extends \\Illuminate\\Foundation\\Auth\\User\n{\n    use HasRoles;\n}\n")
        ->and(app(AddHasRoles::class)->edit("<?php\n\nreturn [];\n", 'User'))->toBeNull();
});

it('writes a panel provider with the installed plugins, and registers it', function () {
    config(['app.name' => "Rina's Shop"]);
    $packages = packagesOffering($this->dir, [
        'acme/shop' => ['providers' => ['Acme\\ShopServiceProvider'], 'sunrice-plugins' => [TestPlugin::class]],
        'acme/gone' => ['sunrice-plugins' => ['Acme\\Missing\\Plugin', Panel::class]], // not there, not a plugin
    ]);
    file_put_contents("{$this->dir}/providers.php", "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");

    $create = new CreatePanelProvider($packages);
    expect($create->plugins())->toBe([TestPlugin::class])
        ->and($create->handle("{$this->dir}/app/Providers/AdminPanelProvider.php", "{$this->dir}/providers.php"))->toBeTrue();

    expect(file_get_contents("{$this->dir}/app/Providers/AdminPanelProvider.php"))->toBe(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Providers;

        use Sunrice\Admin\Panel;
        use Sunrice\Admin\PanelProvider;
        use Sunrice\Tests\Admin\Fixtures\TestPlugin;

        final class AdminPanelProvider extends PanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                return $panel
                    ->path('admin')
                    ->brand('Rina\'s Shop')
                    ->plugin(TestPlugin::make());
            }
        }

        PHP)
        ->and(file_get_contents("{$this->dir}/providers.php"))->toContain('App\Providers\AdminPanelProvider::class', 'App\Providers\AppServiceProvider::class');

    // Run again: the file is kept as it is, and listed once.
    file_put_contents("{$this->dir}/app/Providers/AdminPanelProvider.php", '<?php // mine');
    $create->handle("{$this->dir}/app/Providers/AdminPanelProvider.php", "{$this->dir}/providers.php");
    expect(file_get_contents("{$this->dir}/app/Providers/AdminPanelProvider.php"))->toBe('<?php // mine')
        ->and(substr_count((string) file_get_contents("{$this->dir}/providers.php"), 'AdminPanelProvider'))->toBe(1);
});

it('writes a panel provider without plugins', function () {
    expect((new CreatePanelProvider(packagesOffering($this->dir, [])))->code('Acme', []))
        ->toContain("use Sunrice\\Admin\\PanelProvider;\n\nfinal class")
        ->toContain("->brand('Acme');\n");
});

it('sets the panel up when the app has none, then installs', function () {
    Storage::fake('public');
    app()->useAppPath("{$this->dir}/app");
    app()->useBootstrapPath("{$this->dir}/bootstrap");
    mkdir("{$this->dir}/bootstrap");
    file_put_contents("{$this->dir}/bootstrap/providers.php", "<?php\n\nreturn [];\n");
    app()->instance(PackageManifest::class, packagesOffering($this->dir, []));
    expect(app()->bound(Panel::class))->toBeFalse();

    artisan('sunrice:install', ['--no-user' => true])
        ->expectsOutputToContain('Panel provider')
        ->assertSuccessful();

    expect(app()->bound(Panel::class))->toBeTrue()
        ->and(app(Panel::class)->getPath())->toBe('admin')
        ->and(file_get_contents("{$this->dir}/bootstrap/providers.php"))->toContain('App\Providers\AdminPanelProvider::class');
});

it('adds HasRoles to the user model before setting the panel up, when nothing has loaded the model yet', function () {
    Storage::fake('public');
    $class = 'InstallTestApp'.uniqid();
    mkdir("{$this->dir}/models");
    file_put_contents("{$this->dir}/models/User.php", "<?php\n\nnamespace {$class};\n\nuse Illuminate\\Foundation\\Auth\\User as Authenticatable;\n\nclass User extends Authenticatable\n{\n    protected \$table = 'users';\n}\n");
    ClassLoader::getRegisteredLoaders()[array_key_first(ClassLoader::getRegisteredLoaders())]->addPsr4("{$class}\\", "{$this->dir}/models");
    config(['auth.providers.users.model' => "{$class}\\User"]);
    app()->useAppPath("{$this->dir}/app");
    app()->useBootstrapPath("{$this->dir}/bootstrap");
    mkdir("{$this->dir}/bootstrap");
    file_put_contents("{$this->dir}/bootstrap/providers.php", "<?php\n\nreturn [];\n");
    app()->instance(PackageManifest::class, packagesOffering($this->dir, []));
    expect(app(AddHasRoles::class)->missing("{$class}\\User"))->toBeTrue()
        ->and(class_exists("{$class}\\User", false))->toBeFalse();

    artisan('sunrice:install', ['--no-user' => true])
        ->expectsConfirmation("Add the HasRoles trait (spatie/laravel-permission) to {$class}\\User?", 'yes')
        ->assertSuccessful();

    expect(file_get_contents("{$this->dir}/models/User.php"))->toContain('use Spatie\\Permission\\Traits\\HasRoles;', '    use HasRoles;')
        ->and(app(AddHasRoles::class)->ready("{$class}\\User"))->toBeTrue()
        ->and(app()->bound(Panel::class))->toBeTrue();
});

it('stops before setting the panel up when the user model can\'t get HasRoles', function () {
    app()->useAppPath("{$this->dir}/app");
    config(['auth.providers.users.model' => 'Missing\\User']);
    expect(app(AddHasRoles::class)->missing('Missing\\User'))->toBeFalse();

    artisan('sunrice:install', ['--no-user' => true])
        ->expectsOutputToContain('run sunrice:install again')
        ->assertFailed();

    expect(file_exists("{$this->dir}/app/Providers/AdminPanelProvider.php"))->toBeFalse()
        ->and(app()->bound(Panel::class))->toBeFalse();
});

/** An app panel provider in its own file (loaded through Composer), registered: the app's panel. */
function appPanelProvider(string $dir, string $panel = "return \$panel\n            ->path('admin');"): string
{
    $namespace = 'InstallTestApp'.uniqid();
    mkdir("{$dir}/providers");
    file_put_contents("{$dir}/providers/AdminPanelProvider.php", <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Sunrice\\Admin\\Panel;
        use Sunrice\\Admin\\PanelProvider;
        use Sunrice\\Tests\\Admin\\Fixtures\\Plugins\\BlogPlugin;

        final class AdminPanelProvider extends PanelProvider
        {
            public function panel(Panel \$panel): Panel
            {
                {$panel}
            }
        }

        PHP);
    ClassLoader::getRegisteredLoaders()[array_key_first(ClassLoader::getRegisteredLoaders())]->addPsr4("{$namespace}\\", "{$dir}/providers");
    app()->register("{$namespace}\\AdminPanelProvider");

    return realpath("{$dir}/providers/AdminPanelProvider.php") ?: '';
}

function parses(string $code): bool
{
    try {
        PhpToken::tokenize($code, TOKEN_PARSE);

        return true;
    } catch (ParseError) {
        return false;
    }
}

it('adds a plugin to the provider it wrote for an app without plugins', function () {
    $code = (new CreatePanelProvider(packagesOffering($this->dir, [])))->code('Acme', []);

    $edited = app(AddPanelPlugins::class)->edit($code, [ShopPlugin::class]);

    expect($edited)->toBe(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Providers;

        use Sunrice\Admin\Panel;
        use Sunrice\Admin\PanelProvider;
        use Sunrice\Tests\Admin\Fixtures\Plugins\ShopPlugin;

        final class AdminPanelProvider extends PanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                return $panel
                    ->path('admin')
                    ->brand('Acme')
                    ->plugin(ShopPlugin::make());
            }
        }

        PHP)
        ->and(app(AddPanelPlugins::class)->edit((string) $edited, [ShopPlugin::class]))->toBe($edited);
});

it('adds plugins to the end of a longer chain, with the imports in order, once', function () {
    $code = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Providers;

        use Acme\Settings\GeneralSettings;
        use Sunrice\Admin\Panel;
        use Sunrice\Admin\PanelProvider;
        use Zed\Things;

        final class AdminPanelProvider extends PanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                $brand = config('app.name');

                return $panel
                    ->path('backend')
                    ->brand($brand) // the app's name
                    ->settings(GeneralSettings::class)
                    ->navigation(fn () => [
                        Things::item(),
                    ])
                    ->plugin(new \Sunrice\Tests\Admin\Fixtures\Plugins\BlogPlugin());
            }

            public function boot(): void
            {
                parent::boot();
            }
        }

        PHP;

    $add = app(AddPanelPlugins::class);
    $edited = $add->edit($code, [ShopPlugin::class, BlogPlugin::class, TestPlugin::class]);

    expect($edited)->toBe(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Providers;

        use Acme\Settings\GeneralSettings;
        use Sunrice\Admin\Panel;
        use Sunrice\Admin\PanelProvider;
        use Sunrice\Tests\Admin\Fixtures\Plugins\ShopPlugin;
        use Sunrice\Tests\Admin\Fixtures\TestPlugin;
        use Zed\Things;

        final class AdminPanelProvider extends PanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                $brand = config('app.name');

                return $panel
                    ->path('backend')
                    ->brand($brand) // the app's name
                    ->settings(GeneralSettings::class)
                    ->navigation(fn () => [
                        Things::item(),
                    ])
                    ->plugin(new \Sunrice\Tests\Admin\Fixtures\Plugins\BlogPlugin())
                    ->plugin(ShopPlugin::make())
                    ->plugin(TestPlugin::make());
            }

            public function boot(): void
            {
                parent::boot();
            }
        }

        PHP)
        ->and(parses((string) $edited))->toBeTrue()
        ->and($add->edit((string) $edited, [ShopPlugin::class, BlogPlugin::class, TestPlugin::class]))->toBe($edited);
});

it('uses an import the file has, and the full name when the short one is taken', function () {
    $code = "<?php\n\nnamespace App;\n\nuse Other\\ShopPlugin;\nuse Sunrice\\Tests\\Admin\\Fixtures\\Plugins\\BlogPlugin as Blog;\n\nfinal class P extends \\Sunrice\\Admin\\PanelProvider\n{\n    public function panel(\\Sunrice\\Admin\\Panel \$panel): \\Sunrice\\Admin\\Panel\n    {\n        return \$panel->path('admin');\n    }\n}\n";

    expect(app(AddPanelPlugins::class)->edit($code, [BlogPlugin::class, ShopPlugin::class]))
        ->toContain("return \$panel->path('admin')->plugin(Blog::make())->plugin(\\Sunrice\\Tests\\Admin\\Fixtures\\Plugins\\ShopPlugin::make());")
        ->not->toContain('use Sunrice\\Tests\\Admin\\Fixtures\\Plugins\\ShopPlugin;');
});

it('leaves a provider it doesn\'t recognise alone', function (string $body) {
    $code = "<?php\n\nnamespace App;\n\nuse Sunrice\\Admin\\Panel;\n\nfinal class P extends \\Sunrice\\Admin\\PanelProvider\n{\n    public function panel(Panel \$panel): Panel\n    {\n        {$body}\n    }\n}\n";

    expect(parses($code))->toBeTrue()
        ->and(app(AddPanelPlugins::class)->edit($code, [ShopPlugin::class]))->toBeNull();
})->with([
    'a method call' => ['return $this->configure($panel);'],
    'two returns' => ['if (app()->isLocal()) { $panel->path(\'dev\'); } else { return $panel; } return $panel->path(\'admin\');'],
    'a ternary' => ['return app()->isLocal() ? $panel : $panel->path(\'admin\');'],
    'not $panel first' => ['return Panel::make()->path(\'admin\');'],
]);

it('leaves a file that doesn\'t parse, or has no panel(), alone', function () {
    $add = app(AddPanelPlugins::class);

    expect($add->edit("<?php\n\nreturn \$panel->path(\n", [ShopPlugin::class]))->toBeNull()
        ->and($add->edit("<?php\n\nfinal class P\n{\n    public function other(\$panel)\n    {\n        return \$panel->path('a');\n    }\n}\n", [ShopPlugin::class]))->toBeNull();
});

it('finds the plugins installed packages offer that the panel doesn\'t have', function () {
    app()->instance(PackageManifest::class, packagesOffering($this->dir, [
        'acme/shop' => ['sunrice-plugins' => [ShopPlugin::class]],
        'acme/blog' => ['sunrice-plugins' => [BlogPlugin::class]],
    ]));
    $file = appPanelProvider($this->dir, "return \$panel\n            ->plugin(BlogPlugin::make());");

    $add = app(AddPanelPlugins::class);
    expect($add->missing(app(Panel::class)))->toBe([ShopPlugin::class])
        ->and($add->file())->toBe($file);
});

it('offers to add installed plugins the panel doesn\'t have, then syncs permissions in a fresh process', function () {
    Process::fake();
    Storage::fake('public');
    app()->instance(PackageManifest::class, packagesOffering($this->dir, [
        'acme/shop' => ['sunrice-plugins' => [ShopPlugin::class]],
        'acme/blog' => ['sunrice-plugins' => [BlogPlugin::class]],
    ]));
    $file = appPanelProvider($this->dir, "return \$panel\n            ->path('admin')\n            ->plugin(BlogPlugin::make());");

    artisan('sunrice:install', ['--no-user' => true, '--roles' => true])
        ->expectsOutputToContain(ShopPlugin::class)
        ->expectsConfirmation('Add them to '.$file.'?', 'yes')
        ->expectsOutputToContain('Plugins added')
        ->assertSuccessful();

    $code = (string) file_get_contents($file);
    expect($code)->toContain("use Sunrice\\Tests\\Admin\\Fixtures\\Plugins\\BlogPlugin;\nuse Sunrice\\Tests\\Admin\\Fixtures\\Plugins\\ShopPlugin;\n")
        ->toContain("            ->plugin(BlogPlugin::make())\n            ->plugin(ShopPlugin::make());\n")
        ->and(parses($code))->toBeTrue();
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $lint, $status);
    expect($status)->toBe(0);
    Process::assertRan(fn ($process) => $process->command === [PHP_BINARY, 'artisan', 'sunrice:sync-permissions', '--no-interaction']);
    Process::assertRan(fn ($process) => $process->command === [PHP_BINARY, 'artisan', 'sunrice:roles', '--no-interaction']);

    // Again: the file has them, nothing is added twice.
    expect(app(AddPanelPlugins::class)->handle($file, [ShopPlugin::class]))->toBeTrue()
        ->and(file_get_contents($file))->toBe($code);
});

it('adds them without asking with --plugins', function () {
    Process::fake();
    Storage::fake('public');
    app()->instance(PackageManifest::class, packagesOffering($this->dir, ['acme/shop' => ['sunrice-plugins' => [ShopPlugin::class]]]));
    $file = appPanelProvider($this->dir);

    artisan('sunrice:install', ['--no-user' => true, '--plugins' => true])->assertSuccessful();

    expect(file_get_contents($file))->toContain("->path('admin')\n            ->plugin(ShopPlugin::make());");
});

it('only says what to add without interaction, or when the file isn\'t one it recognises', function (array $options, string $panel) {
    Process::fake();
    Storage::fake('public');
    app()->instance(PackageManifest::class, packagesOffering($this->dir, ['acme/shop' => ['sunrice-plugins' => [ShopPlugin::class]]]));
    $file = appPanelProvider($this->dir, $panel);
    $before = file_get_contents($file);

    artisan('sunrice:install', ['--no-user' => true, ...$options])
        ->expectsOutputToContain('use '.ShopPlugin::class.';')
        ->expectsOutputToContain('->plugin(ShopPlugin::make())')
        ->expectsOutputToContain($file)
        ->assertSuccessful();

    expect(file_get_contents($file))->toBe($before);
    Process::assertNothingRan();
})->with([
    'no interaction' => [['--no-interaction' => true], "return \$panel\n            ->path('admin');"],
    'unrecognised' => [['--plugins' => true], 'return $this->app->isLocal() ? $panel : $panel->path(\'admin\');'],
]);

it('asks nothing when the panel has every installed plugin', function () {
    Process::fake();
    Storage::fake('public');
    app()->instance(PackageManifest::class, packagesOffering($this->dir, ['acme/blog' => ['sunrice-plugins' => [BlogPlugin::class]]]));
    appPanelProvider($this->dir, 'return $panel->plugin(BlogPlugin::make());');

    artisan('sunrice:install', ['--no-user' => true])
        ->doesntExpectOutputToContain('Installed plugins')
        ->assertSuccessful();

    Process::assertNothingRan();
});
