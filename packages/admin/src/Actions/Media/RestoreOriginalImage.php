<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Media\Jobs\GenerateImageSizes;
use Sunrice\Admin\Media\Media;

/**
 * Puts back the original OptimizeImage kept, and deletes the backup.
 *
 * @api
 */
final class RestoreOriginalImage
{
    /** @return bool whether there was an original to put back */
    public function handle(Media $media): bool
    {
        [$backupDisk, $backupPath] = OptimizeImage::backupLocation($media);
        $original = $backupDisk->exists($backupPath) ? $backupDisk->get($backupPath) : null;
        if ($original === null) {
            return false;
        }
        Storage::disk($media->disk)->put($media->path, $original);
        $info = @getimagesizefromstring($original);
        $media->forceFill([
            'size' => strlen($original),
            'width' => $info === false ? $media->width : $info[0],
            'height' => $info === false ? $media->height : $info[1],
            'version' => $media->version + 1,
        ])->save();
        GenerateImageSizes::for($media);
        $backupDisk->delete($backupPath);

        return true;
    }
}
