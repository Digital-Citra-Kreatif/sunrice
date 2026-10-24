<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Admin\Actions\Media\DeleteMedia;
use Sunrice\Admin\Actions\Media\DeleteMediaFolder;
use Sunrice\Admin\Actions\Media\MoveMedia;
use Sunrice\Admin\Actions\Media\SaveMediaFolder;
use Sunrice\Admin\Actions\Media\TrashMedia;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Http\Controllers\MediaController;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Admin\Media\MediaUsages;

#[Description('Organise the media library. "folders": every folder (id, name, depth). "create_folder" (name, parent_id), "rename_folder" (folder_id, name), "delete_folder" (folder_id; what is in it moves up). "move": files ("ids") into a folder ("folder_id", none for the top level). "trash" / "restore" a file ("id"): trashing takes it offline, so its links stop working (on the site too) and its url and thumbnail are null until it\'s restored. "usages": where a file is used. "delete": delete a trashed file permanently; refused while content uses it unless "force": true. Ask the user before deleting.')]
final class ManageMedia extends Tool
{
    protected string $name = 'manage_media';

    public const ACTIONS = ['folders', 'create_folder', 'rename_folder', 'delete_folder', 'move', 'trash', 'restore', 'usages', 'delete'];

    public function handle(Request $request): Response
    {
        /** @var array{action: string, id?: int|null, ids?: list<int>|null, folder_id?: int|null, parent_id?: int|null, name?: string|null, force?: bool|null} $args */
        $args = $request->validate([
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'id' => ['nullable', 'required_if:action,trash,restore,usages,delete', 'integer'],
            'ids' => ['nullable', 'required_if:action,move', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'folder_id' => ['nullable', 'required_if:action,rename_folder,delete_folder', 'integer', Rule::exists(MediaFolder::class, 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists(MediaFolder::class, 'id')],
            'name' => ['nullable', 'required_if:action,create_folder,rename_folder', 'string', 'max:100'],
            'force' => ['nullable', 'boolean'],
        ]);
        $action = $args['action'];
        $ability = match ($action) {
            'folders', 'usages' => 'view',
            'trash', 'restore', 'delete' => 'delete',
            default => 'update',
        };
        if (! $this->can("sunrice.media.{$ability}")) {
            return $this->denied(['view' => 'view the media library', 'update' => 'organise media', 'delete' => 'delete media'][$ability]);
        }
        $folder = isset($args['folder_id']) ? MediaFolder::query()->find($args['folder_id']) : null;

        switch ($action) {
            case 'folders':
                return $this->json(['folders' => self::tree(MediaFolder::query()->orderBy('name')->get(['id', 'parent_id', 'name'])->values()->all(), null, 0)]);
            case 'create_folder':
            case 'rename_folder':
                $saved = app(SaveMediaFolder::class)->handle($args['name'] ?? '',
                    $action === 'create_folder' && isset($args['parent_id']) ? MediaFolder::query()->find($args['parent_id']) : null,
                    $action === 'rename_folder' ? $folder : null);

                return $this->json(['saved' => true, 'folder' => ['id' => $saved->id, 'parent_id' => $saved->parent_id, 'name' => $saved->name]]);
            case 'delete_folder':
                if ($folder === null) {
                    return Response::error('No such folder.');
                }
                app(DeleteMediaFolder::class)->handle($folder);

                return $this->json(['deleted' => true, 'folder_id' => $folder->id]);
            case 'move':
                $files = Media::withTrashed()->whereKey($args['ids'] ?? [])->get();
                if ($files->isEmpty()) {
                    return Response::error('No such files. list_media lists them.');
                }

                return $this->json(['moved' => app(MoveMedia::class)->handle($files, $folder), 'folder' => $folder?->name]);
        }

        $media = Media::withTrashed()->find($args['id'] ?? 0);
        if ($media === null) {
            return Response::error('No such file. list_media lists them.');
        }
        switch ($action) {
            case 'usages':
                return $this->json(['id' => $media->id, 'usages' => app(MediaUsages::class)->of($media)]);
            case 'trash':
                if ($media->trashed()) {
                    return Response::error('The file is in the trash already.');
                }
                app(TrashMedia::class)->handle($media);

                return $this->json(['trashed' => true, 'id' => $media->id]);
            case 'restore':
                if (! $media->trashed()) {
                    return Response::error('The file isn\'t in the trash.');
                }
                app(TrashMedia::class)->restore($media);

                return $this->json(['restored' => true, ...self::describe($media)]);
            default:
                if (! $media->trashed()) {
                    return Response::error('Trash the file first ("trash"); only files in the trash can be permanently deleted.');
                }
                // Refused while in use (ValidationException, naming where), unless forced.
                app(DeleteMedia::class)->handle($media, ($args['force'] ?? false) === true);

                return $this->json(['deleted' => true, 'id' => $media->id]);
        }
    }

    /**
     * A file as agents see it.
     *
     * @return array<string, mixed>
     */
    public static function describe(Media $media): array
    {
        return array_intersect_key(MediaController::describe($media), array_flip(['id', 'title', 'filename', 'alt', 'caption', 'mimeType', 'size', 'width', 'height', 'folderId', 'url', 'trashed']));
    }

    /**
     * @param  array<int, MediaFolder>  $folders
     * @return list<array{id: int, name: string, depth: int}>
     */
    private static function tree(array $folders, ?int $parent, int $depth): array
    {
        $tree = [];
        foreach ($depth > 20 ? [] : $folders as $folder) {
            if ($folder->parent_id === $parent) {
                $tree = [...$tree, ['id' => $folder->id, 'name' => $folder->name, 'depth' => $depth], ...self::tree($folders, $folder->id, $depth + 1)];
            }
        }

        return $tree;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(self::ACTIONS)->required(),
            'id' => $schema->integer()->description('A file (trash, restore, usages, delete).'),
            'ids' => $schema->array()->items($schema->integer())->description('Files to move.'),
            'folder_id' => $schema->integer()->description('move: where to (none: the top level); rename_folder, delete_folder: which.'),
            'parent_id' => $schema->integer()->description('create_folder: inside this folder.'),
            'name' => $schema->string()->description('create_folder, rename_folder.'),
            'force' => $schema->boolean()->description('delete: also when content uses it (ask first).'),
        ];
    }
}
