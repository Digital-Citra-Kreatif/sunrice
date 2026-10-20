<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\Tool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

#[IsDestructive]
#[Description('Run one of the artisan commands this site allows (sunrice-admin.ai.commands; "command": "list" shows them with their options), e.g. {"command": "sunrice:activity-prune", "options": {"--days": 30}}. Options and arguments are checked against the command; it runs without questions (commands that ask need their --force). Returns the exit code and the output. Ask the user before anything that deletes data.')]
final class RunCommand extends Tool
{
    protected string $name = 'run_command';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.ai.run-commands')) {
            return $this->denied('run commands');
        }
        /** @var array{command: string, options?: array<mixed>|null} $args */
        $args = $request->validate([
            'command' => ['required', 'string', 'max:100'],
            'options' => ['nullable', 'array', 'max:20'],
        ]);
        $allowed = self::allowed();
        if ($args['command'] === 'list') {
            return $this->json(['commands' => array_map(fn (string $name): array => [
                'command' => $name,
                'description' => self::command($name)?->getDescription(),
                'options' => $allowed[$name] ?? self::ownOptions($name),
            ], array_keys($allowed))]);
        }
        $command = array_key_exists($args['command'], $allowed) ? self::command($args['command']) : null;
        if ($command === null) {
            return Response::error("\"{$args['command']}\" isn't one of the commands this site lets agents run: ".implode(', ', array_keys($allowed)).'.');
        }

        $definition = $command->getNativeDefinition();
        $only = $allowed[$args['command']];
        $parameters = [];
        foreach ($args['options'] ?? [] as $key => $value) {
            $key = (string) $key;
            $option = str_starts_with($key, '--') ? substr($key, 2) : null;
            $known = $option !== null ? preg_match('/^[a-z][a-z0-9-]*$/', $option) === 1 && $definition->hasOption($option) : preg_match('/^[a-z][a-z0-9_]*$/', $key) === 1 && $definition->hasArgument($key);
            if (! $known || ($only !== null && ! in_array($key, $only, true))) {
                return Response::error("\"{$args['command']}\" doesn't take \"{$key}\" here. Options: ".implode(', ', $only ?? self::ownOptions($args['command'])).'.');
            }
            if (! is_scalar($value) && ! (is_array($value) && array_is_list($value) && array_filter($value, fn (mixed $v): bool => ! is_scalar($v)) === [])) {
                return Response::error("Give \"{$key}\" a text, number or true/false (or a list of them).");
            }
            $parameters[$key] = $value;
        }
        $parameters['--no-interaction'] = true;

        $output = new BufferedOutput;
        try {
            $code = app(Kernel::class)->call($args['command'], $parameters, $output);
        } catch (Throwable $e) {
            return Response::error('The command failed: '.ReadErrorLog::clean($e->getMessage()));
        } finally {
            // Which command, with which options; never their values.
            app(ActivityLog::class)->record('ran command', 'command', null, $args['command'], ['options' => array_keys(array_diff_key($parameters, ['--no-interaction' => true]))]);
        }

        return $this->json([
            'command' => $args['command'],
            'exit_code' => $code,
            'output' => mb_substr($output->fetch(), -20000),
        ]);
    }

    /**
     * The allowed commands that exist: name => the options it may take (null: any of its own).
     *
     * @return array<string, list<string>|null>
     */
    private static function allowed(): array
    {
        $config = config('sunrice-admin.ai.commands', []);
        $allowed = [];
        foreach (is_array($config) ? $config : [] as $key => $value) {
            [$name, $options] = is_string($key) ? [$key, is_array($value) ? array_values(array_filter($value, is_string(...))) : []] : [$value, null];
            if (is_string($name) && self::command($name) !== null) {
                $allowed[$name] = $options;
            }
        }

        return $allowed;
    }

    private static function command(string $name): ?Command
    {
        $commands = app(Kernel::class)->all();
        $command = $commands[$name] ?? null;

        return $command instanceof Command ? $command : null;
    }

    /** @return list<string> a command's own options ("--days") and arguments */
    private static function ownOptions(string $name): array
    {
        $definition = self::command($name)?->getNativeDefinition();

        return $definition === null ? [] : [...array_map(fn (string $o): string => "--{$o}", array_keys($definition->getOptions())), ...array_keys($definition->getArguments())];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()->description('The command\'s name, or "list".')->required(),
            'options' => $schema->object()->description('{"--option": value, "argument": value}'),
        ];
    }
}
