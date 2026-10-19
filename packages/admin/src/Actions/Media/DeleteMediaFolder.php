<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;

/**
 * Deletes a folder. Nothing in it is lost: its files and folders move up
 * to where the folder was.
 *
 * @api
 */
final class DeleteMediaFolder
{
    public function handle(MediaFolder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            Media::withTrashed()->where('folder_id', $folder->id)->update(['folder_id' => $folder->parent_id]);
            MediaFolder::query()->where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
            $folder->delete();
        });
    }
}
