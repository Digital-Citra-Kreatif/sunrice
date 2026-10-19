<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\Media;

/**
 * Moves media to the trash, or back out of it. Files stay on the disk
 * until DeleteMedia.
 *
 * @api
 */
final class TrashMedia
{
    public function __construct(private ActivityLog $activity) {}

    public function handle(Media $media): void
    {
        $media->delete();
        $this->activity->record('trashed', 'media', $media, $media->title ?? $media->filename);
    }

    public function restore(Media $media): void
    {
        $media->restore();
        $this->activity->record('restored', 'media', $media, $media->title ?? $media->filename);
    }
}
