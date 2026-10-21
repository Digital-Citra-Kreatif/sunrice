<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Illuminate\Http\UploadedFile;

/**
 * What an uploaded file really holds, whatever its name says: for the
 * media library and for anything else that takes files (the CMS's form
 * uploads).
 *
 * @api
 */
final class FileContent
{
    /** Extensions that promise a raster image, checked against the content. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'bmp'];

    /**
     * Whether a file named as an image (.jpg, .png…) is one: its content
     * is an image that PHP can read the size of. Other extensions pass.
     */
    public static function matchesExtension(UploadedFile $file): bool
    {
        if (! in_array(strtolower($file->getClientOriginalExtension()), self::IMAGE_EXTENSIONS, true)) {
            return true;
        }
        $mime = strtolower((string) $file->getMimeType());

        return str_starts_with($mime, 'image/') && ! str_contains($mime, 'svg') && @getimagesize($file->getRealPath()) !== false;
    }

    /**
     * Whether the file has PHP code in it (`<?php`), like an image with a
     * script appended: harmless while stored and served as it should be,
     * dangerous on a server misconfigured to run it. Read in chunks.
     */
    public static function containsPhp(UploadedFile $file): bool
    {
        $handle = @fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }
        try {
            $tail = '';
            while (! feof($handle)) {
                $chunk = fread($handle, 1048576);
                if ($chunk === false) {
                    break;
                }
                if (stripos($tail.$chunk, '<?php') !== false) {
                    return true;
                }
                $tail = substr($chunk, -4);
            }

            return false;
        } finally {
            fclose($handle);
        }
    }
}
