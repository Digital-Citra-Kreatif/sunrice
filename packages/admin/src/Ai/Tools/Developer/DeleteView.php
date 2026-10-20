<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools\Developer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Sunrice\Admin\Actions\Views\DeleteView as DeleteViewAction;
use Sunrice\Admin\Ai\DeveloperTools;

#[IsDestructive]
#[Description('Delete a Blade view ("name" like "cms.article"). Its content is kept as a version (read_view), to bring it back with save_view. Ask the user first: pages using it may fall back to another view or fail.')]
final class DeleteView extends DeveloperTool
{
    protected string $name = 'delete_view';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.views.update')) {
            return $this->denied('change views');
        }
        $name = DeveloperTools::viewName($request->get('name'));
        if ($name === null) {
            return Response::error('Give a view\'s name, like "cms.article".');
        }
        $version = app(DeleteViewAction::class)->handle($name);

        return $version === null ? Response::error("No view {$name}.") : $this->json(['deleted' => $name, 'version' => $version]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Like "cms.article".')->required(),
        ];
    }
}
