<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\DeveloperTools;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\StringInput;
use Throwable;

/**
 * Run a console command an AI agent asked for, like "cache:clear" or
 * "queue:retry all", if its name is allowed (config
 * `sunrice-admin.ai.developer_tools.commands`). It runs without asking
 * questions; what it printed and its exit code come back, and the run
 * goes in the activity log.
 *
 * @internal
 */
final class RunCommand
{
    /** The most output returned (characters, from the end). */
    public const MAX_OUTPUT = 20000;

    public function __construct(private ActivityLog $activity) {}

    /**
     * @return array{command: string, exit_code: int, output: string}
     *
     * @throws ValidationException
     */
    public function handle(string $line): array
    {
        $line = trim($line);
        if ($line === '' || strlen($line) > 1000 || preg_match('/[\r\n\0]/', $line) === 1) {
            throw ValidationException::withMessages(['command' => 'Give one command line, like "cache:clear".']);
        }
        try {
            $name = (new StringInput($line))->getFirstArgument();
        } catch (ExceptionInterface $e) {
            throw ValidationException::withMessages(['command' => $e->getMessage()]);
        }
        if (! is_string($name) || ! str_starts_with($line, $name) || ! DeveloperTools::allowsCommand($name)) {
            throw ValidationException::withMessages(['command' => '"'.(is_string($name) ? $name : $line).'" isn\'t a command agents may run. Allowed: '.implode(', ', DeveloperTools::commands()).'.']);
        }
        if (! preg_match('/(^|\s)(--no-interaction|-n)(\s|$)/', $line)) {
            $line .= ' --no-interaction';
        }

        try {
            $code = Artisan::call($line);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $code = 1;
            $output = $e::class.': '.$e->getMessage();
        }
        $this->activity->record('ran', 'command', null, Str::limit($line, 250, ''), ['note' => "exit code {$code}"]);

        return [
            'command' => $line,
            'exit_code' => $code,
            'output' => strlen($output) > self::MAX_OUTPUT ? '…'.substr($output, -self::MAX_OUTPUT) : $output,
        ];
    }
}
