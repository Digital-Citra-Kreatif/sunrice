<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Sunrice\Admin\Actions\Media\DeleteMediaFolder;
use Sunrice\Admin\Actions\Media\SaveMediaFolder;
use Sunrice\Admin\Media\MediaFolder;

/**
 * Folders on the Media page: create, rename and delete (the contents
 * move up). Needs "sunrice.media.update".
 *
 * @internal
 */
final class MediaFolderController
{
    public function store(Request $request, SaveMediaFolder $save): RedirectResponse
    {
        $this->authorize($request);
        $request->validate(['name' => ['required', 'string', 'max:100'], 'parent_id' => ['nullable', 'integer', Rule::exists(MediaFolder::class, 'id')]]);
        $parent = MediaFolder::query()->find($request->integer('parent_id') ?: null);
        $folder = $save->handle($request->string('name')->toString(), $parent);

        return back()->with('success', "Folder \"{$folder->name}\" created.");
    }

    public function update(Request $request, int $folder, SaveMediaFolder $save): RedirectResponse
    {
        $this->authorize($request);
        $request->validate(['name' => ['required', 'string', 'max:100']]);
        $save->handle($request->string('name')->toString(), folder: MediaFolder::query()->findOrFail($folder));

        return back()->with('success', 'Folder renamed.');
    }

    public function destroy(Request $request, int $folder, DeleteMediaFolder $delete): RedirectResponse
    {
        $this->authorize($request);
        $model = MediaFolder::query()->findOrFail($folder);
        $delete->handle($model);

        return redirect()->route('sunrice.admin.media.index', array_filter(['folder' => $model->parent_id]))
            ->with('success', "Folder \"{$model->name}\" deleted. What was in it moved up.");
    }

    private function authorize(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable && $user->can('sunrice.media.update'), 403);
    }
}
