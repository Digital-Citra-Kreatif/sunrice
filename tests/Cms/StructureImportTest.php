<?php

declare(strict_types=1);

use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Structure\ImportReport;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Structure\StructureImportFailed;

use function Pest\Laravel\artisan;

function structureFixture(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/Fixtures/structure.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('round-trips the fixture: imported, then exported as it was', function () {
    $report = app(StructureImporter::class)->import(structureFixture());

    expect($report->items(ImportReport::REFUSED))->toBe([])
        ->and($report->status('collection', 'posts'))->toBe(ImportReport::CREATED)
        ->and($report->status('entry', 'pages/team'))->toBe(ImportReport::CREATED);
    $exported = json_decode(json_encode(app(StructureExporter::class)->export(content: true), JSON_THROW_ON_ERROR), true);
    expect($exported)->toEqual(structureFixture());

    // The entry links resolved, the parent set, the published one live.
    $team = Entry::query()->whereHas('translations', fn ($q) => $q->where('draft_slug', 'team'))->first();
    expect($team->parent?->mainTranslation()?->isLive())->toBeTrue()
        ->and(Menu::query()->where('handle', 'main')->first()->items()->whereNotNull('entry_id')->count())->toBe(2);
});

it('changes nothing when imported again, and reports updates by handle', function () {
    app(StructureImporter::class)->import(structureFixture());
    $again = app(StructureImporter::class)->import(structureFixture());
    expect(array_unique(array_column($again->items(), 'status')))->toBe([ImportReport::UNCHANGED]);

    $changed = structureFixture();
    $changed['collections'][0]['title'] = 'Site pages';
    $changed['redirects'][0]['status'] = 302;
    $report = app(StructureImporter::class)->import($changed);
    expect($report->status('collection', 'pages'))->toBe(ImportReport::UPDATED)
        ->and($report->status('redirect', '/old-about'))->toBe(ImportReport::UPDATED)
        ->and(Collection::query()->where('handle', 'pages')->value('title'))->toBe('Site pages');
});

it('saves nothing on a dry run', function () {
    $report = app(StructureImporter::class)->import(structureFixture(), dryRun: true);

    expect($report->dryRun)->toBeTrue()
        ->and($report->status('blueprint', 'page'))->toBe(ImportReport::CREATED)
        ->and(Blueprint::query()->count())->toBe(0)
        ->and(Collection::query()->count())->toBe(0);
});

it('refuses to remove fields unless forced', function () {
    app(StructureImporter::class)->import(structureFixture());
    $fewer = structureFixture();
    array_pop($fewer['blueprints'][1]['fields']); // page's topics

    $report = app(StructureImporter::class)->import($fewer);
    expect($report->status('blueprint', 'page'))->toBe(ImportReport::REFUSED)
        ->and($report->items(ImportReport::REFUSED)[0]['notes'][0])->toContain('topics')
        ->and(Blueprint::query()->where('handle', 'page')->first()->schema()->field('topics'))->not->toBeNull();

    expect(app(StructureImporter::class)->import($fewer, force: true)->status('blueprint', 'page'))->toBe(ImportReport::UPDATED)
        ->and(Blueprint::query()->where('handle', 'page')->first()->schema()->field('topics'))->toBeNull();
});

it('validates definitions and imports nothing when one is invalid', function () {
    $bad = structureFixture();
    $bad['blueprints'][0]['fields'][0]['type'] = 'no-such-type';

    expect(fn () => app(StructureImporter::class)->import($bad))->toThrow(StructureImportFailed::class, 'blueprint "contact"');
    expect(Collection::query()->count())->toBe(0)->and(Blueprint::query()->count())->toBe(0);

    expect(fn () => app(StructureImporter::class)->import(['widgets' => []]))->toThrow(StructureImportFailed::class, 'Unknown sections');
});

it('exports and imports from the commands', function () {
    app(StructureImporter::class)->import(structureFixture());
    $file = tempnam(sys_get_temp_dir(), 'structure').'.json';

    artisan('sunrice:export-structure', ['file' => $file])->assertSuccessful();
    $written = json_decode((string) file_get_contents($file), true);
    expect($written)->not->toHaveKey('entries')->and($written['collections'])->toHaveCount(2);

    artisan('sunrice:import-structure', ['file' => $file, '--dry-run' => true])->expectsOutputToContain('Dry run')->assertSuccessful();
    artisan('sunrice:import-structure', ['file' => $file])->assertSuccessful();
    artisan('sunrice:import-structure', ['file' => '/no/such/file.json'])->assertFailed();
    @unlink($file);
});
