<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Install\AddHasRoles;
use Sunrice\Admin\Actions\Install\CreatePanelProvider;
use Sunrice\Admin\Panel;
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
