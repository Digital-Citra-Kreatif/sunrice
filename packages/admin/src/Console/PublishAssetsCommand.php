<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Assets\Precompress;
use Sunrice\Admin\Panel;

/**
 * sunrice:assets — copies the panel's built JavaScript and CSS to
 * public/vendor/sunrice-admin, and each plugin bundle's that says where
 * it's published (PluginBundle::publishedFrom()) to its folder, with gzip
 * (and Brotli) copies of the scripts and stylesheets (Precompress). Run
 * it after installing or updating. A folder that is a link to the build
 * (a symlinked public/vendor/…) is left alone: nothing is deleted through it.
 *
 * @internal
 */
final class PublishAssetsCommand extends Command
{
    protected $signature = 'sunrice:assets';

    protected $description = 'Publish the admin panel\'s and its plugins\' JavaScript and CSS to public/';

    public function handle(Filesystem $files): int
    {
        $source = AdminAssets::distPath();
        if (! is_file($source.'/manifest.json')) {
            $this->components->error("The panel's assets aren't built ({$source} has no manifest.json).");

            return self::FAILURE;
        }

        $published = [];
        if ($this->publish($files, 'Sunrice', $source, public_path(AdminAssets::BUILD_DIRECTORY))) {
            $published[] = public_path(AdminAssets::BUILD_DIRECTORY);
        }
        foreach (app()->bound(Panel::class) ? app(Panel::class)->getBundles() : [] as $bundle) {
            $from = $bundle->sourcePath();
            $to = $bundle->publishedPath();
            if ($from === null || $to === null) {
                continue;
            }
            if (! is_dir($from)) {
                $this->components->warn("{$bundle->displayName()}: its build ({$from}) isn't there, so nothing was published.");

                continue;
            }
            if ($this->publish($files, $bundle->displayName(), $from, $to)) {
                $published[] = $to;
            }
        }
        // Compressed copies for the web server, of what was copied (never into a package's own build).
        foreach ($published as $directory) {
            Precompress::directory($directory);
        }

        return self::SUCCESS;
    }

    /** Replaces the folder with a copy of the build, unless it is the build (a link to it) or holds it. */
    private function publish(Filesystem $files, string $name, string $source, string $target): bool
    {
        $shown = self::shown($target);
        $realSource = realpath($source);
        $realTarget = realpath($target);
        if (is_link($target) || ($realSource !== false && $realTarget !== false && $realSource === $realTarget)) {
            $this->components->info("{$name}: {$shown} is a link to the package's build, so it's up to date already and was left as it is.");

            return false;
        }
        if ($realSource !== false && $realTarget !== false && str_starts_with($realSource.'/', $realTarget.'/')) {
            $this->components->error("{$name}: {$shown} holds the package's build ({$source}), so it wasn't replaced. Publish it to a folder of its own.");

            return false;
        }
        $files->deleteDirectory($target);
        $files->copyDirectory($source, $target);
        $this->components->info("Published {$name} to {$shown}.");

        return true;
    }

    private static function shown(string $path): string
    {
        $public = rtrim(public_path(), '/').'/';

        return str_starts_with($path, $public) ? 'public/'.substr($path, strlen($public)) : $path;
    }
}
