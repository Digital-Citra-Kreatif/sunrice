<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\Media;

/**
 * Deletes media for good: the record and its files (the original and
 * its sizes). `$media->delete()` only moves it to the trash.
 *
 * @api
 */
final class DeleteMedia
{
    public function __construct(private ActivityLog $activity) {}

    public function handle(Media $media): void
    {
        $files = $media->files();
        $disk = $media->disk;
        $media->forceDelete();
        Storage::disk($disk)->delete($files);
        $this->activity->record('deleted', 'media', $media, $media->title ?? $media->filename);
    }
}
