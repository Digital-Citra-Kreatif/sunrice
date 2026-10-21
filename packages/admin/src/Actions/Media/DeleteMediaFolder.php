<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;

/**
 * Deletes a folder. Nothing in it is lost: its files and folders move up
 * to where the folder was, a folder whose name is taken there renamed
 * "Photos (2)" (folders in one place have different names).
 *
 * @api
 */
final class DeleteMediaFolder
{
    public function __construct(private ActivityLog $activity) {}

    public function handle(MediaFolder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            Media::withTrashed()->where('folder_id', $folder->id)->update(['folder_id' => $folder->parent_id]);
            $taken = MediaFolder::query()->where('parent_id', $folder->parent_id)->whereKeyNot($folder->id)->pluck('name')
                ->map(fn (mixed $name): string => mb_strtolower(is_string($name) ? $name : ''))->flip()->all();
            // Only a clash is written on its own; the rest move in one statement.
            foreach (MediaFolder::query()->where('parent_id', $folder->id)->orderBy('name')->get(['id', 'name']) as $child) {
                $name = $child->name;
                for ($n = 2; isset($taken[mb_strtolower($name)]); $n++) {
                    $name = mb_substr($child->name, 0, 90)." ({$n})";
                }
                $taken[mb_strtolower($name)] = true;
                if ($name !== $child->name) {
                    MediaFolder::query()->whereKey($child->id)->update(['name' => $name]);
                }
            }
            MediaFolder::query()->where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
            $folder->delete();
        });
        $this->activity->record('deleted', 'media folder', $folder, $folder->name);
    }
}
