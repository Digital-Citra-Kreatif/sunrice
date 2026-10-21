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

    /** How much code after a tag is read to judge it. */
    private const CODE_WINDOW = 65536;

    /**
     * Whether the file has PHP code in it, like an image with a script
     * appended: harmless while stored and served as it should be,
     * dangerous on a server misconfigured to run it. Finds `<?php`, the
     * short echo tag `<?=` (always on) and, where `short_open_tag` is on,
     * `<?` followed by a space or a line break. For those two, what
     * follows (up to `?>`, at most 64 KB) must read as PHP to count: PHP's
     * own tokenizer finds no character that can't appear in code, outside
     * comments and strings. Code that runs can't have one, whatever bytes
     * its comments or strings hold, while the bytes `<?=` turning up in a
     * photo's compressed data are followed by stray control bytes within a
     * few characters. Read in chunks.
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
                    $code = substr($buffer, $offset, self::CODE_WINDOW);
                    $close = strpos($code, '?>', 2);
                    if ($close !== false) {
                        $code = substr($code, 0, $close + 2);
                    } elseif (! $end && strlen($code) < self::CODE_WINDOW) {
                        // It may run into the next chunk: look again with it.
                        $carried = substr($buffer, $offset);
                        break;
                    }
                    if (self::readsAsCode($code)) {
                        return true;
                    }
                }
            }

            return false;
        } finally {
            fclose($handle);
        }
    }

    /** Whether PHP's tokenizer finds no character that code can't have. */
    private static function readsAsCode(string $code): bool
    {
        foreach (@token_get_all($code) as $token) {
            if (is_array($token) && $token[0] === T_BAD_CHARACTER) {
                return false;
            }
        }

        return true;
    }
}
