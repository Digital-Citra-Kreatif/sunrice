<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Site\RoutePattern;
use Sunrice\Cms\Site\Site;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'unit', 'title' => 'Unit', 'fields' => [
        ['handle' => 'project', 'type' => 'entries', 'config' => ['collections' => ['projects']]],
        ['handle' => 'others', 'type' => 'entries', 'config' => ['collections' => ['projects'], 'multiple' => true]],
    ]]);
    $this->projects = Collection::query()->create(['handle' => 'projects', 'title' => 'Projects', 'route' => '/projects/{slug}']);
    $this->units = Collection::query()->create(['handle' => 'units', 'title' => 'Units', 'blueprint' => 'unit', 'route' => '/projects/{project}/{slug}', 'listing_route' => '/units']);
});

function routedEntry(Collection $collection, string $title, array $data = []): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection, ['title' => $title, 'data' => $data]));
}

function translateRouted(EntryTranslation $translation, string $title, string $slug): EntryTranslation
{
    $other = app(CreateTranslation::class)->handle($translation->entry, 'id');
    app(SaveDraft::class)->handle($other, ['title' => $title, 'slug' => $slug]);

    return app(PublishEntry::class)->handle($other->refresh());
}

it('checks patterns with fields', function (string $pattern, bool $plain, bool $withFields) {
    expect(RoutePattern::isValid($pattern))->toBe($plain)
        ->and(RoutePattern::isValid($pattern, parameters: true))->toBe($withFields);
})->with([
    ['/blog/{slug}', true, true],
    ['/projects/{project}/{slug}', false, true],
    ['/{project}/{slug}/plan', false, true],
    ['/projects/{project}', false, false],
    ['/projects/{project}/{project}/{slug}', false, false],
    ['/projects/{Project}/{slug}', false, false],
    ['/projects/{project}-x/{slug}', false, false],
]);

it('matches and builds paths with the related entries\' slugs', function () {
    $pattern = new RoutePattern('/projects/{project}/{slug}');

    expect($pattern->parameters())->toBe(['project'])
        ->and($pattern->match('/projects/tower-a/unit-1'))->toBe(['slug' => 'unit-1', 'parameters' => ['project' => 'tower-a']])
        ->and($pattern->match('/projects/-/unit-1'))->toBe(['slug' => 'unit-1', 'parameters' => ['project' => '-']])
        ->and($pattern->match('/projects/unit-1'))->toBeNull()
        ->and($pattern->path('unit-1', ['project' => 'tower-a']))->toBe('/projects/tower-a/unit-1')
        ->and($pattern->path('unit-1'))->toBe('/projects/-/unit-1')
        ->and($pattern->specificity())->toBeGreaterThan((new RoutePattern('/projects/{slug}'))->specificity());
});

it('serves entries at URLs holding their related entry\'s slug, most specific pattern first', function () {
    $tower = routedEntry($this->projects, 'Tower');
    $unit = routedEntry($this->units, 'Unit one', ['project' => $tower->entry_id]);
    $loose = routedEntry($this->units, 'Loose');

    expect($unit->url())->toBe(url('/projects/tower/unit-one'))
        ->and($loose->url())->toBe(url('/projects/-/loose'));
    get('/projects/tower')->assertOk()->assertSee('Tower');
    get('/projects/tower/unit-one')->assertOk()->assertSee('Unit one');
    get('/projects/-/loose')->assertOk();
    // Another project's slug isn't this unit's address.
    routedEntry($this->projects, 'Bridge');
    get('/projects/bridge/unit-one')->assertNotFound();
});

it('fills URLs in each language with the related entry\'s slug there, else the main one\'s', function () {
    $tower = routedEntry($this->projects, 'Tower');
    $unit = routedEntry($this->units, 'Unit one', ['project' => $tower->entry_id]);
    $unitId = translateRouted($unit, 'Unit satu', 'unit-satu');

    expect($unitId->refresh()->url())->toBe(url('/id/projects/tower/unit-satu'));
    translateRouted($tower, 'Menara', 'menara');
    app(Site::class)->flush();

    expect(EntryTranslation::query()->find($unitId->id)->url())->toBe(url('/id/projects/menara/unit-satu'));
    get('/id/projects/menara/unit-satu')->assertOk()->assertSee('Unit satu');
});

it('redirects the old URLs when the related entry\'s slug changes', function () {
    $tower = routedEntry($this->projects, 'Tower');
    routedEntry($this->units, 'Unit one', ['project' => $tower->entry_id]);
    routedEntry($this->units, 'Unit two', ['project' => $tower->entry_id]);
    app(SaveDraft::class)->handle($tower->refresh(), ['title' => 'Tower', 'slug' => 'tower-a']);
    app(PublishEntry::class)->handle($tower->refresh());

    get('/projects/tower/unit-one?x=1')->assertRedirect(url('/projects/tower-a/unit-one?x=1'))->assertStatus(301);
    get('/projects/tower/unit-two')->assertRedirect(url('/projects/tower-a/unit-two'));
    get('/projects/tower')->assertRedirect(url('/projects/tower-a'));
    get('/projects/tower-a/unit-one')->assertOk();
});

it('redirects the old URL when an entry links to another related entry', function () {
    $tower = routedEntry($this->projects, 'Tower');
    $bridge = routedEntry($this->projects, 'Bridge');
    $unit = routedEntry($this->units, 'Unit one', ['project' => $tower->entry_id]);
    app(SaveDraft::class)->handle($unit->refresh(), ['title' => 'Unit one', 'data' => ['project' => $bridge->entry_id]]);
    app(PublishEntry::class)->handle($unit->refresh());

    get('/projects/tower/unit-one')->assertRedirect(url('/projects/bridge/unit-one'));
});

it('lists, maps and links filled URLs without a query per entry', function () {
    $tower = routedEntry($this->projects, 'Tower');
    $bridge = routedEntry($this->projects, 'Bridge');
    foreach (range(1, 4) as $i) {
        routedEntry($this->units, "Unit {$i}", ['project' => $i % 2 ? $tower->entry_id : $bridge->entry_id]);
    }

    get('/units')->assertOk()->assertSee(url('/projects/tower/unit-1'))->assertSee(url('/projects/bridge/unit-2'));
    get('/sitemap.xml')->assertOk()->assertSee(url('/projects/bridge/unit-4'));

    app(Site::class)->flush();
    config(['sunrice-cms.cache.enabled' => false]);
    $units = EntryTranslation::query()->where('collection_id', $this->units->id)->with('entry.collection', 'entry.translations')->get();
    DB::enableQueryLog();
    $urls = $units->map->url()->all();
    expect($urls)->toContain(url('/projects/tower/unit-3'))
        // The collection's fields, then every project's slugs at once.
        ->and(count(DB::getQueryLog()))->toBeLessThanOrEqual(2);
});

it('checks fields in a collection\'s URL against its blueprint', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.create'));

    post('/admin/collections', ['title' => 'Flats', 'handle' => 'flats', 'route' => '/flats/{project}/{slug}'])->assertSessionHasErrors('route');
    post('/admin/collections', ['title' => 'Flats', 'handle' => 'flats', 'blueprint' => 'unit', 'route' => '/flats/{others}/{slug}'])->assertSessionHasErrors('route');
    post('/admin/collections', ['title' => 'Flats', 'handle' => 'flats', 'blueprint' => 'unit', 'route' => '/flats/{nope}/{slug}'])->assertSessionHasErrors('route');
    post('/admin/collections', ['title' => 'Flats', 'handle' => 'flats', 'blueprint' => 'unit', 'route' => '/flats/{project}/{slug}'])->assertSessionHasNoErrors();
});
