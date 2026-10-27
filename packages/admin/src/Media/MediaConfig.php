<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Illuminate\Http\UploadedFile;

/**
 * The media settings from config/sunrice-admin.php, typed and with safe
 * fallbacks.
 *
 * @internal
 */
final class MediaConfig
{
    /** Never stored, whatever the config allows: they could run on the server or in the browser. */
    public const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'shtml',
        'html', 'htm', 'xhtml', 'xht', 'js', 'mjs', 'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com',
        'jsp', 'asp', 'aspx', 'htaccess', 'htpasswd', 'svgz', 'xml', 'xsl', 'swf',
    ];

    /** File contents that browsers would run as a page or script, whatever the extension. */
    public const BLOCKED_MIME_TYPES = [
        'text/html', 'application/xhtml+xml', 'text/javascript', 'application/javascript', 'application/x-javascript',
        'application/x-httpd-php', 'text/x-php', 'application/x-php', 'application/x-sh', 'text/x-shellscript',
        'application/x-msdownload', 'application/x-executable', 'application/x-dosexec',
    ];

    /** @var array{0: mixed, 1: array<string, array{width: int|null, height: int|null, crop: bool}>}|null the config value last read, and the sizes made of it */
    private static ?array $imageSizes = null;

    /** @var array{0: mixed, 1: array<string, int>}|null the config value last read, and its ranks */
    private static ?array $imageSizeRanks = null;

    public static function disk(): string
    {
        $disk = config('sunrice-admin.media.disk');

        return is_string($disk) && $disk !== '' ? $disk : 'public';
    }

    public static function directory(): string
    {
        $directory = config('sunrice-admin.media.directory');

        return trim(is_string($directory) ? $directory : 'media', '/');
    }

    /** The private disk files in the trash are kept on (`media.trash_disk`, default `local`). */
    public static function trashDisk(): string
    {
        $disk = config('sunrice-admin.media.trash_disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    /** The folder of the trash disk they're kept in (`media.trash_directory`). */
    public static function trashDirectory(): string
    {
        $directory = config('sunrice-admin.media.trash_directory');
        $directory = trim(is_string($directory) ? $directory : '', '/');

        return $directory !== '' ? $directory : 'sunrice-media-trash';
    }

    /** The real limit: the config's, capped by PHP's upload limits. */
    public static function maxKilobytes(): int
    {
        $configured = config('sunrice-admin.media.max_upload_kb');
        $configured = is_numeric($configured) ? (int) $configured : 20480;
        $php = (int) floor(UploadedFile::getMaxFilesize() / 1024);

        return $php > 0 ? min($configured, $php) : $configured;
    }

    /**
     * Whether the media disk is served through a link from config
     * `filesystems.links` (php artisan storage:link) that doesn't exist:
     * its files' URLs then lead nowhere. One file check.
     */
    public static function storageLinkMissing(): bool
    {
        $root = config('filesystems.disks.'.self::disk().'.root');
        $links = config('filesystems.links');
        if (config('filesystems.disks.'.self::disk().'.driver') !== 'local' || ! is_string($root) || ! is_array($links)) {
            return false;
        }
        foreach ($links as $link => $target) {
            if (is_string($link) && is_string($target) && rtrim($target, '/\\') === rtrim($root, '/\\')) {
                return ! file_exists($link);
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function allowedExtensions(): array
    {
        $allowed = config('sunrice-admin.media.allowed_extensions');
        $allowed = is_array($allowed) ? array_map(fn (mixed $ext) => is_string($ext) ? strtolower(ltrim($ext, '.')) : '', $allowed) : [];

        return array_values(array_diff(array_filter($allowed), self::BLOCKED_EXTENSIONS));
    }

    /** @return array<string, array{width: int|null, height: int|null, crop: bool}> */
    public static function imageSizes(): array
    {
        $sizes = config('sunrice-admin.media.image_sizes');
        // Read for every image of a list (Media::url(), srcset(), toData()): parsed once per config value.
        if (self::$imageSizes !== null && self::$imageSizes[0] === $sizes) {
            return self::$imageSizes[1];
        }
        $result = [];
        foreach (is_array($sizes) ? $sizes : [] as $name => $size) {
            if (! is_string($name) || preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $name) !== 1 || ! is_array($size)) {
                continue;
            }
            $width = isset($size[0]) && is_numeric($size[0]) ? max(1, (int) $size[0]) : null;
            $height = isset($size[1]) && is_numeric($size[1]) ? max(1, (int) $size[1]) : null;
            $crop = ($size[2] ?? 'fit') === 'crop';
            if ($width === null && $height === null) {
                continue;
            }
            $result[$name] = ['width' => $width, 'height' => $height, 'crop' => $crop && $width !== null && $height !== null];
        }
        self::$imageSizes = [$sizes, $result];

        return $result;
    }

    /**
     * Where each configured size name comes in the config (image_sizes' keys, as written), for listing a
     * file's sizes in that order whatever order the database kept them in.
     *
     * @return array<string, int>
     */
    public static function imageSizeRanks(): array
    {
        $sizes = config('sunrice-admin.media.image_sizes', []);
        if (self::$imageSizeRanks === null || self::$imageSizeRanks[0] !== $sizes) {
            self::$imageSizeRanks = [$sizes, array_flip(array_map(strval(...), array_keys((array) $sizes)))];
        }

        return self::$imageSizeRanks[1];
    }

    /** Make image sizes on the queue: as `media.queue` says, or (null) whenever jobs don't run in the request anyway. */
    public static function queue(): bool
    {
        $queue = config('sunrice-admin.media.queue');
        if (is_bool($queue)) {
            return $queue;
        }
        $connection = config('queue.default');
        $driver = is_string($connection) ? config("queue.connections.{$connection}.driver") : null;

        return is_string($driver) && ! in_array($driver, ['sync', 'null'], true);
    }

    public static function maxPixels(): int
    {
        $max = config('sunrice-admin.media.max_pixels');

        return is_numeric($max) ? (int) $max : 40_000_000;
    }
}
