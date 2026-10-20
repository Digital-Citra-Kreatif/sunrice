<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools\Developer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Admin\Actions\Console\RunCommand as RunCommandAction;
use Sunrice\Admin\Ai\DeveloperTools;

#[Description('Run a console (artisan) command, like "view:clear" or "queue:retry all", without "php artisan". Only the commands the app allows (see get_panel_info → developer_tools.commands); it can\'t answer questions, so pass options like --force. Returns what it printed and its exit code (0 is success).')]
final class RunCommand extends DeveloperTool
{
    protected string $name = 'run_command';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.commands.run')) {
            return $this->denied('run commands');
        }
        $command = $request->get('command');
        if (! is_string($command)) {
            return Response::error('Give a "command", like "cache:clear". Allowed: '.implode(', ', DeveloperTools::commands()).'.');
        }
        try {
            return $this->json(app(RunCommandAction::class)->handle($command));
        } catch (ValidationException $e) {
            return Response::error('Not run: '.implode(' ', array_filter($e->validator->errors()->all(), is_string(...))));
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()->description('The command line without "php artisan": "cache:clear", "queue:retry all".')->required(),
        ];
    }
}
