<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaTrash;
use Sunrice\Admin\Media\MediaUsages;

/**
 * Deletes media permanently: the record and its files (the original and
 * its sizes, and an optimised image's backup), wherever they are: on its
 * disk, or on the private trash disk while it's in the trash. `$media->delete()` only
 * moves it to the trash. A file in use needs `$force`.
 *
 * @api
 */
final class DeleteMedia
{
    public function __construct(private ActivityLog $activity) {}

    /**
     * @param  bool  $force  also when it's used somewhere (MediaUsages)
     *
     * @throws ValidationException when it's in use and not forced
     */
    public function handle(Media $media, bool $force = false): void
    {
        if (! $force && $media->usages()->exists()) {
            $used = app(MediaUsages::class)->of($media);
            $places = implode(', ', array_slice(array_column($used, 'label'), 0, 5));
            $places = count($used) > 5 ? __(':places and :count more', ['places' => $places, 'count' => count($used) - 5]) : $places;
            throw ValidationException::withMessages(['media' => __(':file is used by :places. Delete it anyway?', ['file' => $media->filename, 'places' => $places])]);
        }
        OptimizeImage::forgetBackup($media);
        $files = $media->files();
        // Explicitly, not by the foreign key's cascade: not every connection enforces it.
        $media->usages()->delete();
        $disk = $media->disk;
        $media->forceDelete();
        Storage::disk($disk)->delete($files);
        app(MediaTrash::class)->forget($media);
        $this->activity->record('deleted', 'media', $media, $media->title ?? $media->filename);
    }
}
