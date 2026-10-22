<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Assets\Precompress;

/**
 * sunrice:assets — copies the panel's built JavaScript and CSS to
 * public/vendor/sunrice-admin, with gzip (and Brotli) copies of the
 * scripts and stylesheets, its own and the plugin bundles' (Precompress).
 * Run it after installing or updating.
 *
 * @internal
 */
final class PublishAssetsCommand extends Command
{
    protected $signature = 'sunrice:assets';

    protected $description = 'Publish the admin panel\'s JavaScript and CSS to public/';

    public function handle(Filesystem $files): int
    {
        $source = AdminAssets::distPath();
        if (! is_file($source.'/manifest.json')) {
            $this->components->error("The panel's assets aren't built ({$source} has no manifest.json).");

            return self::FAILURE;
        }

        $target = public_path(AdminAssets::BUILD_DIRECTORY);
        $files->deleteDirectory($target);
        $files->copyDirectory($source, $target);
        $this->components->info('Published the admin assets to '.AdminAssets::BUILD_DIRECTORY.'.');
        // Compressed copies for the web server: the panel's, and the plugin bundles' already published.
        foreach (Precompress::publishedDirectories() as $directory) {
            Precompress::directory($directory);
        }

        return self::SUCCESS;
    }
}
