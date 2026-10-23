<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\DeleteBlueprint;
use Sunrice\Cms\Actions\Collections\DeleteCollection;
use Sunrice\Cms\Actions\Collections\RestoreDeleted;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\MoveEntry;
use Sunrice\Cms\Actions\Entries\SaveEntrySettings;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * What a deletion, a trash and restore, a move or a change must not
 * break: the definitions content needs, access, the order and the cache.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'body', 'type' => 'textarea']]]);
    Blueprint::query()->create(['handle' => 'landing', 'title' => 'Landing', 'fields' => [['handle' => 'headline', 'type' => 'text']]]);
});

function integrityEntry(Collection $collection, string $title, ?int $parent = null): Entry
{
    $entry = app(CreateEntry::class)->handle($collection, ['title' => $title])->entry;
    if ($parent !== null) {
        $entry->forceFill(['parent_id' => $parent])->save();
    }

    return $entry;
}

/** @return list<string> the titles of the entries under $parent, in order */
function integrityLevel(Collection $collection, ?int $parent = null): array
{
    return Entry::query()->with('translations')->where('collection_id', $collection->id)->where('parent_id', $parent)
        ->orderBy('sort_order')->orderBy('id')->get()->map(fn (Entry $entry) => $entry->mainTranslation()?->draft_title)->all();
}

it('keeps a blueprint a collection offers, or an entry chose, from being deleted', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'blueprints' => ['landing']]);
    $delete = app(DeleteBlueprint::class);
    $landing = Blueprint::query()->where('handle', 'landing')->firstOrFail();

    expect($delete->usedBy($landing))->toBe(['the collection "Pages" (one of its other blueprints)']);

    // No longer offered, but an entry still has it.
    integrityEntry($pages, 'Home')->forceFill(['blueprint' => 'landing'])->save();
    $pages->forceFill(['blueprints' => null])->save();
    expect($delete->usedBy($landing))->toBe(['an entry of "Pages"'])
        ->and(fn () => $delete->handle($landing))->toThrow(ValidationException::class, 'an entry of "Pages"');

    // The list says so too.
    $pages->forceFill(['blueprints' => ['landing']])->save();
    actingAs(adminUser(Panel::ACCESS, 'cms.blueprints.view'));
    get('/admin/cms/blueprints')->assertInertia(fn (Assert $page) => $page
        ->where('blueprints.0.handle', 'landing')->where('blueprints.0.usedBy', ['the collection "Pages" (one of its other blueprints)', 'an entry of "Pages"']));
});

it('keeps the blueprint of a collection or taxonomy in the trash, so restoring it brings back its fields', function () {
    $articles = Collection::query()->create(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'page']);
    app(DeleteCollection::class)->handle($articles);
    $page = Blueprint::query()->where('handle', 'page')->firstOrFail();

    expect(fn () => app(DeleteBlueprint::class)->handle($page))->toThrow(ValidationException::class, 'the collection "Articles" (in the trash)');

    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'blueprint' => 'landing']);
    $tags->delete();
    expect(app(DeleteBlueprint::class)->usedBy(Blueprint::query()->where('handle', 'landing')->firstOrFail()))->toBe(['the taxonomy "Tags" (in the trash)']);

    app(RestoreDeleted::class)->collection($articles->fresh() ?? $articles);
    expect(Collection::query()->findOrFail($articles->id)->schema()->field('body'))->not->toBeNull();
});

it('keeps who may work in a taxonomy while it is in the trash', function () {
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    $editor = adminUser('cms.terms.topics.view', 'cms.terms.topics.update');
    expect($editor->can('cms.terms.topics.update'))->toBeTrue();

    $topics->delete();
    app(RestoreDeleted::class)->taxonomy($topics);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($editor->fresh()?->can('cms.terms.topics.update'))->toBeTrue();
});

it('moves an entry past its next sibling only, keeping its children under it, in one statement', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'ordered' => true, 'hierarchical' => true]);
    $a = integrityEntry($pages, 'A');
    integrityEntry($pages, 'A1', $a->id);
    integrityEntry($pages, 'B');

    app(MoveEntry::class)->handle($a, 'down');
    expect(integrityLevel($pages))->toBe(['B', 'A'])
        ->and(integrityLevel($pages, $a->id))->toBe(['A1']);

    // However many entries, a move writes the order once.
    $flat = Collection::query()->create(['handle' => 'flat', 'title' => 'Flat', 'blueprint' => 'page', 'ordered' => true]);
    $first = integrityEntry($flat, 'First');
    foreach (range(1, 30) as $n) {
        integrityEntry($flat, "Entry {$n}");
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    app(MoveEntry::class)->handle($first, 'down');
    $updates = collect(DB::getQueryLog())->filter(fn (array $query) => str_starts_with(strtolower($query['query']), 'update') && str_contains($query['query'], 'sunrice_entries'));
    DB::disableQueryLog();

    expect($updates)->toHaveCount(1)
        ->and(integrityLevel($flat)[0])->toBe('Entry 1');
});

it('refreshes the site cache when an entry moves, changes blueprint or closes its comments', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'ordered' => true, 'comments' => true]);
    $a = integrityEntry($pages, 'A');
    integrityEntry($pages, 'B');
    $cache = app(SiteCache::class);
    $cached = fn (): array => $cache->remember('integrity', fn () => [integrityLevel($pages), Entry::query()->findOrFail($a->id)->only(['blueprint', 'comments_closed'])]);

    expect($cached()[0])->toBe(['A', 'B']);
    app(MoveEntry::class)->handle($a, 'down');
    expect($cached()[0])->toBe(['B', 'A']);

    $a->refresh()->forceFill(['blueprint' => 'landing'])->save();
    expect($cached()[1]['blueprint'])->toBe('landing');

    app(SaveEntrySettings::class)->handle($a->refresh(), ['comments_closed' => true]);
    expect($cached()[1]['comments_closed'])->toBeTrue();
});

it('refuses a move that would put the entries under it deeper than allowed', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'hierarchical' => true]);
    $parent = null;
    foreach (range(1, Entry::MAX_DEPTH - 1) as $level) {
        $parent = integrityEntry($pages, "Level {$level}", $parent?->id);
    }
    $branch = integrityEntry($pages, 'Branch');
    integrityEntry($pages, 'Leaf', $branch->id);

    // Branch would be at the deepest level, and its leaf one below it.
    expect(fn () => app(SaveEntrySettings::class)->handle($branch, ['parent_id' => $parent?->id]))
        ->toThrow(ValidationException::class, 'counting the ones under this entry');

    // Without its leaf it fits.
    Entry::query()->where('parent_id', $branch->id)->update(['parent_id' => null]);
    app(SaveEntrySettings::class)->handle($branch->refresh(), ['parent_id' => $parent?->id]);
    expect($branch->refresh()->parent_id)->toBe($parent?->id);
});
