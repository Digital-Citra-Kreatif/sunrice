<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * An `.htaccess` for the media directory, which the web server serves
 * without PHP: Apache then sends `nosniff` for every file, a CSP that
 * keeps an SVG from running anything when it's opened by itself, and
 * `Content-Disposition: attachment` for documents and archives. Other web
 * servers ignore the file (the media docs have their rules).
 * `sunrice:install` writes it to a local media disk that has none.
 *
 * Apache needs `AllowOverride FileInfo` for `Header`, which Laravel's own
 * `public/.htaccess` (mod_rewrite) needs as well.
 *
 * @internal
 */
final class MediaHtaccess
{
    public const CONTENTS = <<<'HTACCESS'
        # Written by `php artisan sunrice:install`. Headers for media files, which the web server serves without PHP.
        # Apache only (others ignore this file: see the panel's media docs). Change it as you like; it's never overwritten.
        <IfModule mod_headers.c>
            # Browsers use the declared type and never guess.
            Header always set X-Content-Type-Options "nosniff"
            # An SVG opened by itself can't run scripts or load anything.
            <FilesMatch "(?i)\.svg$">
                Header always set Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:"
                Header always set Content-Disposition "inline"
            </FilesMatch>
            # Documents and archives are downloaded, not opened on this domain.
            <FilesMatch "(?i)\.(json|txt|csv|zip|pdf|docx?|xlsx?|pptx?|odt|ods|odp|rtf)$">
                Header always set Content-Disposition "attachment"
            </FilesMatch>
        </IfModule>

        HTACCESS;

    /**
     * Writes the file into the media directory when the media disk is a
     * local one and the file isn't there yet; its path, or null when
     * nothing was written.
     */
    public static function write(): ?string
    {
        $disk = MediaConfig::disk();
        if (config("filesystems.disks.{$disk}.driver") !== 'local') {
            return null;
        }
        try {
            $storage = Storage::disk($disk);
            $path = ltrim(MediaConfig::directory().'/.htaccess', '/');
            if ($storage->exists($path)) {
                return null;
            }
            $storage->put($path, self::CONTENTS);

            return $storage->path($path);
        } catch (Throwable) {
            return null;
        }
    }
}
