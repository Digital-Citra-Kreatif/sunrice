<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaConfig;
use Throwable;

/**
 * Makes an image's sizes (config `sunrice-admin.media.image_sizes`),
 * decoding the original once, as {path}-{size}.{ext} next to it, and
 * deletes sizes that are no longer configured. If the image can't be
 * read, or there is no image library, the media keeps only its original.
 *
 * @internal
 */
final class GenerateImageSizes implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly int $mediaId) {}

    /** Now, or on the queue when `sunrice-admin.media.queue` is on. */
    public static function for(Media $media): void
    {
        if (! $media->isResizable()) {
            return;
        }
        if (MediaConfig::queue()) {
            // Once the upload commits: a worker would not find a file whose transaction is still open.
            self::dispatch($media->id)->afterCommit();
        } else {
            self::dispatchSync($media->id);
        }
    }

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null || ! $media->isResizable()) {
            return;
        }
        if ($media->width !== null && $media->height !== null && $media->width * $media->height > MediaConfig::maxPixels()) {
            Log::info('Sunrice: no sizes for a very large image', ['media' => $media->id]);

            return;
        }

        $disk = Storage::disk($media->disk);
        $extension = strtolower(pathinfo($media->path, PATHINFO_EXTENSION)) ?: 'jpg';
        $base = substr($media->path, 0, -(strlen($extension) + 1));

        try {
            $original = $disk->get($media->path);
            if ($original === null) {
                return;
            }
            $source = (new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver))->read($original);
        } catch (Throwable $e) {
            Log::warning('Sunrice: can\'t make image sizes', ['media' => $media->id, 'error' => $e->getMessage()]);

            return;
        }

        $sizes = [];
        foreach (MediaConfig::imageSizes() as $name => $size) {
            try {
                $image = clone $source;
                if ($size['crop'] && $size['width'] !== null && $size['height'] !== null) {
                    $image->cover($size['width'], $size['height']);
                } else {
                    $image->scaleDown($size['width'], $size['height']);
                }
                $path = "{$base}-{$name}.{$extension}";
                $disk->put($path, (string) $image->encodeByExtension($extension));
                $sizes[$name] = $path;
            } catch (Throwable $e) {
                Log::warning('Sunrice: can\'t make an image size', ['media' => $media->id, 'size' => $name, 'error' => $e->getMessage()]);
            }
        }

        // Sizes no longer configured.
        foreach (array_diff_key($media->sizes, $sizes) as $stale) {
            $disk->delete($stale);
        }
        $media->forceFill(['sizes' => $sizes])->saveQuietly();
    }
}
