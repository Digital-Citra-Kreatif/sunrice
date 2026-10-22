<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Install;

use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\ServiceProvider;
use Sunrice\Admin\Contracts\Plugin;

/**
 * Writes the app's panel provider, `app/Providers/AdminPanelProvider.php`
 * (at `/admin`, named after `app.name`, with every installed plugin), and
 * adds it to `bootstrap/providers.php`. Used by `sunrice:install` when the
 * app has no panel yet; an existing file is kept as it is.
 *
 * Plugins are found in the installed packages: a package names its plugin
 * classes in its composer.json, under `extra.laravel.sunrice-plugins`
 * (the CMS: `["Sunrice\\Cms\\CmsPlugin"]`).
 *
 * @internal
 */
final class CreatePanelProvider
{
    public const CLASS_NAME = 'App\\Providers\\AdminPanelProvider';

    public function __construct(private PackageManifest $packages) {}

    /** Whether it's in bootstrap/providers.php now (false: that file is missing, add it by hand). */
    public function handle(?string $path = null, ?string $providersFile = null): bool
    {
        $path ??= app_path('Providers/AdminPanelProvider.php');
        if (! is_file($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, $this->code(config('app.name'), $this->plugins()));
        }

        return ServiceProvider::addProviderToBootstrapFile(self::CLASS_NAME, $providersFile);
    }

    /**
     * The plugin classes installed packages offer (that exist and are plugins).
     *
     * @return list<class-string<Plugin>>
     */
    public function plugins(): array
    {
        $plugins = [];
        foreach ($this->packages->config('sunrice-plugins') as $class) {
            if (is_string($class) && class_exists($class) && is_a($class, Plugin::class, true)) {
                $plugins[] = $class;
            }
        }
        sort($plugins);

        return array_values(array_unique($plugins));
    }

    /** @param list<class-string<Plugin>> $plugins */
    public function code(mixed $brand, array $plugins): string
    {
        $imports = array_map(fn (string $plugin) => "use {$plugin};\n", $plugins);
        $calls = array_map(fn (string $plugin) => "\n            ->plugin(".class_basename($plugin).'::make())', $plugins);
        $brand = var_export(is_string($brand) && $brand !== '' ? $brand : 'Laravel', true);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Providers;

            use Sunrice\\Admin\\Panel;
            use Sunrice\\Admin\\PanelProvider;
            {$this->lines($imports)}
            final class AdminPanelProvider extends PanelProvider
            {
                public function panel(Panel \$panel): Panel
                {
                    return \$panel
                        ->path('admin')
                        ->brand({$brand}){$this->lines($calls)};
                }
            }

            PHP;
    }

    /** @param list<string> $lines */
    private function lines(array $lines): string
    {
        return implode('', $lines);
    }
}
