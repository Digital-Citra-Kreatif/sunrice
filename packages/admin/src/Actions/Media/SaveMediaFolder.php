<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\MediaFolder;

/**
 * Creates a folder, or renames one. Folders in the same place need
 * different names.
 *
 * @api
 */
final class SaveMediaFolder
{
    public function __construct(private ActivityLog $activity) {}

    /** @throws ValidationException */
    public function handle(string $name, ?MediaFolder $parent = null, ?MediaFolder $folder = null): MediaFolder
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => __('Give the folder a name of up to 100 characters.')]);
        }
        $parentId = $folder !== null ? $folder->parent_id : $parent?->id;
        $taken = MediaFolder::query()->where('parent_id', $parentId)->where('name', $name)
            ->when($folder !== null, fn (Builder $query) => $query->whereKeyNot($folder?->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => __('There is already a folder called ":name" here.', ['name' => $name])]);
        }

        $folder ??= new MediaFolder(['parent_id' => $parentId]);
        $action = $folder->exists ? 'renamed' : 'created';
        $folder->name = $name;
        $folder->save();
        $this->activity->record($action, 'media folder', $folder, $name);

        return $folder;
    }
}
