<?php

declare(strict_types=1);

namespace Sunrice\StarterKit\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Sunrice\Cms\Structure\ImportReport;
use Sunrice\Cms\Structure\StructureImportFailed;
use Sunrice\StarterKit\Actions\InstallStarter;
use Sunrice\StarterKit\Installation;
use Sunrice\StarterKit\Starter;
use Sunrice\StarterKit\Starters;

/**
 * sunrice:starter: installs a starter (see InstallStarter).
 *
 * @internal
 */
final class InstallStarterCommand extends Command
{
    protected $signature = 'sunrice:starter
        {name? : A starter from sunrice:starters}
        {--path= : A starter\'s folder instead (one with a starter.json)}
        {--dry-run : Report what would happen without saving or copying anything}
        {--no-content : The structure only: no entries, terms or global values}
        {--force : Replace the app\'s files that differ, and save fieldsets and blueprints that lose fields}
        {--skip-other-languages : Leave out texts in languages this site doesn\'t have (by default it stops)}';

    protected $description = 'Install a starter site: its structure, sample content, media, templates and assets';

    public function handle(Starters $starters, InstallStarter $install): int
    {
        try {
            $starter = $this->starter($starters);
            $installation = $install->handle($starter, (bool) $this->option('dry-run'), (bool) $this->option('force'), ! $this->option('no-content'), (bool) $this->option('skip-other-languages'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (StructureImportFailed $e) {
            $this->components->error($e->getMessage().' Nothing was installed.');

            return self::FAILURE;
        } catch (ValidationException $e) {
            $this->components->error(implode(' ', $e->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->report($installation);
        if ($installation->report->items(ImportReport::REFUSED) !== []) {
            $this->components->warn('Some fieldsets or blueprints would lose fields and were left as they are: run with --force to replace them.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @throws InvalidArgumentException */
    private function starter(Starters $starters): Starter
    {
        $path = $this->option('path');
        if (is_string($path) && $path !== '') {
            return Starter::load($path);
        }
        $name = $this->argument('name');
        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException('Name a starter (php artisan sunrice:starters lists them), or give its folder with --path.');
        }

        return $starters->find($name) ?? throw new InvalidArgumentException("There is no starter \"{$name}\": php artisan sunrice:starters lists them.");
    }

    private function report(Installation $installation): void
    {
        $verb = $installation->dryRun ? 'would be' : 'were';
        $counts = array_filter($installation->report->counts());
        $this->components->twoColumnDetail('<fg=green>'.$installation->starter->title.'</>', $installation->dryRun ? 'dry run: nothing saved' : 'installed');
        $this->components->twoColumnDetail('Structure and content', implode(', ', array_map(fn (string $status, int $count): string => "{$count} {$status}", array_keys($counts), $counts)) ?: 'nothing new');
        $this->components->twoColumnDetail('Media files', count($installation->uploaded).' '.($installation->dryRun ? 'to upload' : 'uploaded').', '.count($installation->reused).' already in the library');
        $this->components->twoColumnDetail('Files', count($installation->files(Installation::COPIED))." {$verb} copied, ".count($installation->files(Installation::UNCHANGED)).' unchanged, '.count($installation->files(Installation::KEPT)).' kept');
        foreach ($installation->files(Installation::KEPT) as $file) {
            $this->line("  <fg=yellow>kept</> {$file} (yours differs: --force replaces it)");
        }
        if (! $installation->dryRun) {
            $this->components->info('Done. Choose the homepage and check Settings → Site in the admin.');
        }
    }
}
