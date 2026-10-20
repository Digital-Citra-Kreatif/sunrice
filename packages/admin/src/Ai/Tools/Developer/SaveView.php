<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools\Developer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Admin\Actions\Views\SaveView as SaveViewAction;
use Sunrice\Admin\Ai\DeveloperTools;

#[Description('Create or replace a Blade view ("name" like "cms.article") with "content", the whole file. It must compile; the earlier content is kept as a version (read_view). The site uses it at once, so read it first and ask the user before big changes. Changes on the server are lost on the next deploy unless they\'re also put in the app\'s code.')]
final class SaveView extends DeveloperTool
{
    protected string $name = 'save_view';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.views.update')) {
            return $this->denied('change views');
        }
        $name = DeveloperTools::viewName($request->get('name'));
        $content = $request->get('content');
        if ($name === null || ! is_string($content)) {
            return Response::error('Give a view\'s "name", like "cms.article", and its whole "content".');
        }
        try {
            $saved = app(SaveViewAction::class)->handle($name, $content);
        } catch (ValidationException $e) {
            return Response::error('Not saved: '.implode(' ', array_filter($e->validator->errors()->all(), is_string(...))));
        }

        return $this->json(['name' => $name, ...$saved]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Like "cms.article".')->required(),
            'content' => $schema->string()->description('The whole view.')->required(),
        ];
    }
}
