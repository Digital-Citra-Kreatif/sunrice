<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools\Developer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Ai\DeveloperTools;
use Sunrice\Admin\Ai\ViewVersions;

#[IsReadOnly]
#[Description('Read a Blade view ("name" like "cms.article"): its code, and the earlier versions kept when it was changed through save_view or delete_view. "version" reads one of those, to undo a change by saving it again.')]
final class ReadView extends DeveloperTool
{
    protected string $name = 'read_view';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.views.view')) {
            return $this->denied('read views');
        }
        $name = DeveloperTools::viewName($request->get('name'));
        if ($name === null) {
            return Response::error('Give a view\'s name, like "cms.article" (list_views lists them).');
        }
        $versions = app(ViewVersions::class);
        $version = $request->get('version');
        if (is_string($version) && $version !== '') {
            $content = $versions->read($name, $version);

            return $content === null
                ? Response::error("No version \"{$version}\" of {$name}.")
                : $this->json(['name' => $name, 'version' => $version, 'content' => $content]);
        }
        $file = DeveloperTools::viewFile($name);
        $exists = is_file($file);
        if (! $exists && $versions->all($name) === []) {
            return Response::error("No view {$name}. list_views lists them; save_view creates one.");
        }

        return $this->json(array_filter([
            'name' => $name,
            'file' => str_replace('.', '/', $name).'.blade.php',
            'exists' => $exists,
            'content' => $exists ? File::get($file) : null,
            'modified' => $exists ? date(DATE_ATOM, (int) filemtime($file)) : null,
            'versions' => $versions->all($name),
        ], fn (mixed $value): bool => $value !== null));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Like "cms.article".')->required(),
            'version' => $schema->string()->description('An earlier version, from "versions".'),
        ];
    }
}
