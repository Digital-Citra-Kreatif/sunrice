<?php

declare(strict_types=1);

namespace Sunrice\StarterKit\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Sunrice\StarterKit\Starters;

/**
 * sunrice:starters: the starters this app can install.
 *
 * @internal
 */
final class StartersCommand extends Command
{
    protected $signature = 'sunrice:starters';

    protected $description = 'List the starter sites this app can install';

    public function handle(Starters $starters): int
    {
        try {
            $all = $starters->all();
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
        if ($all === []) {
            $this->components->info('No starters yet.');

            return self::SUCCESS;
        }
        $rows = [];
        foreach ($all as $starter) {
            $rows[] = [$starter->name, $starter->title, $starter->description, implode(', ', $starter->locales)];
        }
        $this->table(['Name', 'Title', 'Description', 'Languages'], $rows);
        $this->line('Install one with: php artisan sunrice:starter <name> --dry-run, then without --dry-run.');

        return self::SUCCESS;
    }
}
