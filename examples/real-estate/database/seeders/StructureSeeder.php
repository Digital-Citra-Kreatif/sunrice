<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;
use Sunrice\Cms\Structure\StructureImporter;

/**
 * Creates (or updates, by handle) the site's content model from database/structure.json: fieldsets,
 * blueprints, collections with their listing pages, taxonomies, global sets, forms, menus and redirects.
 * The file is the versioned content model: export it again after changing the structure in the admin
 * (php artisan sunrice:export-structure database/structure.json) and commit it.
 *
 * Adds only what's missing: running it on a site with content changes no content.
 */
final class StructureSeeder extends Seeder
{
    public const FILE = 'structure.json';

    public function run(StructureImporter $importer): void
    {
        $path = database_path(self::FILE);
        $structure = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($structure)) {
            throw new RuntimeException("{$path} isn't a structure export.");
        }

        $report = $importer->import($structure, dryRun: false, force: false);

        foreach ($report->items() as $item) {
            if ($item['status'] === 'refused') {
                throw new RuntimeException("The {$item['type']} \"{$item['handle']}\" was refused: ".implode(' ', $item['notes']));
            }
        }

        $this->command?->info('Structure imported: '.json_encode($report->counts()));
    }
}
