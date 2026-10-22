<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Sunrice\Admin\Actions\Install\CreatePanelProvider;
use Sunrice\Cms\CmsPlugin;
use Sunrice\StarterKit\StarterKitPlugin;

it('offers the CMS and the starter kit to the panel provider sunrice:install writes', function () {
    $dir = sys_get_temp_dir().'/sunrice-install-'.uniqid();
    mkdir("{$dir}/vendor/composer", 0755, true);
    $packages = array_map(
        fn (string $package) => json_decode((string) file_get_contents(dirname(__DIR__, 2)."/packages/{$package}/composer.json"), true, flags: JSON_THROW_ON_ERROR),
        ['admin', 'cms', 'starter-kit'],
    );
    file_put_contents("{$dir}/vendor/composer/installed.json", json_encode(['packages' => $packages]));

    $plugins = (new CreatePanelProvider(new PackageManifest(new Filesystem, $dir, "{$dir}/packages.php")))->plugins();
    (new Filesystem)->deleteDirectory($dir);

    expect($plugins)->toBe([CmsPlugin::class, StarterKitPlugin::class]);
});
