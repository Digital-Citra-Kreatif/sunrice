<?php

declare(strict_types=1);

namespace Sunrice\Admin\Assets;

/**
 * The versions of the built files the browser downloads: the md5 of a
 * file's content. Each file is stat'ed once per request (a scoped instance,
 * which Octane and queue workers renew), and hashed again only when its
 * mtime, size or inode changed, so a long-lived worker picks up a newly
 * published file on its next request and a republished copy with the same
 * content keeps its version (no needless reload).
 *
 * @internal
 */
final class AssetVersions
{
    /** @var array<string, array{string, string}> per path: its stat key and its content's md5, for the process */
    private static array $hashes = [];

    /** @var array<string, string|null> this request's, by path */
    private array $files = [];

    /** The md5 of the file's content, or null when there's no such file. */
    public function file(string $path): ?string
    {
        if (array_key_exists($path, $this->files)) {
            return $this->files[$path];
        }
        clearstatcache(true, $path);
        $stat = @stat($path);
        if ($stat === false || ! is_file($path)) {
            return $this->files[$path] = null;
        }
        $key = $stat['mtime'].'|'.$stat['size'].'|'.$stat['ino'];
        if ((self::$hashes[$path][0] ?? null) !== $key) {
            $hash = md5_file($path);
            if ($hash === false) {
                return $this->files[$path] = null;
            }
            self::$hashes[$path] = [$key, $hash];
        }

        return $this->files[$path] = self::$hashes[$path][1];
    }

    /** The first of these files that exists, and its version: the published copy before the package's build. */
    public function first(string ...$paths): ?string
    {
        foreach ($paths as $path) {
            if (($version = $this->file($path)) !== null) {
                return $version;
            }
        }

        return null;
    }
}
