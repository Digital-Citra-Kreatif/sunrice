<?php

declare(strict_types=1);

namespace Sunrice\StarterKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Sunrice\StarterKit\Actions\MakeStarter;

/**
 * sunrice:make-starter: makes a starter from this site (see MakeStarter).
 *
 * @internal
 */
final class MakeStarterCommand extends Command
{
    protected $signature = 'sunrice:make-starter
        {name : The starter\'s name: lowercase letters, digits and dashes}
        {--to= : The folder to write (default: starters/<name>)}
        {--title= : Its title (default: from the name)}
        {--description= : One sentence about it}
        {--public=* : Folders of public/ to take, like --public=site}
        {--force : Replace the folder\'s contents}';

    protected $description = 'Make a starter site from this site: structure, content, media, templates and assets';

    public function handle(MakeStarter $make): int
    {
        $text = fn (mixed $value): string => is_string($value) ? $value : '';
        $name = $text($this->argument('name'));
        $to = $text($this->option('to'));
        $directory = $to !== '' ? $to : base_path("starters/{$name}");
        $public = $this->option('public');
        try {
            $starter = $make->handle($name, $directory, $text($this->option('title')), $text($this->option('description')), array_values(array_filter(Arr::wrap($public), 'is_string')), (bool) $this->option('force'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
        $this->components->info("Wrote the starter \"{$starter->title}\" to {$starter->path}: ".count($starter->mediaFiles()).' media files.');
        $this->line('Check it on a fresh app with: php artisan sunrice:starter --path='.$starter->path.' --dry-run');

        return self::SUCCESS;
    }
}
