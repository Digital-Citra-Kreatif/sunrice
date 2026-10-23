<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;

/**
 * What a plugin's install step (`$panel->installStep()`) gets to talk to
 * the person running `php artisan sunrice:install`: lines, warnings and
 * questions. Without interaction (`--no-interaction`), confirm() answers
 * no, so a step that would change the app's files says what to change
 * instead.
 *
 * @api
 */
final class InstallOutput
{
    /** @internal made by sunrice:install */
    public function __construct(private Command $command, private bool $interactive) {}

    /** Whether someone answers questions (not `--no-interaction`). */
    public function interactive(): bool
    {
        return $this->interactive;
    }

    /** Asks a yes/no question; false without interaction. */
    public function confirm(string $question, bool $default = true): bool
    {
        return $this->interactive && $this->command->confirm($question, $default);
    }

    public function info(string $message): void
    {
        $this->command->outputComponents()->info($message);
    }

    public function warn(string $message): void
    {
        $this->command->outputComponents()->warn($message);
    }

    /** A done step, like the command's own: "Welcome route removed ..... routes/web.php". */
    public function detail(string $first, string $second = ''): void
    {
        $this->command->outputComponents()->twoColumnDetail($first, $second);
    }

    /** A plain line (code to copy, say). */
    public function line(string $line = ''): void
    {
        $this->command->line($line);
    }
}
