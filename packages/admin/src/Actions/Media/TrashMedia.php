<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFilesNotMoved;

/**
 * Moves media to the trash, or back out of it. In the trash its files are
 * off the public disk (on the private `media.trash_disk`), so its links
 * stop working; restoring puts them back at the same paths. DeleteMedia
 * removes them for good.
 *
 * @api
 */
final class TrashMedia
{
    public function __construct(private ActivityLog $activity) {}

    /** @throws ValidationException when its files can't be moved (nothing changed) */
    public function handle(Media $media): void
    {
        try {
            $media->delete();
        } catch (MediaFilesNotMoved) {
            throw ValidationException::withMessages(['media' => __(':file couldn\'t be moved to the trash: its files couldn\'t be moved off the public disk. Nothing was changed.', ['file' => $media->filename])]);
        }
        $this->activity->record('trashed', 'media', $media, $media->title ?? $media->filename);
    }

    /** @throws ValidationException when its files can't be moved back (nothing changed) */
    public function restore(Media $media): void
    {
        try {
            $media->restore();
        } catch (MediaFilesNotMoved) {
            throw ValidationException::withMessages(['media' => __(':file couldn\'t be restored: its files couldn\'t be moved back from the trash. Nothing was changed.', ['file' => $media->filename])]);
        }
        $this->activity->record('restored', 'media', $media, $media->title ?? $media->filename);
    }
}
