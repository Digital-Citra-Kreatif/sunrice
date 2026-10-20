<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Structure\StructureExporter;

/**
 * Writes database/structure.json: the CMS's structure export (sunrice:export-structure) plus the "SEO from
 * fields" settings of collections and taxonomies, which the CMS's export leaves out (see qa findings).
 * StructureSeeder imports both.
 */
#[Signature('lumina:export-structure {file=database/structure.json}')]
#[Description('Export the content model, SEO-from-fields settings included')]
final class ExportStructure extends Command
{
    public const SEO = ['seo_title_field', 'seo_description_field', 'seo_image_field'];

    public function handle(StructureExporter $exporter): int
    {
        $structure = $exporter->export(content: false);

        foreach (['collections' => Collection::class, 'taxonomies' => Taxonomy::class] as $section => $model) {
            $models = $model::query()->get()->keyBy('handle');
            foreach ($structure[$section] ?? [] as $i => $item) {
                $record = $models->get($item['handle']);
                if ($record !== null) {
                    $structure[$section][$i]['seo'] = array_filter($record->only(self::SEO), fn ($value) => $value !== null);
                }
            }
        }

        $path = base_path((string) $this->argument('file'));
        file_put_contents($path, json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->components->info("Wrote {$path}.");

        return self::SUCCESS;
    }
}
