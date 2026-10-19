<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Http\Controllers\MediaController;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;

#[IsReadOnly]
#[Description('List the media library: files (id, title, filename, alt text, type, size, dimensions, URL) in a folder or matching a search, newest first, and the folders. Use a file\'s id in media fields (get_record shows them).')]
final class ListMedia extends Tool
{
    protected string $name = 'list_media';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.media.view')) {
            return $this->denied('view the media library');
        }
        $search = $request->get('search');
        $search = is_string($search) ? trim($search) : '';
        $folder = $request->get('folder');
        $type = $request->get('type');
        $page = $request->get('page');

        $media = Media::query()
            ->when($search === '', fn (Builder $q) => $q->where('folder_id', is_int($folder) ? $folder : null))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereLike('title', "%{$search}%")->orWhereLike('filename', "%{$search}%")))
            ->tap(fn (Builder $q) => Media::whereTypes($q, is_string($type) && in_array($type, Media::TYPES, true) ? [$type] : []))
            ->orderByDesc('id')
            ->paginate(30, ['*'], 'page', is_int($page) ? max(1, $page) : 1);

        return $this->json([
            'folders' => MediaFolder::query()->where('parent_id', $search === '' && is_int($folder) ? $folder : null)->orderBy('name')->get(['id', 'name'])->toArray(),
            'files' => array_map(fn (Media $m) => array_intersect_key(MediaController::describe($m), array_flip(['id', 'title', 'filename', 'alt', 'caption', 'mimeType', 'size', 'width', 'height', 'url'])), $media->items()),
            'total' => $media->total(),
            'page' => $media->currentPage(),
            'last_page' => $media->lastPage(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder' => $schema->integer()->description('A folder id; the top level when left out.'),
            'search' => $schema->string()->description('Searches every folder.'),
            'type' => $schema->string()->enum(Media::TYPES),
            'page' => $schema->integer(),
        ];
    }
}
