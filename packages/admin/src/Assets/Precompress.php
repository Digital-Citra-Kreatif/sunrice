<?php

declare(strict_types=1);

namespace Sunrice\Admin\Assets;

use Illuminate\Foundation\Events\VendorTagPublished;
use Sunrice\Admin\Panel;

/**
 * Writes a gzip (`.gz`) copy next to each published script and stylesheet,
 * and a Brotli one (`.br`) when PHP has the brotli extension, for web
 * servers that serve precompressed files (nginx gzip_static, Caddy's
 * precompressed): `sunrice:assets` does it for the panel's assets and
 * every plugin bundle's that says where it's published
 * (PluginBundle::publishedFrom()), and so does `vendor:publish` for those
 * folders. A copy newer than its file is kept.
 *
 * @internal
 */
final class Precompress
{
    private const COMPRESSIBLE = '/\.(js|css)$/';

    /**
     * Compresses the scripts and stylesheets in a folder (and below).
     *
     * @return int how many copies were written
     */
    public static function directory(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }
        $written = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile() || preg_match(self::COMPRESSIBLE, $file->getFilename()) !== 1) {
                continue;
            }
            $written += self::file($file->getPathname());
        }

        return $written;
    }

    /**
     * The folders the panel's and the plugin bundles' assets are published to.
     *
     * @return list<string>
     */
    public static function publishedDirectories(): array
    {
        $directories = [public_path(AdminAssets::BUILD_DIRECTORY)];
        if (app()->bound(Panel::class)) {
            foreach (app(Panel::class)->getBundles() as $bundle) {
                if ($bundle->publishedPath() !== null) {
                    $directories[] = $bundle->publishedPath();
                }
            }
        }

        return array_values(array_unique($directories));
    }

    /** After `vendor:publish`: compresses what it copied into the panel's or a bundle's folder. */
    public static function published(VendorTagPublished $event): void
    {
        $ours = self::publishedDirectories();
        foreach ($event->paths as $destination) {
            if (is_string($destination) && in_array(rtrim($destination, '/'), $ours, true)) {
                self::directory($destination);
            }
        }
    }

    private static function file(string $path): int
    {
        $written = 0;
        $content = null;
        $copies = ['gz' => fn (string $data): string|false => gzencode($data, 9)];
        if (function_exists('brotli_compress')) {
            $copies['br'] = fn (string $data): string|false => brotli_compress($data, 11);
        }
        foreach ($copies as $extension => $compress) {
            $copy = $path.'.'.$extension;
            if (is_file($copy) && filemtime($copy) >= filemtime($path)) {
                continue;
            }
            $content ??= (string) file_get_contents($path);
            $compressed = $compress($content);
            if (is_string($compressed)) {
                file_put_contents($copy, $compressed);
                $written++;
            }
        }

        return $written;
    }
}
