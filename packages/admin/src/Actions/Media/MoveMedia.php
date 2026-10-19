<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;

/**
 * Moves media into a folder, or to the top level with null. Files keep
 * their paths and URLs: folders only organise the library.
 *
 * @api
 */
final class MoveMedia
{
    public function __construct(private ActivityLog $activity) {}

    /** @param iterable<Media> $media */
    public function handle(iterable $media, ?MediaFolder $folder): int
    {
        $ids = [];
        foreach ($media as $item) {
            $ids[] = $item->id;
        }
        if ($ids === []) {
            return 0;
        }
        $moved = Media::withTrashed()->whereKey($ids)->update(['folder_id' => $folder?->id, 'updated_at' => now()]);
        $this->activity->record('moved', 'media', null, $moved.' '.($moved === 1 ? 'file' : 'files').' to '.($folder === null ? 'the top level' : "\"{$folder->name}\""), ['ids' => $ids]);

        return $moved;
    }
}
