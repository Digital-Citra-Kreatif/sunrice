<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\RenameFieldHandle;
use Sunrice\Cms\Actions\Collections\SaveCollection;
use Sunrice\Cms\Actions\Entries\ChangeBlueprint;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\DuplicateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Query\EntryQuery;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea'],
        ['handle' => 'body', 'type' => 'textarea'],
    ]]);
    Blueprint::query()->create(['handle' => 'landing', 'title' => 'Landing page', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea'],
        ['handle' => 'headline', 'type' => 'text', 'required' => true],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]],
    ]]);
    Blueprint::query()->create(['handle' => 'other', 'title' => 'Other', 'fields' => [['handle' => 'x', 'type' => 'text']]]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'blueprints' => ['landing'], 'route' => '/{slug}']);
});

function overridePage(string $title = 'Home', array $data = ['intro' => 'Hi', 'body' => 'Body text']): EntryTranslation
{
    return app(CreateEntry::class)->handle(test()->pages, ['title' => $title, 'data' => $data]);
}

it('lists the blueprints an entry may use, its collection\'s first', function () {
    expect($this->pages->allowedBlueprints())->toBe(['page', 'landing'])
        ->and(Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'blueprint' => 'page', 'blueprints' => ['page']])->allowedBlueprints())->toBe(['page']);

    $entry = overridePage()->entry;
    expect($entry->blueprintHandle())->toBe('page')
        ->and(array_map(fn ($f) => $f->handle, $entry->schema()->fields()))->toBe(['intro', 'body']);
});

it('changes an entry\'s blueprint, keeping every value, and counts versions up', function () {
    $translation = overridePage();
    $entry = $translation->entry;
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    app(ChangeBlueprint::class)->handle($entry, 'landing');

    expect($entry->fresh()?->blueprint)->toBe('landing')
        ->and($entry->fresh()?->schema()->field('headline'))->not->toBeNull()
        ->and($translation->fresh()?->version)->toBe(2)
        ->and($translation->fresh()?->draft_data)->toMatchArray(['intro' => 'Hi', 'body' => 'Body text']) // hidden, not dropped
        ->and(Activity::query()->latest('id')->first()?->properties['changed'] ?? [])->toContain('blueprint');

    // Back to the collection's own: stored as none, and "body" is back with its value.
    app(ChangeBlueprint::class)->handle($entry->fresh(), 'page');
    expect($entry->fresh()?->blueprint)->toBeNull()
        ->and($entry->fresh()?->schema()->field('body'))->not->toBeNull();

    expect(fn () => app(ChangeBlueprint::class)->handle($entry->fresh(), 'other'))->toThrow(ValidationException::class);
});

it('validates and saves drafts with the entry\'s blueprint', function () {
    $translation = overridePage();
    app(ChangeBlueprint::class)->handle($translation->entry, 'landing');
    $translation = $translation->fresh();

    expect(fn () => app(SaveDraft::class)->handle($translation, ['title' => 'Home', 'data' => ['headline' => '']]))
        ->toThrow(ValidationException::class);

    app(SaveDraft::class)->handle($translation, ['title' => 'Home', 'data' => ['headline' => 'Welcome', 'body' => 'ignored']]);
    expect($translation->fresh()?->draft_data)->toMatchArray(['intro' => 'Hi', 'headline' => 'Welcome', 'body' => 'Body text']);
});

it('hydrates, syncs terms and finds references with the entry\'s blueprint', function () {
    $term = app(SaveTerm::class)->handle($this->tags, ['title' => 'News']);
    $translation = overridePage();
    app(ChangeBlueprint::class)->handle($translation->entry, 'landing');
    app(SaveDraft::class)->handle($translation->fresh(), ['title' => 'Home', 'data' => ['headline' => 'Welcome', 'topics' => [$term->id]]]);
    app(PublishEntry::class)->handle($translation->fresh());

    $live = EntryTranslation::query()->findOrFail($translation->id);
    $topics = $live->values()['topics'];
    expect($topics)->toHaveCount(1)
        ->and($topics[0])->not->toBe($term->id) // a term, not its id
        ->and($live->entry->terms()->pluck('sunrice_terms.id')->all())->toBe([$term->id])
        ->and($live->search_text)->toContain('Welcome')
        ->and((new EntryQuery(['pages']))->whereFieldTerm('topics', 'news')->count())->toBe(1); // the field is the entry's blueprint's
});

it('shows the entry\'s fields in the editor and offers the blueprints in its settings', function () {
    $entry = overridePage()->entry;
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    get(entryEditUrl($entry->id))->assertInertia(fn (Assert $page) => $page
        ->where('fields.1.handle', 'body')
        ->where('settings.blueprint', 'page')
        ->where('blueprints', [['handle' => 'page', 'title' => 'Page'], ['handle' => 'landing', 'title' => 'Landing page']]));

    put("/admin/cms/entries/{$entry->id}/settings", ['blueprint' => 'other'])->assertSessionHasErrors('blueprint');
    put("/admin/cms/entries/{$entry->id}/settings", ['blueprint' => 'landing'])->assertSessionHasNoErrors();
    get(entryEditUrl($entry->id))->assertInertia(fn (Assert $page) => $page
        ->where('fields.1.handle', 'headline')
        ->where('settings.blueprint', 'landing')
        ->where('entry.version', 2));

    // Only one blueprint: nothing to choose.
    $this->pages->update(['blueprints' => null]);
    get(entryEditUrl($entry->id))->assertInertia(fn (Assert $page) => $page
        ->where('blueprints', [])
        ->where('settings.blueprint', 'page') // no longer allowed: the collection's fields again
        ->where('fields.1.handle', 'body'));
});

it('lets only those who may edit the entry change its blueprint', function () {
    $entry = overridePage()->entry;
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate'));

    put("/admin/cms/entries/{$entry->id}/settings", ['blueprint' => 'landing', 'locale' => 'id'])->assertForbidden();
    expect($entry->fresh()?->blueprint)->toBeNull();
});

it('exports every blueprint\'s fields, each entry filling its own', function () {
    overridePage('Plain');
    $landing = overridePage('Landing', ['intro' => 'Lead']);
    app(ChangeBlueprint::class)->handle($landing->entry, 'landing');
    app(SaveDraft::class)->handle($landing->fresh(), ['title' => 'Landing', 'data' => ['headline' => 'Big']]);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.export'));

    $csv = get('/admin/cms/collections/pages/entries/export')->assertOk()->streamedContent();
    $rows = array_map(str_getcsv(...), array_values(array_filter(explode("\n", trim($csv)))));
    $byTitle = array_column(array_slice($rows, 1), null, 1);

    expect(array_slice($rows[0], 8))->toBe(['Intro', 'Body', 'Headline', 'Topics'])
        ->and(array_slice($byTitle['Plain'], 8))->toBe(['Hi', 'Body text', '', ''])
        ->and(array_slice($byTitle['Landing'], 8))->toBe(['Lead', '', 'Big', '']); // its hidden "body" stays out
});

it('keeps the blueprint when duplicating, and edits the allowed list on the collection', function () {
    $entry = overridePage()->entry;
    app(ChangeBlueprint::class)->handle($entry, 'landing');

    expect(app(DuplicateEntry::class)->handle($entry->fresh())->blueprint)->toBe('landing');

    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.update'));
    get("/admin/cms/collections/{$this->pages->id}/edit")->assertInertia(fn (Assert $page) => $page->where('values.blueprints', ['landing']));
    put("/admin/cms/collections/{$this->pages->id}", ['title' => 'Pages', 'handle' => 'pages', 'blueprint' => 'page', 'blueprints' => ['landing', 'other'], 'per_page' => 10])->assertSessionHasNoErrors();
    expect($this->pages->fresh()?->allowedBlueprints())->toBe(['page', 'landing', 'other']);
});

it('saves the blueprints offered with SaveCollection', function () {
    app(SaveCollection::class)->handle(['title' => 'Pages', 'blueprints' => ['other']], $this->pages);
    expect($this->pages->fresh()?->allowedBlueprints())->toBe(['page', 'other']);

    expect(fn () => app(SaveCollection::class)->handle(['title' => 'Pages', 'blueprints' => ['missing']], $this->pages))->toThrow(ValidationException::class);
    app(SaveCollection::class)->handle(['title' => 'Pages', 'blueprints' => []], $this->pages);
    expect($this->pages->fresh()?->blueprints)->toBeNull();
});

it('renames a field in the entries that use the blueprint, theirs or their collection\'s', function () {
    $plain = overridePage('Plain');
    $landing = overridePage('Landing', ['intro' => 'Lead']);
    app(ChangeBlueprint::class)->handle($landing->entry, 'landing');

    app(RenameFieldHandle::class)->handle(Blueprint::query()->where('handle', 'landing')->firstOrFail(), 'intro', 'lead');

    expect($landing->fresh()?->draft_data)->toHaveKey('lead')->not->toHaveKey('intro')
        ->and($plain->fresh()?->draft_data)->toHaveKey('intro')->not->toHaveKey('lead')
        ->and(Entry::usingBlueprint('page')->pluck('id')->all())->toBe([$plain->entry_id])
        ->and(Entry::usingBlueprint('landing')->pluck('id')->all())->toBe([$landing->entry_id]);
});

it('exports and imports the blueprints offered and an entry\'s own', function () {
    $landing = overridePage('Landing');
    app(ChangeBlueprint::class)->handle($landing->entry, 'landing');
    app(SaveDraft::class)->handle($landing->fresh(), ['title' => 'Landing', 'data' => ['headline' => 'Big']]);

    $structure = json_decode(json_encode(app(StructureExporter::class)->export(content: true), JSON_THROW_ON_ERROR), true); // as the file holds it
    expect(collect($structure['collections'])->firstWhere('handle', 'pages')['blueprints'] ?? null)->toBe(['landing'])
        ->and(collect($structure['entries'])->firstWhere('slug', 'landing')['blueprint'] ?? null)->toBe('landing');

    $landing->entry->delete();
    app(ForceDeleteEntry::class)->handle($landing->entry);
    $this->pages->update(['blueprints' => null]);
    app(StructureImporter::class)->import($structure);

    $imported = Entry::query()->where('collection_id', $this->pages->id)->where('blueprint', 'landing')->firstOrFail();
    expect($this->pages->fresh()?->blueprints)->toBe(['landing'])
        ->and($imported->translations()->firstOrFail()->draft_data)->toMatchArray(['headline' => 'Big']);
});
