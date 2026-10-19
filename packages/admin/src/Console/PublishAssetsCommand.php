<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Sunrice\Admin\Assets\AdminAssets;

/**
 * sunrice:assets — copies the panel's built JavaScript and CSS to
 * public/vendor/sunrice-admin. Run it after installing or updating.
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

        return self::SUCCESS;
    }
}
