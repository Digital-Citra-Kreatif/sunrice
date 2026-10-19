<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Sunrice\Admin\Media\Jobs\GenerateImageSizes;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaConfig;

/**
 * Shrinks an oversized image and compresses it again, in place: the file
 * keeps its path (links keep working), the version goes up and the sizes
 * are made again. The first original is kept as a backup
 * (RestoreOriginalImage puts it back). GIFs (animations) and SVGs are
 * left alone, and an image that already fits is only written when it gets
 * smaller.
 *
 * @api
 */
final class OptimizeImage
{
    private const SUPPORTED = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    private const LOSSY = ['jpg', 'jpeg', 'webp', 'avif'];

    /**
     * @return array{status: 'optimized'|'skipped', reason: string|null, before: int, after: int, width: int|null, height: int|null}
     */
    public function handle(Media $media, int $maxWidth, int $maxHeight, int $quality, bool $backup = true, bool $dryRun = false): array
    {
        $extension = strtolower(pathinfo($media->path, PATHINFO_EXTENSION));
        $result = ['status' => 'skipped', 'reason' => null, 'before' => $media->size, 'after' => $media->size, 'width' => $media->width, 'height' => $media->height];

        if (! $media->isImage() || ! in_array($extension, self::SUPPORTED, true)) {
            return ['reason' => 'not a JPEG, PNG, WebP or AVIF image'] + $result;
        }
        $disk = Storage::disk($media->disk);
        $original = $disk->get($media->path);
        if ($original === null) {
            return ['reason' => 'the file is missing'] + $result;
        }
        if ($media->width !== null && $media->height !== null && $media->width * $media->height > MediaConfig::maxPixels()) {
            return ['reason' => 'too large to decode safely'] + $result;
        }

        $image = (new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver))->read($original);
        $resize = $image->width() > $maxWidth || $image->height() > $maxHeight;
        if ($resize) {
            $image->scaleDown($maxWidth, $maxHeight);
        }
        $encoded = in_array($extension, self::LOSSY, true)
            ? (string) $image->encodeByExtension($extension, quality: max(1, min(100, $quality)))
            : (string) $image->encodeByExtension($extension);

        $result['before'] = strlen($original);
        if (! $resize && strlen($encoded) >= strlen($original)) {
            return ['reason' => 'already as small as it gets'] + $result;
        }
        $result = ['status' => 'optimized', 'reason' => null, 'before' => strlen($original), 'after' => strlen($encoded), 'width' => $image->width(), 'height' => $image->height()];
        if ($dryRun) {
            return $result;
        }

        if ($backup) {
            [$backupDisk, $backupPath] = self::backupLocation($media);
            // Always the first original: later runs never overwrite it.
            if (! $backupDisk->exists($backupPath)) {
                $backupDisk->put($backupPath, $original);
            }
        }
        $disk->put($media->path, $encoded);
        $media->forceFill(['size' => $result['after'], 'width' => $result['width'], 'height' => $result['height'], 'version' => $media->version + 1])->save();
        GenerateImageSizes::for($media);

        return $result;
    }

    /**
     * Where a file's original is kept: [disk, path].
     *
     * @return array{0: Filesystem, 1: string}
     */
    public static function backupLocation(Media $media): array
    {
        $disk = config('sunrice-admin.media.optimize.backup_disk');
        $directory = config('sunrice-admin.media.optimize.backup_directory');
        $directory = trim(is_string($directory) ? $directory : 'sunrice-originals', '/');

        return [Storage::disk(is_string($disk) && $disk !== '' ? $disk : $media->disk), $directory.'/'.ltrim($media->path, '/')];
    }

    /** Deletes a file's backup (it was replaced or deleted). */
    public static function forgetBackup(Media $media): void
    {
        [$disk, $path] = self::backupLocation($media);
        $disk->delete($path);
    }
}
