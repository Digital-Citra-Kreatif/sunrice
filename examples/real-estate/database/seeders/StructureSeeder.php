<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Console\Commands\ExportStructure;
use Illuminate\Database\Seeder;
use RuntimeException;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Structure\StructureImporter;

/**
 * Creates (or updates, by handle) the site's content model from database/structure.json: fieldsets,
 * blueprints, collections with their listing pages, taxonomies, global sets, forms, menus and redirects.
 * The file is the versioned content model: export it again after changing the structure in the admin
 * (php artisan lumina:export-structure) and commit it.
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

        $this->seoFromFields($structure);

        $this->command?->info('Structure imported: '.json_encode($report->counts()));
    }

    /**
     * "SEO from fields" (which field fills an entry's or term's description and share image when its SEO card
     * is empty): the CMS's structure import doesn't carry it, so lumina:export-structure stores it under "seo"
     * and it's set here, on fields the blueprint has.
     *
     * @param  array<string, mixed>  $structure
     */
    private function seoFromFields(array $structure): void
    {
        foreach (['collections' => Collection::class, 'taxonomies' => Taxonomy::class] as $section => $model) {
            foreach ($structure[$section] ?? [] as $item) {
                $seo = array_intersect_key((array) ($item['seo'] ?? []), array_flip(ExportStructure::SEO));
                $record = $model::query()->where('handle', $item['handle'] ?? null)->first();
                if ($record !== null && $seo !== []) {
                    $record->forceFill($seo)->save();
                }
            }
        }
    }
}
