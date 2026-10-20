<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai;

use Illuminate\Support\Facades\File;

/**
 * Earlier versions of the views AI agents changed or deleted, the newest
 * DeveloperTools::VERSIONS of each, kept under storage so a change can be
 * undone.
 *
 * @internal
 */
final class ViewVersions
{
    /** Keep the view's current content as a version, before it changes. */
    public function keep(string $name, string $content): string
    {
        $dir = $this->dir($name);
        File::ensureDirectoryExists($dir);
        $version = now()->format('Ymd-His-u');
        File::put("{$dir}/{$version}.blade.php", $content);
        foreach (array_slice($this->all($name), DeveloperTools::VERSIONS) as $old) {
            File::delete("{$dir}/{$old}.blade.php");
        }

        return $version;
    }

    /** @return list<string> the versions, newest first */
    public function all(string $name): array
    {
        $versions = array_map(fn (string $file): string => basename($file, '.blade.php'), glob($this->dir($name).'/*.blade.php') ?: []);
        rsort($versions);

        return $versions;
    }

    public function read(string $name, string $version): ?string
    {
        if (preg_match('/^\d{8}-\d{6}-\d{6}$/', $version) !== 1) {
            return null;
        }
        $file = $this->dir($name)."/{$version}.blade.php";

        return is_file($file) ? File::get($file) : null;
    }

    private function dir(string $name): string
    {
        return DeveloperTools::versionsPath().'/'.str_replace('.', '/', $name);
    }
}
