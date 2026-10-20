<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Admin\Actions\Media\UpdateMedia as Update;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Media\Media;

#[Description('Change a media file\'s title, alt text (what an image shows, for people who can\'t see it and for search engines) or caption. Only what you send changes; an empty value clears it.')]
final class UpdateMedia extends Tool
{
    protected string $name = 'update_media';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.media.update')) {
            return $this->denied('edit media');
        }
        /** @var array{id: int, title?: string|null, alt?: string|null, caption?: string|null} $args */
        $args = $request->validate([
            'id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:250'],
            'alt' => ['nullable', 'string', 'max:250'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);
        $media = Media::query()->find($args['id']);
        if ($media === null) {
            return Response::error('No such file. list_media lists them.');
        }
        $details = array_intersect_key($args, array_flip(['title', 'alt', 'caption']));
        if ($details === []) {
            return Response::error('Send "title", "alt" or "caption".');
        }

        return $this->json(['saved' => true, ...ManageMedia::describe(app(Update::class)->handle($media, $details))]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'title' => $schema->string(),
            'alt' => $schema->string(),
            'caption' => $schema->string(),
        ];
    }
}
