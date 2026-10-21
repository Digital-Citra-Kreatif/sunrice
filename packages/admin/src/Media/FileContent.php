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
     * Whether the file has PHP code in it, like an image with a script
     * appended: harmless while stored and served as it should be,
     * dangerous on a server misconfigured to run it. Finds `<?php`, the
     * short echo tag `<?=` (always on) and, where `short_open_tag` is on,
     * `<?` followed by a space or a line break; for those two, followed by
     * code (plain text up to `?>` or the end of the file), so that the
     * bytes `<?=` turning up in a photo's compressed data don't count.
     * Read in chunks.
     */
    public static function containsPhp(UploadedFile $file): bool
    {
        $handle = @fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }
        $open = filter_var(ini_get('short_open_tag'), FILTER_VALIDATE_BOOL) ? '(?:=|\s)' : '=';
        try {
            $carried = '';
            while (! feof($handle)) {
                $chunk = fread($handle, 1048576);
                if ($chunk === false) {
                    break;
                }
                $buffer = $carried.$chunk;
                $end = feof($handle);
                if (stripos($buffer, '<?php') !== false) {
                    return true;
                }
                $carried = substr($buffer, -4);
                preg_match_all('/<\?'.$open.'/', $buffer, $tags, PREG_OFFSET_CAPTURE);
                foreach ($tags[0] as [, $offset]) {
                    // Code: plain text after the tag, up to the closing tag or the end.
                    preg_match('/\G<\?'.$open.'[\t\n\r\x20-\x7E]*/', $buffer, $code, 0, $offset);
                    $text = $code[0] ?? '';
                    if (str_contains(substr($text, 2), '?>')) {
                        return true;
                    }
                    if ($offset + strlen($text) === strlen($buffer)) {
                        if ($end) {
                            return true;
                        }
                        // It runs into the next chunk: look again with it.
                        $carried = substr($buffer, $offset);
                        break;
                    }
                }
            }

            return false;
        } finally {
            fclose($handle);
        }
    }
}
