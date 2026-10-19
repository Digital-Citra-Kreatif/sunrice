<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Media\DeleteMedia;
use Sunrice\Admin\Actions\Media\MoveMedia;
use Sunrice\Admin\Actions\Media\ReplaceMedia;
use Sunrice\Admin\Actions\Media\UpdateMedia;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaConfig;
use Sunrice\Admin\Media\MediaFolder;

/**
 * The Media page: browse, upload, edit, move, trash and restore. Each
 * action checks its "sunrice.media.*" permission.
 *
 * @internal
 */
final class MediaController
{
    public const PER_PAGE = 48;

    public const TYPES = ['image', 'video', 'audio', 'document'];

    public function index(Request $request): Response
    {
        $user = $this->authorize($request, 'view');
        /** @var array<string, mixed> $query */
        $query = $request->validate([
            'folder' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'trash' => ['nullable', 'boolean'],
        ]);
        $search = is_string($query['search'] ?? null) ? trim($query['search']) : '';
        $type = is_string($query['type'] ?? null) ? $query['type'] : null;
        $trash = (bool) ($query['trash'] ?? false);

        // The folder tree is small: one query, the rest in memory.
        $folders = MediaFolder::query()->orderBy('name')->get(['id', 'parent_id', 'name']);
        $current = is_numeric($query['folder'] ?? null) ? $folders->firstWhere('id', (int) $query['folder']) : null;
        // Searching and the trash look through every folder.
        $everywhere = $search !== '' || $trash;

        $media = ($trash ? Media::onlyTrashed() : Media::query())
            ->when(! $everywhere, fn (Builder $q) => $q->where('folder_id', $current?->id))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('title', '%'.self::escapeLike($search).'%')
                ->orWhereLike('filename', '%'.self::escapeLike($search).'%')))
            ->when($type !== null, fn (Builder $q) => self::whereType($q, (string) $type))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Media/Index', [
            'query' => ['folder' => $current?->id, 'search' => $search, 'type' => $type, 'trash' => $trash],
            'folder' => $current === null ? null : ['id' => $current->id, 'name' => $current->name],
            'path' => self::path($folders, $current),
            'folders' => $everywhere ? [] : $folders->where('parent_id', $current?->id)->map(fn (MediaFolder $f) => ['id' => $f->id, 'name' => $f->name])->values(),
            'tree' => self::tree($folders),
            'media' => [
                'data' => array_map(fn (Media $item) => self::describe($item), $media->items()),
                'total' => $media->total(),
                'currentPage' => $media->currentPage(),
                'lastPage' => $media->lastPage(),
                'prevUrl' => $media->previousPageUrl(),
                'nextUrl' => $media->nextPageUrl(),
            ],
            'can' => [
                'upload' => $user->can('sunrice.media.upload'),
                'update' => $user->can('sunrice.media.update'),
                'delete' => $user->can('sunrice.media.delete'),
            ],
            'upload' => [
                'maxKb' => MediaConfig::maxKilobytes(),
                'accept' => implode(',', array_map(fn (string $ext) => ".{$ext}", MediaConfig::allowedExtensions())),
            ],
        ]);
    }

    public function store(Request $request, UploadMedia $upload): RedirectResponse
    {
        $user = $this->authorize($request, 'upload');
        $request->validate(['file' => ['required', 'file'], 'folder_id' => ['nullable', 'integer', Rule::exists(MediaFolder::class, 'id')]]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $folder = $request->integer('folder_id') ?: null;

        $upload->handle($file, ['folder_id' => $folder, 'uploaded_by' => self::userId($request)]);

        // No message: the page shows each upload's progress and result.
        return back();
    }

    public function update(Request $request, int $media, UpdateMedia $update): RedirectResponse
    {
        $this->authorize($request, 'update');
        /** @var array{title?: string|null, alt?: string|null, caption?: string|null} $details */
        $details = $request->validate([
            'title' => ['nullable', 'string', 'max:250'],
            'alt' => ['nullable', 'string', 'max:250'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);
        $update->handle(Media::query()->findOrFail($media), $details);

        return back()->with('success', 'Saved.');
    }

    public function replace(Request $request, int $media, ReplaceMedia $replace): RedirectResponse
    {
        $this->authorize($request, 'update');
        $request->validate(['file' => ['required', 'file']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $replace->handle(Media::query()->findOrFail($media), $file);

        return back()->with('success', 'File replaced.');
    }

    public function destroy(Request $request, int $media): RedirectResponse
    {
        $this->authorize($request, 'delete');
        Media::query()->findOrFail($media)->delete();

        return back()->with('success', 'Moved to the trash.');
    }

    public function restore(Request $request, int $media): RedirectResponse
    {
        $this->authorize($request, 'delete');
        Media::onlyTrashed()->findOrFail($media)->restore();

        return back()->with('success', 'Restored.');
    }

    public function forceDelete(Request $request, int $media, DeleteMedia $delete): RedirectResponse
    {
        $this->authorize($request, 'delete');
        $delete->handle(Media::onlyTrashed()->findOrFail($media));

        return back()->with('success', 'Deleted for good.');
    }

    /** Move, trash, restore or delete several at once. */
    public function bulk(Request $request, MoveMedia $move, DeleteMedia $delete): RedirectResponse
    {
        /** @var array{ids: list<int>} $input */
        $input = $request->validate([
            'action' => ['required', Rule::in(['move', 'trash', 'restore', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'folder_id' => ['nullable', 'integer', Rule::exists(MediaFolder::class, 'id')],
        ]);
        $action = $request->string('action')->toString();
        $this->authorize($request, $action === 'move' ? 'update' : 'delete');
        $ids = $input['ids'];

        $count = match ($action) {
            'move' => $move->handle(Media::withTrashed()->whereKey($ids)->get(), MediaFolder::query()->find($request->integer('folder_id') ?: null)),
            'trash' => Media::query()->whereKey($ids)->get()->each(fn (Media $m) => $m->delete())->count(),
            'restore' => Media::onlyTrashed()->whereKey($ids)->get()->each(fn (Media $m) => $m->restore())->count(),
            default => DB::transaction(fn () => Media::onlyTrashed()->whereKey($ids)->get()->each(fn (Media $m) => $delete->handle($m))->count()),
        };
        $done = ['move' => 'moved', 'trash' => 'moved to the trash', 'restore' => 'restored', 'delete' => 'deleted for good'][$action];

        return back()->with('success', "{$count} ".($count === 1 ? 'file' : 'files')." {$done}.");
    }

    /** @return array<string, mixed> */
    public static function describe(Media $media): array
    {
        return [
            'id' => $media->id,
            'title' => $media->title,
            'filename' => $media->filename,
            'alt' => $media->alt,
            'caption' => $media->caption,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'folderId' => $media->folder_id,
            'url' => $media->url(),
            'thumbnail' => $media->isImage() ? $media->url('thumbnail') : null,
            'createdAt' => $media->created_at?->toIso8601String(),
            'trashed' => $media->deleted_at !== null,
        ];
    }

    /** @param Builder<Media> $query */
    private static function whereType(Builder $query, string $type): void
    {
        if ($type === 'document') {
            $query->whereNot(fn (Builder $q) => $q->whereLike('mime_type', 'image/%')->orWhereLike('mime_type', 'video/%')->orWhereLike('mime_type', 'audio/%'));

            return;
        }
        $query->whereLike('mime_type', "{$type}/%");
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * The folders from the top down to `$current`.
     *
     * @param  Collection<int, MediaFolder>  $folders
     * @return list<array{id: int, name: string}>
     */
    private static function path(Collection $folders, ?MediaFolder $current): array
    {
        $path = [];
        $seen = [];
        while ($current !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            array_unshift($path, ['id' => $current->id, 'name' => $current->name]);
            $current = $current->parent_id === null ? null : $folders->firstWhere('id', $current->parent_id);
        }

        return $path;
    }

    /**
     * Every folder in tree order with its depth, for "move to".
     *
     * @param  Collection<int, MediaFolder>  $folders
     * @return list<array{id: int, name: string, depth: int}>
     */
    private static function tree(Collection $folders, ?int $parent = null, int $depth = 0): array
    {
        $tree = [];
        if ($depth > 20) {
            return $tree;
        }
        foreach ($folders->where('parent_id', $parent) as $folder) {
            $tree[] = ['id' => $folder->id, 'name' => $folder->name, 'depth' => $depth];
            $tree = [...$tree, ...self::tree($folders, $folder->id, $depth + 1)];
        }

        return $tree;
    }

    private static function userId(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : null;
    }

    private function authorize(Request $request, string $ability): Authorizable
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable && $user->can("sunrice.media.{$ability}"), 403);

        return $user;
    }
}
