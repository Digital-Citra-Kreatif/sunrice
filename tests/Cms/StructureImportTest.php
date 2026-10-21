<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Structure\ImportReport;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Structure\StructureImportFailed;

use function Pest\Laravel\artisan;

// The fixture has Indonesian texts: the site has both languages.
beforeEach(fn () => config(['sunrice-cms.locales' => ['en', 'id']]));

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

    // A redirect in every language carries its switch both ways.
    $changed['redirects'][0]['every_language'] = true;
    expect(app(StructureImporter::class)->import($changed)->status('redirect', '/old-about'))->toBe(ImportReport::UPDATED)
        ->and(app(StructureExporter::class)->export()['redirects'][0])->toMatchArray(['from' => '/old-about', 'every_language' => true]);
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

it('carries every setting of collections, taxonomies, global sets and forms', function (string $table, array $carried) {
    // A new column on these tables is part of the content model: export and import it too.
    // Not the patterns this site's languages had before (they redirect here): StructureImporter::NOT_CARRIED.
    $columns = array_diff(Schema::getColumnListing($table), ['id', 'created_at', 'updated_at', 'deleted_at', ...StructureImporter::NOT_CARRIED]);

    expect(array_values(array_diff($columns, $carried)))->toBe([]);
})->with([
    'collections' => ['sunrice_collections', StructureImporter::COLLECTION],
    'taxonomies' => ['sunrice_taxonomies', StructureImporter::TAXONOMY],
    'global sets' => ['sunrice_global_sets', StructureImporter::GLOBAL_SET],
    'forms' => ['sunrice_forms', StructureImporter::FORM],
]);

it('round-trips "SEO from fields" and the roles a form emails', function () {
    app(StructureImporter::class)->import(structureFixture());

    expect(Collection::query()->where('handle', 'posts')->value('seo_description_field'))->toBe('intro')
        ->and(Taxonomy::query()->where('handle', 'topics')->value('seo_description_field'))->toBe('intro')
        ->and(Form::query()->where('handle', 'enquiry')->first()->notify_roles)->toBe(['editor']);

    $bad = structureFixture();
    $bad['collections'][1]['seo_image_field'] = 'Not a handle';
    expect(fn () => app(StructureImporter::class)->import($bad))->toThrow(StructureImportFailed::class, 'collection "posts"');
});

/** @return list<array{0: string, 1: list<array<string, mixed>>}> */
function importedMenu(string $handle = 'main'): array
{
    $flat = [];
    $walk = function ($items, int $depth) use (&$walk, &$flat): void {
        foreach ($items as $item) {
            $flat[] = str_repeat('-', $depth).($item['entry'] ?? $item['term'] ?? $item['collection'] ?? $item['url']);
            $walk($item['children'], $depth + 1);
        }
    };
    $menu = collect(app(StructureExporter::class)->export()['menus'])->firstWhere('handle', $handle);
    $walk($menu['items'] ?? [], 0);

    return $flat;
}

it('keeps the links of a menu that exists: a file with fewer links removes none (NEW-admin-1)', function () {
    app(StructureImporter::class)->import(structureFixture());
    $fewer = structureFixture();
    $fewer['menus'][0]['items'] = [$fewer['menus'][0]['items'][3]]; // only the address

    $dry = app(StructureImporter::class)->import($fewer, dryRun: true);
    $report = app(StructureImporter::class)->import($fewer);

    expect($dry->status('menu', 'main'))->toBe(ImportReport::UNCHANGED)
        ->and($report->status('menu', 'main'))->toBe(ImportReport::UNCHANGED)
        ->and($report->items()[array_search('menu', array_column($report->items(), 'type'), true)]['notes'])->toBe([])
        ->and(importedMenu())->toBe(['pages/about', '-pages/team', 'posts', 'topics/news', 'https://example.com']);
});

it('keeps links editors added, and adds the file\'s missing links in the file\'s order, saying which (NEW-admin-1)', function () {
    $start = structureFixture();
    $start['menus'][0]['items'] = [$start['menus'][0]['items'][1], $start['menus'][0]['items'][3]]; // posts, the address
    app(StructureImporter::class)->import($start);
    $menu = Menu::query()->where('handle', 'main')->first();
    $items = $menu->items()->orderBy('sort_order')->get();
    app(SaveMenu::class)->handle($menu, [
        ...$items->map(fn ($i) => ['collection_id' => $i->collection_id, 'url' => $i->url, 'labels' => $i->labels, 'query' => $i->query_string])->all(),
        ['url' => 'https://added.example', 'labels' => ['en' => 'Added in the panel']],
    ]);

    $dry = app(StructureImporter::class)->import(structureFixture(), dryRun: true);
    $notes = collect($dry->items())->firstWhere('type', 'menu')['notes'];
    expect($dry->status('menu', 'main'))->toBe(ImportReport::UPDATED)
        ->and($notes)->toBe(['Adds a link to pages/about.', 'Adds a link to pages/team.', 'Adds a link to topics/news.'])
        ->and($menu->items()->count())->toBe(3);

    app(StructureImporter::class)->import(structureFixture());
    expect(importedMenu())->toBe(['pages/about', '-pages/team', 'posts', 'topics/news', 'https://example.com', 'https://added.example']);
    expect(app(StructureImporter::class)->import(structureFixture())->status('menu', 'main'))->toBe(ImportReport::UNCHANGED);
});

it('replaces a menu\'s links only when asked, listing the links it removes (NEW-admin-1)', function () {
    app(StructureImporter::class)->import(structureFixture());
    $fewer = structureFixture();
    $fewer['menus'][0]['items'] = [$fewer['menus'][0]['items'][3]];
    $file = tempnam(sys_get_temp_dir(), 'structure');
    file_put_contents($file, json_encode($fewer));

    artisan('sunrice:import-structure', ['file' => $file, '--dry-run' => true, '--replace-menus' => true])
        ->expectsOutputToContain('Removes the link to pages/about.')->assertSuccessful();
    expect(Menu::query()->where('handle', 'main')->first()->items()->count())->toBe(5);

    $report = app(StructureImporter::class)->import($fewer, replaceMenus: true);
    expect($report->status('menu', 'main'))->toBe(ImportReport::UPDATED)
        ->and(collect($report->items())->firstWhere('type', 'menu')['notes'])->toBe([
            'Removes the link to pages/about.', 'Removes the link to pages/team.', 'Removes the link to posts.', 'Removes the link to topics/news.',
        ])
        ->and(importedMenu())->toBe(['https://example.com']);
    unlink($file);
});

it('exports links to entries by collection and slug, those in the trash included, and leaves out links to what is gone', function () {
    app(StructureImporter::class)->import(structureFixture());
    $team = Entry::query()->whereHas('translations', fn ($q) => $q->where('draft_slug', 'team'))->first();
    $team->delete(); // in the trash
    $menu = Menu::query()->where('handle', 'main')->first();
    $menu->items()->create(['url' => null, 'labels' => [], 'sort_order' => 9]); // its entry permanently deleted

    expect(importedMenu())->toBe(['pages/about', '-pages/team', 'posts', 'topics/news', 'https://example.com']);
});

it('carries conditions of one rule and of several between sites', function () {
    $structure = ['blueprints' => [['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'layout', 'type' => 'text', 'label' => '', 'required' => false, 'instructions' => '', 'translatable' => null, 'validation' => [], 'config' => [], 'fields' => []],
        ['handle' => 'featured', 'type' => 'toggle', 'label' => '', 'required' => false, 'instructions' => '', 'translatable' => null, 'validation' => [], 'config' => [], 'fields' => []],
        ['handle' => 'one', 'type' => 'text', 'label' => '', 'required' => false, 'instructions' => '', 'translatable' => null, 'validation' => [], 'config' => [], 'fields' => [],
            'condition' => ['field' => 'layout', 'operator' => 'filled', 'value' => null]],
        ['handle' => 'several', 'type' => 'text', 'label' => '', 'required' => false, 'instructions' => '', 'translatable' => null, 'validation' => [], 'config' => [], 'fields' => [],
            'condition' => ['match' => 'any', 'rules' => [['field' => 'layout', 'operator' => 'equals', 'value' => 'video'], ['field' => 'featured', 'operator' => 'equals', 'value' => true]]]],
    ]]]];

    expect(app(StructureImporter::class)->import($structure)->status('blueprint', 'post'))->toBe(ImportReport::CREATED);
    $exported = json_decode(json_encode(app(StructureExporter::class)->export(), JSON_THROW_ON_ERROR), true);
    expect(collect($exported['blueprints'])->firstWhere('handle', 'post')['fields'])->toEqual($structure['blueprints'][0]['fields']);
});
