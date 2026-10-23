<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\DiscardChanges;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\RestoreRevision;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\SaveEntryTerms;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Revision;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/*
 * An entry's terms are its own, shared by its languages, for the
 * taxonomies attached to its collection (the editor's Taxonomies card),
 * whatever its blueprint: saved with the draft, live once published.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id'], 'inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    // No blueprint: the card is there all the same.
    $this->notes = Collection::query()->create(['handle' => 'notes', 'title' => 'Notes', 'route' => '/notes/{slug}']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $this->format = Taxonomy::query()->create(['handle' => 'format', 'title' => 'Format', 'hierarchical' => true]);
    $this->secret = Taxonomy::query()->create(['handle' => 'secret', 'title' => 'Secret']);
    $this->tags->collections()->attach($this->notes);
    $this->format->collections()->attach($this->notes, ['single' => true]);
    $this->php = app(SaveTerm::class)->handle($this->tags, ['title' => 'PHP']);
    $this->laravel = app(SaveTerm::class)->handle($this->tags, ['title' => 'Laravel']);
    $this->guide = app(SaveTerm::class)->handle($this->format, ['title' => 'Guide']);
    $this->howto = app(SaveTerm::class)->handle($this->format, ['title' => 'How-to', 'parent_id' => $this->guide->id]);
    $this->hidden = app(SaveTerm::class)->handle($this->secret, ['title' => 'Hidden']);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update', 'cms.entries.publish'));
});

/** A note, published with these terms (by taxonomy handle). */
function liveNote(array $terms = [], string $title = 'Hello'): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->notes, ['title' => $title, 'terms' => $terms]));
}

/** @return list<int> the entry's rows in sunrice_entry_term, whatever their taxonomy, sorted */
function storedTerms(EntryTranslation|Entry $entry): array
{
    $id = $entry instanceof Entry ? $entry->id : $entry->entry_id;

    return DB::table('sunrice_entry_term')->where('entry_id', $id)->orderBy('term_id')->pluck('term_id')->map(fn ($id): int => (int) $id)->all();
}

/** @return list<int> the entry's live terms, sorted */
function liveTerms(EntryTranslation|Entry $entry): array
{
    $ids = ($entry instanceof Entry ? $entry : $entry->entry)->liveTermIds();
    sort($ids);

    return $ids;
}

it('gives the editor a Taxonomies card: the collection\'s taxonomies, by title, one term only or not, whatever the blueprint', function () {
    $note = liveNote(['tags' => [$this->php->id], 'format' => [$this->howto->id]]);

    get(entryEditUrl($note->entry_id))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('taxonomies', [
            ['handle' => 'format', 'title' => 'Format', 'hierarchical' => true, 'single' => true],
            ['handle' => 'tags', 'title' => 'Tags', 'hierarchical' => false, 'single' => false],
        ])
        ->where('values.terms', ['format' => [$this->howto->id], 'tags' => [$this->php->id]])
        ->where('can.terms', true)
        ->where('termLabels', [(string) $this->php->id => 'PHP', (string) $this->howto->id => 'How-to'])
        ->where('entry.live', null));
    // A new entry's card, empty.
    get('/admin/cms/collections/notes/entries/create')->assertInertia(fn (Assert $page) => $page
        ->where('taxonomies.1.handle', 'tags')->where('values.terms', ['format' => [], 'tags' => []])->where('can.terms', true));

    // A collection without taxonomies: no card.
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    $page = app(CreateEntry::class)->handle($pages, ['title' => 'About']);
    get(entryEditUrl($page->entry_id))->assertInertia(fn (Assert $page) => $page->where('taxonomies', [])->where('values.terms', []));
});

it('lists only the taxonomies one may see the terms of, unless one may change the entry\'s terms', function () {
    $note = liveNote(['tags' => [$this->php->id]]);
    config(['sunrice-cms.locales' => ['en', 'id']]);
    app(CreateTranslation::class)->handle($note->entry, 'id');

    // A translator, who may see Tags' terms: Tags only, read-only, and their saves leave terms alone.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate', 'cms.terms.tags.view'));
    get(entryEditUrl($note->entry_id, 'id'))->assertInertia(fn (Assert $page) => $page
        ->where('taxonomies', [['handle' => 'tags', 'title' => 'Tags', 'hierarchical' => false, 'single' => false]])
        ->where('values.terms', ['tags' => [$this->php->id]])
        ->where('can.terms', false));
    put("/admin/cms/entries/{$note->entry_id}", ['locale' => 'id', 'title' => 'Halo', 'terms' => ['tags' => [$this->laravel->id]]])->assertSessionHasNoErrors();
    expect($note->entry->fresh()?->draft_terms)->toBeNull();
    // Without any terms permission: no card.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate'));
    get(entryEditUrl($note->entry_id, 'id'))->assertInertia(fn (Assert $page) => $page->where('taxonomies', []));

    // An author of their own entries, without cms.terms.view: every taxonomy of the collection, and the picker serves them.
    $author = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update-own');
    actingAs($author);
    $own = app(CreateEntry::class)->handle($this->notes, ['title' => 'Mine']);
    get(entryEditUrl($own->entry_id))->assertInertia(fn (Assert $page) => $page->where('taxonomies.1.handle', 'tags')->where('can.terms', true));
    $this->getJson('/admin/cms/terms/picker?taxonomy=format')->assertOk()->assertJsonPath('items.1', ['id' => $this->howto->id, 'label' => 'How-to', 'depth' => 1]);
    $this->getJson('/admin/cms/terms/picker?taxonomy=secret')->assertForbidden();
});

it('saves terms with the draft, leaving the live ones, and publishes them with any language', function () {
    $note = liveNote(['tags' => [$this->php->id]]);
    $entryId = $note->entry_id;

    put("/admin/cms/entries/{$entryId}", ['title' => 'Hello', 'version' => $note->fresh()?->version, 'terms' => ['tags' => [$this->php->id, $this->laravel->id], 'format' => [$this->guide->id]]])
        ->assertSessionHasNoErrors();
    $note->refresh();
    expect(liveTerms($note))->toBe([$this->php->id])
        ->and($note->entry->draftTermIds())->toEqualCanonicalizing([$this->php->id, $this->laravel->id, $this->guide->id])
        ->and($note->hasUnpublishedChanges())->toBeTrue()
        // The version counts up, so the editor starts afresh.
        ->and($note->version)->toBe(3);

    // Changes pending: the Changed markers, Compare with live, the list.
    get(entryEditUrl($entryId))->assertInertia(fn (Assert $page) => $page
        ->where('entry.hasUnpublishedChanges', true)
        ->where('entry.live.changed', ['terms.format', 'terms.tags']));
    get("/admin/cms/entries/{$entryId}/translations/en/compare-live")->assertOk()->assertExactJson(['fields' => [
        ['label' => 'Format', 'live' => '', 'draft' => 'Guide'],
        ['label' => 'Tags', 'live' => 'PHP', 'draft' => 'PHP, Laravel'],
    ]]);
    get('/admin/cms/collections/notes/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.0.changes', true));

    // Publishing another language publishes them too: they're every language's.
    $id = app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($note->entry, 'id'), ['title' => 'Halo']);
    app(PublishEntry::class)->handle($id->fresh());
    expect(liveTerms($note->entry->fresh()))->toEqualCanonicalizing([$this->php->id, $this->laravel->id, $this->guide->id])
        ->and($note->entry->fresh()?->draft_terms)->toBeNull()
        ->and($note->fresh()?->hasUnpublishedChanges())->toBeFalse();
});

it('refuses two terms where one only is allowed, a taxonomy the collection hasn\'t, and terms of another taxonomy', function () {
    $note = app(CreateEntry::class)->handle($this->notes, ['title' => 'Hello']);
    $save = fn (array $terms) => app(SaveDraft::class)->handle($note->fresh(), ['terms' => $terms]);

    expect(fn () => $save(['format' => [$this->guide->id, $this->howto->id]]))->toThrow(ValidationException::class, 'Choose one Format term: Notes allows only one.')
        ->and(fn () => $save(['secret' => [$this->hidden->id]]))->toThrow(ValidationException::class, 'secret isn\'t one of Notes\'s taxonomies.')
        ->and(fn () => $save(['nope' => []]))->toThrow(ValidationException::class, 'There is no taxonomy "nope".')
        ->and(fn () => $save(['tags' => [$this->guide->id]]))->toThrow(ValidationException::class, "{$this->guide->id} isn't a term of Tags.")
        ->and(fn () => $save(['tags' => ['php', 'ruby', 'go']]))->toThrow(ValidationException::class, 'ruby, go aren\'t terms of Tags.')
        ->and(fn () => app(CreateEntry::class)->handle($this->notes, ['title' => 'X', 'terms' => 'tags']))->toThrow(ValidationException::class);
    expect(Entry::query()->count())->toBe(1)->and($note->entry->fresh()?->draft_terms)->toBeNull();

    put("/admin/cms/entries/{$note->entry_id}", ['title' => 'Hello', 'terms' => ['format' => [$this->guide->id, $this->howto->id]]])->assertSessionHasErrors('terms.format');

    // Slugs, one id for a single taxonomy, and null for none.
    $save(['tags' => ['php', (string) $this->laravel->id], 'format' => $this->howto->id]);
    expect($note->entry->fresh()?->draftTermIds())->toEqualCanonicalizing([$this->php->id, $this->laravel->id, $this->howto->id]);
    $save(['format' => null]);
    expect($note->entry->fresh()?->draftTermIds())->toEqualCanonicalizing([$this->php->id, $this->laravel->id]);
});

it('leaves terms of a taxonomy no longer attached alone, unseen until attached again, and a taxonomy not given keeps its terms', function () {
    $note = liveNote(['tags' => [$this->php->id], 'format' => [$this->guide->id]]);
    $this->format->collections()->detach($this->notes);
    app(SaveDraft::class)->handle($note->fresh(), ['terms' => ['tags' => [$this->laravel->id]]]);
    app(PublishEntry::class)->handle($note->fresh());

    expect(storedTerms($note))->toBe(collect([$this->laravel->id, $this->guide->id])->sort()->values()->all())
        ->and(liveTerms($note))->toBe([$this->laravel->id]);
    get(entryEditUrl($note->entry_id))->assertInertia(fn (Assert $page) => $page->where('values.terms', ['tags' => [$this->laravel->id]]));

    // Attached again: they're back.
    $this->format->collections()->attach($this->notes);
    expect(liveTerms($note))->toEqualCanonicalizing([$this->laravel->id, $this->guide->id]);
});

it('goes back to the live terms when the changes are discarded', function () {
    $note = liveNote(['tags' => [$this->php->id]]);
    $saved = app(SaveDraft::class)->handle($note->fresh(), ['terms' => ['tags' => [$this->laravel->id]]]);
    expect($saved->hasUnpublishedChanges())->toBeTrue();

    $discarded = app(DiscardChanges::class)->handle($saved->fresh(), $saved->version);
    expect($discarded->entry->fresh()?->draft_terms)->toBeNull()
        ->and($discarded->fresh()?->hasUnpublishedChanges())->toBeFalse()
        ->and(liveTerms($discarded))->toBe([$this->php->id]);
});

it('keeps the terms with each revision, compares them, and restores them', function () {
    $note = liveNote(['tags' => [$this->php->id]]);
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle($note->fresh(), ['terms' => ['tags' => [$this->laravel->id], 'format' => [$this->guide->id]]]));
    $first = Revision::query()->where('entry_translation_id', $note->id)->orderBy('id')->firstOrFail();

    expect($first->terms)->toBe([$this->php->id]);
    get(entryEditUrl($note->entry_id))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $page) => $page->where('revisions.0.changed', ['Format', 'Tags'])));
    get("/admin/cms/entries/{$note->entry_id}/revisions/{$first->id}/compare")->assertOk()->assertExactJson(['fields' => [
        ['label' => 'Format', 'revision' => '', 'draft' => 'Guide'],
        ['label' => 'Tags', 'revision' => 'PHP', 'draft' => 'Laravel'],
    ]]);

    // Restored: as terms not published yet; undone: the earlier ones back.
    post("/admin/cms/entries/{$note->entry_id}/revisions/{$first->id}/restore")->assertSessionHasNoErrors();
    expect($note->entry->fresh()?->draftTermIds())->toBe([$this->php->id])
        ->and(liveTerms($note))->toEqualCanonicalizing([$this->laravel->id, $this->guide->id]);
    post("/admin/cms/entries/{$note->entry_id}/translations/en/undo-restore")->assertSessionHasNoErrors();
    expect($note->entry->fresh()?->draft_terms)->toBeNull();

    // A revision kept before revisions had terms leaves them alone.
    $first->forceFill(['terms' => null])->save();
    app(RestoreRevision::class)->handle($first->fresh());
    expect($note->entry->fresh()?->draft_terms)->toBeNull();
});

it('creates an entry with its terms from the editor', function () {
    post('/admin/cms/collections/notes/entries', ['title' => 'New', 'terms' => ['tags' => [$this->php->id], 'format' => []]])->assertSessionHasNoErrors();
    $entry = Entry::query()->latest('id')->firstOrFail();

    expect($entry->draft_terms)->toBe([$this->php->id])->and($entry->liveTermIds())->toBe([]);
});

it('gives templates the entry\'s terms, by taxonomy, live or (in a preview) not published yet', function () {
    $note = liveNote(['tags' => [$this->laravel->id, $this->php->id], 'format' => [$this->guide->id]]);
    app(SaveDraft::class)->handle($note->fresh(), ['terms' => ['tags' => [$this->php->id]]]);
    $views = sys_get_temp_dir().'/sunrice-entry-terms-'.uniqid();
    mkdir("{$views}/cms/collections/notes", recursive: true);
    file_put_contents("{$views}/cms/collections/notes/show.blade.php", 'all:@foreach ($entry->terms as $t){{ $t->title() }},@endforeach tags:@foreach ($entry->termsOf(\'tags\') as $t){{ $t->title() }},@endforeach');
    View::addLocation($views);

    // By taxonomy title, then in the editors' order.
    get('/notes/hello')->assertOk()->assertSee('all:Guide,PHP,Laravel, tags:PHP,Laravel,', false);
    $preview = get(URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(5), ['translation' => $note->id]))->assertOk();
    $preview->assertSee('tags:PHP,', false)->assertDontSee('Laravel');
});

it('moves terms fields\' values to the entry\'s own terms on upgrade, leaving the live ones', function () {
    Blueprint::query()->create(['handle' => 'note', 'title' => 'Note', 'fields' => [
        ['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]],
        ['handle' => 'kind', 'type' => 'terms', 'config' => ['taxonomy' => 'format']],
        ['handle' => 'hush', 'type' => 'terms', 'config' => ['taxonomy' => 'secret']],
    ]]);
    $this->notes->update(['blueprint' => 'note']);
    // As 0.1 left them: live terms in the table from the published fields, drafts that chose others.
    $changed = app(CreateEntry::class)->handle($this->notes, ['title' => 'Changed', 'data' => ['tags' => [$this->laravel->id], 'kind' => $this->guide->id, 'hush' => $this->hidden->id]]);
    $same = app(CreateEntry::class)->handle($this->notes, ['title' => 'Same', 'data' => ['tags' => [$this->php->id]]]);
    $never = app(CreateEntry::class)->handle($this->notes, ['title' => 'Never published', 'data' => ['tags' => [$this->php->id]]]);
    DB::table('sunrice_entry_term')->insert([
        ['entry_id' => $changed->entry_id, 'term_id' => $this->php->id], ['entry_id' => $changed->entry_id, 'term_id' => $this->hidden->id],
        ['entry_id' => $same->entry_id, 'term_id' => $this->php->id],
    ]);
    $migration = require dirname(__DIR__, 2).'/packages/cms/database/migrations/2026_10_23_000002_move_terms_fields_to_entry_terms.php';

    $migration->up();
    $migration->up(); // again: nothing changes
    expect(Entry::query()->findOrFail($changed->entry_id)->draft_terms)->toEqualCanonicalizing([$this->laravel->id, $this->guide->id])
        ->and(Entry::query()->findOrFail($same->entry_id)->draft_terms)->toBeNull()
        ->and(Entry::query()->findOrFail($never->entry_id)->draft_terms)->toBe([$this->php->id])
        // The site shows the same: the live terms stay, a taxonomy not attached's too.
        ->and(storedTerms($changed))->toBe(collect([$this->php->id, $this->hidden->id])->sort()->values()->all());

    // Published: the fields' choices are the entry's live terms; the secret one, not attached, stays.
    app(PublishEntry::class)->handle($changed->fresh());
    expect(storedTerms($changed))->toBe(collect([$this->laravel->id, $this->guide->id, $this->hidden->id])->sort()->values()->all());
});

it('carries entries\' terms and attachments\' one term only through the structure, and reads terms fields in older files', function () {
    $note = liveNote(['tags' => [$this->php->id], 'format' => [$this->howto->id]]);
    app(SaveDraft::class)->handle($note->fresh(), ['terms' => ['tags' => [$this->laravel->id]]]);
    app(CreateEntry::class)->handle($this->notes, ['title' => 'Draft', 'terms' => ['tags' => ['php']]]);
    $file = json_decode((string) json_encode(app(StructureExporter::class)->export(content: true)), true);
    $entries = collect($file['entries'])->keyBy('slug');

    expect($entries['hello']['terms'])->toBe(['format' => ['how-to'], 'tags' => ['php']])
        ->and($entries['hello']['draft_terms'])->toBe(['format' => ['how-to'], 'tags' => ['laravel']])
        ->and($entries['draft'])->not->toHaveKey('terms')
        ->and($entries['draft']['draft_terms'])->toBe(['tags' => ['php']])
        ->and(collect($file['taxonomies'])->firstWhere('handle', 'format')['collection_pages']['notes']['single'])->toBeTrue()
        ->and(collect($file['taxonomies'])->firstWhere('handle', 'tags')['collection_pages']['notes'])->not->toHaveKey('single');

    // On a fresh site: the same.
    foreach (['sunrice_entry_term', 'sunrice_references', 'sunrice_revisions', 'sunrice_entry_translations', 'sunrice_entries'] as $table) {
        DB::table($table)->delete();
    }
    DB::table('sunrice_collection_taxonomy')->update(['single' => false]);
    app(StructureImporter::class)->import($file);
    $hello = EntryTranslation::query()->where('slug', 'hello')->firstOrFail()->entry;
    $draft = EntryTranslation::query()->where('draft_slug', 'draft')->firstOrFail()->entry;
    expect(liveTerms($hello))->toEqualCanonicalizing([$this->php->id, $this->howto->id])
        ->and($hello->draftTermIds())->toEqualCanonicalizing([$this->laravel->id, $this->howto->id])
        ->and($draft->draftTermIds())->toBe([$this->php->id])
        ->and($draft->liveTermIds())->toBe([])
        ->and((bool) DB::table('sunrice_collection_taxonomy')->where('taxonomy_id', $this->format->id)->value('single'))->toBeTrue();

    // A file written before: an entry's terms fields stand in for its terms.
    Blueprint::query()->create(['handle' => 'note', 'title' => 'Note', 'fields' => [['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]]]]);
    $this->notes->update(['blueprint' => 'note']);
    app(StructureImporter::class)->import(['entries' => [['collection' => 'notes', 'slug' => 'old', 'translations' => [
        'en' => ['title' => 'Old', 'slug' => 'old', 'published' => true, 'data' => ['topics' => [['term' => 'tags/laravel']]]],
    ]]]]);
    expect(liveTerms(EntryTranslation::query()->where('slug', 'old')->firstOrFail()))->toBe([$this->laravel->id]);
});

it('takes terms through SaveEntryTerms, keeping the taxonomies not given', function () {
    $note = app(CreateEntry::class)->handle($this->notes, ['title' => 'Hello', 'terms' => ['tags' => [$this->php->id], 'format' => [$this->guide->id]]]);
    $entry = $note->entry;

    expect(app(SaveEntryTerms::class)->handle($entry, ['tags' => []]))->toBeTrue()
        ->and($entry->draftTermIds())->toBe([$this->guide->id])
        ->and(app(SaveEntryTerms::class)->handle($entry, ['tags' => []]))->toBeFalse();
    // Back to what's live (nothing): nothing pending.
    app(SaveEntryTerms::class)->handle($entry, ['format' => []]);
    expect($entry->fresh()?->draft_terms)->toBeNull()
        ->and(Term::query()->count())->toBe(5);
});

it('leaves the entry\'s terms alone when a translator discards, restores a revision or undoes a restore', function () {
    $note = liveNote(['tags' => [$this->php->id]]);
    $entryId = $note->entry_id;
    $id = app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($note->entry, 'id'), ['title' => 'Halo']));
    $revision = Revision::query()->where('entry_translation_id', $id->id)->firstOrFail();
    expect($revision->terms)->toBe([$this->php->id]);
    // An editor chose other terms, not published yet.
    app(SaveEntryTerms::class)->handle($note->entry->fresh(), ['tags' => [$this->laravel->id]]);
    $pending = fn (): ?array => Entry::query()->findOrFail($entryId)->draft_terms;
    $translator = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate', 'sunrice.ai.connect');
    actingAs($translator);

    // Discarding the Indonesian changes: its text goes back, the terms stay.
    app(SaveDraft::class)->handle($id->fresh(), ['title' => 'Halo lagi']);
    post("/admin/cms/entries/{$entryId}/translations/id/discard", ['version' => $id->fresh()?->version])->assertSessionHasNoErrors();
    expect($id->fresh()?->draft_title)->toBe('Halo')->and($pending())->toBe([$this->laravel->id]);

    // Restoring its revision: the text, not the terms it was published with.
    post("/admin/cms/entries/{$entryId}/revisions/{$revision->id}/restore")->assertSessionHasNoErrors();
    expect($pending())->toBe([$this->laravel->id]);
    // An editor changes the terms meanwhile; the translator's undo leaves them.
    app(SaveEntryTerms::class)->handle($note->entry->fresh(), ['tags' => [$this->php->id, $this->laravel->id]]);
    session()->put('sunrice-cms.undo-restore.'.$id->id, ['title' => 'Halo', 'slug' => 'halo', 'data' => [], 'terms' => [$this->laravel->id]]);
    post("/admin/cms/entries/{$entryId}/translations/id/undo-restore")->assertSessionHasNoErrors();
    expect($pending())->toEqualCanonicalizing([$this->php->id, $this->laravel->id]);

    // Through an AI client too.
    $token = AiToken::issue((string) $translator->id, 'Claude')[1];
    $restored = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'cms_entry_revisions', 'arguments' => ['action' => 'restore', 'revision_id' => $revision->id]]], ['Authorization' => "Bearer {$token}"]);
    expect($restored->json('result.content.0.text'))->toContain('"restored":true')
        ->and($pending())->toEqualCanonicalizing([$this->php->id, $this->laravel->id]);

    // Someone who may edit the whole entry: the terms go back with the rest (as before).
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    post("/admin/cms/entries/{$entryId}/revisions/{$revision->id}/restore")->assertSessionHasNoErrors();
    expect($pending())->toBeNull()->and(liveTerms($note))->toBe([$this->php->id]);
});

it('refuses to save terms over those another language\'s editor changed since they were loaded', function () {
    $note = liveNote(['tags' => [$this->php->id]]);
    $entryId = $note->entry_id;
    $id = app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($note->entry, 'id'), ['title' => 'Halo']);
    $draft = fn (): array => Entry::query()->findOrFail($entryId)->draftTermIds();
    // Both editors loaded Tags: PHP. The English one adds Laravel.
    put("/admin/cms/entries/{$entryId}", ['title' => 'Hello', 'version' => $note->fresh()?->version, 'terms' => ['tags' => [$this->php->id, $this->laravel->id]], 'terms_base' => ['tags' => [$this->php->id]]])
        ->assertSessionHasNoErrors();

    // The Indonesian one, opened before, takes PHP out: refused, nothing changes.
    put("/admin/cms/entries/{$entryId}", ['locale' => 'id', 'title' => 'Halo lagi', 'version' => $id->version, 'terms' => ['tags' => []], 'terms_base' => ['tags' => [$this->php->id]]])
        ->assertSessionHasErrors(['terms' => 'Someone else changed this entry\'s terms. Reload to see them.']);
    expect($draft())->toEqualCanonicalizing([$this->php->id, $this->laravel->id])->and($id->fresh()?->draft_title)->toBe('Halo');

    // Another taxonomy, untouched by the other save: fine, and Tags keep the English editor's terms.
    put("/admin/cms/entries/{$entryId}", ['locale' => 'id', 'title' => 'Halo lagi', 'version' => $id->version, 'terms' => ['format' => [$this->guide->id]], 'terms_base' => ['format' => []]])
        ->assertSessionHasNoErrors();
    expect($draft())->toEqualCanonicalizing([$this->php->id, $this->laravel->id, $this->guide->id]);

    // Without what was loaded (code, AI clients): saved as given.
    app(SaveDraft::class)->handle($note->fresh(), ['terms' => ['tags' => []]]);
    expect($draft())->toBe([$this->guide->id]);
});

it('exports entries in as many queries for many entries as for few', function () {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(StructureExporter::class)->export(content: true);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    foreach (['One', 'Two', 'Three'] as $title) {
        liveNote(['tags' => [$this->php->id]], $title);
    }
    $few = $queries();
    foreach (['Four', 'Five', 'Six', 'Seven', 'Eight'] as $title) {
        liveNote(['tags' => [$this->laravel->id]], $title);
    }

    expect($queries())->toBe($few);
});

it('shows the terms of the taxonomies attached to the entry\'s collection only, everywhere the site reads them', function () {
    $note = liveNote(['tags' => [$this->php->id], 'format' => [$this->guide->id]]);
    $this->format->update(['route' => '/format/{slug}']);
    $this->format->collections()->detach($this->notes);
    $views = sys_get_temp_dir().'/sunrice-entry-terms-'.uniqid();
    mkdir("{$views}/cms/collections/notes", recursive: true);
    file_put_contents("{$views}/cms/collections/notes/show.blade.php", 'all:@foreach ($entry->terms as $t){{ $t->title() }},@endforeach format:@foreach ($entry->termsOf(\'format\') as $t){{ $t->title() }},@endforeach');
    View::addLocation($views);

    // Its page, live and in a preview, alike.
    get('/notes/hello')->assertOk()->assertSee('all:PHP, format:', false);
    get(URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(5), ['translation' => $note->id]))->assertOk()->assertSee('all:PHP, format:', false);
    // Queries, counts and the term's page.
    expect(Content::entries('notes')->whereTerm('format', 'guide')->get())->toHaveCount(0)
        ->and(Content::entries('notes')->whereTerm('tags', 'php')->get())->toHaveCount(1)
        ->and(trim(Blade::render('<x-sunrice-cms::terms taxonomy="format">@foreach ($component->terms as $t){{ $t->title() }}({{ $t->entries_count }})@endforeach</x-sunrice-cms::terms>')))->toBe('Guide(0)How-to(0)')
        ->and(trim(Blade::render('<x-sunrice-cms::terms taxonomy="format" include-children>@foreach ($component->terms as $t){{ $t->title() }}({{ $t->entries_count }})@endforeach</x-sunrice-cms::terms>')))->toBe('Guide(0)How-to(0)')
        ->and($this->guide->entries()->count())->toBe(0);
    get('/format/guide')->assertOk()->assertDontSee('Hello');

    // Attached again: back everywhere.
    $this->format->collections()->attach($this->notes);
    get('/notes/hello')->assertOk()->assertSee('all:Guide,PHP, format:Guide,', false);
    expect(Content::entries('notes')->whereTerm('format', 'guide')->get())->toHaveCount(1)
        ->and($this->guide->entries()->count())->toBe(1);
});

it('puts the terms of an entry published for later on the site when its time comes', function () {
    $this->travelTo(now()->setTime(9, 0));
    $this->artisan('sunrice:publish-scheduled')->assertSuccessful();
    $note = liveNote(['tags' => [$this->php->id]]);
    $saved = app(SaveDraft::class)->handle($note->fresh(), ['terms' => ['tags' => [$this->laravel->id]]]);

    app(PublishEntry::class)->handle($saved->fresh(), now()->addMinutes(30));
    expect(liveTerms($note))->toBe([$this->php->id])
        ->and($note->entry->fresh()?->draftTermIds())->toBe([$this->laravel->id])
        // The revision keeps the terms it was published with.
        ->and(Revision::query()->where('entry_translation_id', $note->id)->latest('id')->firstOrFail()->terms)->toBe([$this->laravel->id]);

    $this->travelTo(now()->addMinutes(31));
    $this->artisan('sunrice:publish-scheduled')->expectsOutput('1 scheduled entry went live.');
    expect(liveTerms($note))->toBe([$this->laravel->id])->and($note->entry->fresh()?->draft_terms)->toBeNull();

    // Published now: at once.
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle($note->fresh(), ['terms' => ['tags' => [$this->php->id]]]));
    expect(liveTerms($note))->toBe([$this->php->id]);
});

it('shows the entry\'s terms in compare dialogs and to AI clients only of the taxonomies the card shows', function () {
    $note = liveNote(['tags' => [$this->php->id], 'format' => [$this->guide->id]]);
    app(CreateTranslation::class)->handle($note->entry, 'id');
    app(SaveEntryTerms::class)->handle($note->entry->fresh(), ['tags' => [$this->laravel->id], 'format' => [$this->howto->id]]);
    $first = Revision::query()->where('entry_translation_id', $note->id)->firstOrFail();
    $mcp = function (EntryTranslation $note): array {
        $token = AiToken::issue((string) auth()->id(), 'Claude')[1];

        return json_decode((string) $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'cms_get_entry', 'arguments' => ['id' => $note->entry_id]]], ['Authorization' => "Bearer {$token}"])->json('result.content.0.text'), true);
    };

    // A translator who may see Tags' terms: Tags only.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate', 'cms.terms.tags.view', 'sunrice.ai.connect'));
    get("/admin/cms/entries/{$note->entry_id}/translations/en/compare-live")->assertOk()->assertExactJson(['fields' => [['label' => 'Tags', 'live' => 'PHP', 'draft' => 'Laravel']]]);
    get("/admin/cms/entries/{$note->entry_id}/revisions/{$first->id}/compare")->assertOk()->assertExactJson(['fields' => [['label' => 'Tags', 'revision' => 'PHP', 'draft' => 'Laravel']]]);
    get(entryEditUrl($note->entry_id))->assertInertia(fn (Assert $page) => $page->where('entry.live.changed', ['terms.tags']));
    $entry = $mcp($note);
    expect(array_column($entry['taxonomies'], 'handle'))->toBe(['tags'])
        ->and(array_keys($entry['terms']))->toBe(['tags'])->and(array_keys($entry['live_terms']))->toBe(['tags']);

    // Without any terms permission: none.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate', 'sunrice.ai.connect'));
    get("/admin/cms/entries/{$note->entry_id}/translations/en/compare-live")->assertOk()->assertExactJson(['fields' => []]);
    expect($mcp($note))->toMatchArray(['taxonomies' => [], 'terms' => [], 'live_terms' => []]);

    // Who may change the entry's terms: every taxonomy.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'sunrice.ai.connect'));
    expect(array_keys($mcp($note)['terms']))->toBe(['format', 'tags']);
});

it('attaches on upgrade the taxonomies terms fields use, without pages, keeping the site as it was and the draft terms pending', function () {
    // As 0.1 left a site: a terms field of a taxonomy not attached to the collection, live terms from the published field, a draft choosing another.
    $regions = Taxonomy::query()->create(['handle' => 'regions', 'title' => 'Regions']);
    $bali = app(SaveTerm::class)->handle($regions, ['title' => 'Bali']);
    $java = app(SaveTerm::class)->handle($regions, ['title' => 'Java']);
    Blueprint::query()->create(['handle' => 'release', 'title' => 'Release', 'fields' => [
        ['handle' => 'region', 'type' => 'terms', 'config' => ['taxonomy' => 'regions']],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]],
    ]]);
    $press = Collection::query()->create(['handle' => 'press', 'title' => 'Press', 'blueprint' => 'release', 'route' => '/press/{slug}']);
    $changed = app(CreateEntry::class)->handle($press, ['title' => 'News', 'data' => ['region' => $java->id, 'topics' => [$this->php->id]]]);
    $same = app(CreateEntry::class)->handle($press, ['title' => 'Same', 'data' => ['region' => $bali->id]]);
    foreach ([[$changed, $bali->id], [$same, $bali->id]] as [$translation, $live]) {
        $translation->forceFill(['status' => EntryTranslation::PUBLISHED, 'title' => $translation->draft_title, 'slug' => $translation->draft_slug,
            'data' => ['region' => $live], 'published_at' => now()->subDay()])->save();
        DB::table('sunrice_entry_term')->insert(['entry_id' => $translation->entry_id, 'term_id' => $live]);
    }
    $views = sys_get_temp_dir().'/sunrice-entry-terms-'.uniqid();
    mkdir("{$views}/cms/collections/press", recursive: true);
    file_put_contents("{$views}/cms/collections/press/show.blade.php", 'terms:@foreach ($entry->terms as $t){{ $t->title() }},@endforeach');
    View::addLocation($views);

    $migrations = dirname(__DIR__, 2).'/packages/cms/database/migrations';
    (require "{$migrations}/2026_10_23_000002_move_terms_fields_to_entry_terms.php")->up();
    $attach = require "{$migrations}/2026_10_24_000001_attach_taxonomies_from_terms_fields.php";
    $attach->up();
    $attach->up(); // again: nothing changes

    // Attached, without pages; one term only as the field took; Tags too (used by a field), once.
    $pivot = fn (Taxonomy $taxonomy) => DB::table('sunrice_collection_taxonomy')->where('collection_id', $press->id)->where('taxonomy_id', $taxonomy->id)->get();
    expect($pivot($regions))->toHaveCount(1)
        ->and((array) $pivot($regions)->first())->toMatchArray(['term_pages' => false, 'index_page' => false, 'single' => true])
        ->and((bool) $pivot($this->tags)->first()?->single)->toBeFalse()
        ->and(DB::table('sunrice_collection_taxonomy')->where('collection_id', $this->notes->id)->count())->toBe(2);
    // The site as it was; the draft's choice pending.
    get('/press/news')->assertOk()->assertSee('terms:Bali,', false);
    $entry = Entry::query()->findOrFail($changed->entry_id);
    expect($entry->liveTermIds())->toBe([$bali->id])
        ->and($entry->draftTermIds())->toEqualCanonicalizing([$java->id, $this->php->id])
        ->and(Entry::query()->findOrFail($same->entry_id)->draft_terms)->toBeNull();
    // The card shows them, to change or publish.
    get(entryEditUrl($changed->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('values.terms', ['regions' => [$java->id], 'tags' => [$this->php->id]]));
    app(PublishEntry::class)->handle($changed->fresh());
    expect(liveTerms($changed))->toEqualCanonicalizing([$java->id, $this->php->id]);
});
