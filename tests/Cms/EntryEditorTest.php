<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\UnpublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Comment;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Settings\SiteSettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea', 'required' => true],
        ['handle' => 'featured', 'type' => 'toggle'],
    ]]);
    $this->articles = Collection::query()->create(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article', 'dated' => true]);
});

/** Signed in with the panel and these entry permissions ("view", "publish" …). */
function editor(string ...$abilities): void
{
    actingAs(adminUser(Panel::ACCESS, ...array_map(fn ($a) => "cms.entries.{$a}", $abilities)));
}

function draft(string $title = 'Hello', array $data = ['intro' => 'Hi']): EntryTranslation
{
    return app(CreateEntry::class)->handle(test()->articles, ['title' => $title, 'data' => $data]);
}

it('guards every entry route by its permission', function () {
    $entry = draft()->entry;
    editor('view');

    get('/admin/cms/collections/articles/entries')->assertOk();
    get(entryEditUrl($entry->id))->assertOk();
    get('/admin/cms/collections/articles/entries/create')->assertForbidden();
    post('/admin/cms/collections/articles/entries', ['title' => 'X'])->assertForbidden();
    put("/admin/cms/entries/{$entry->id}", ['title' => 'X'])->assertForbidden();
    post("/admin/cms/entries/{$entry->id}/publish")->assertForbidden();
    post("/admin/cms/entries/{$entry->id}/unpublish")->assertForbidden();
    delete("/admin/cms/entries/{$entry->id}")->assertForbidden();
    post("/admin/cms/entries/{$entry->id}/restore")->assertForbidden();

    actingAs(adminUser(Panel::ACCESS));
    get('/admin/cms/entries')->assertForbidden();
    get('/admin/cms/collections/articles/entries')->assertForbidden();
});

it('tells the editor the slug visitors have known, so a new one gets a note, and where to add a redirect', function () {
    $this->articles->forceFill(['route' => '/articles/{slug}'])->save();
    $translation = draft('Hello');
    $slug = fn (Assert $page) => $page->toArray()['props']['entry']['publishedSlug'];
    editor('view', 'update');

    // Never published: no note.
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => expect($slug($page))->toBeNull()->and($page->toArray()['props']['redirectsUrl'])->toBeNull());
    // Published (publishing again redirects from it), and still once taken off the site (nothing redirects then).
    app(PublishEntry::class)->handle($translation);
    app(SaveDraft::class)->handle($translation->fresh(), ['slug' => 'hello-again', 'version' => $translation->fresh()?->version]);
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => expect($slug($page))->toBe('hello')->and($page->toArray()['props']['values']['slug'])->toBe('hello-again'));
    app(UnpublishEntry::class)->handle($translation->fresh());
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => expect($slug($page))->toBe('hello')->and($page->toArray()['props']['entry']['status'])->toBe('draft'));

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.redirects.view', 'cms.redirects.create'));
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => expect($page->toArray()['props']['redirectsUrl'])->toBe(route('sunrice.admin.cms.redirects.create')));

    // The homepage stays at /, and a collection without pages has no address: no note.
    $settings = app(SiteSettings::class);
    $settings->homepage = $translation->entry_id;
    $settings->save();
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => expect($slug($page))->toBeNull());
    $settings->homepage = null;
    $settings->save();
    $this->articles->forceFill(['route' => null])->save();
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => expect($slug($page))->toBeNull());
});

it('opens the first collection from the sidebar', function () {
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    editor('view');

    get('/admin/cms/entries')->assertRedirect('/admin/cms/collections/articles/entries');
});

it('lists entries, newest first in a dated collection, with search and the trash', function () {
    travelTo('2026-10-01');
    app(PublishEntry::class)->handle(draft('Older'));
    travelTo('2026-10-10');
    app(PublishEntry::class)->handle(draft('Newer'));
    $trashed = draft('Gone');
    $trashed->entry->delete();
    editor('view', 'create');

    get('/admin/cms/collections/articles/entries')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Entries/Index', shouldExist: false)
        ->where('collection.title', 'Articles')
        ->where('entries.total', 2)
        ->where('entries.data.0.title', 'Newer')
        ->where('entries.data.0.status', 'published')
        ->where('can.create', true)
        ->where('can.publish', false));
    get('/admin/cms/collections/articles/entries?search=old')->assertInertia(fn (Assert $page) => $page->where('entries.total', 1));
    get('/admin/cms/collections/articles/entries?search=OLDER')->assertInertia(fn (Assert $page) => $page->where('entries.total', 1));
    get('/admin/cms/collections/articles/entries?search=%25')->assertInertia(fn (Assert $page) => $page->where('entries.total', 0));
    get('/admin/cms/collections/articles/entries?status=trashed')->assertInertia(fn (Assert $page) => $page
        ->where('entries.total', 1)->where('entries.data.0.title', 'Gone'));
});

it('creates an entry from the editor', function () {
    editor('view', 'create');

    get('/admin/cms/collections/articles/entries/create')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Entries/Edit', shouldExist: false)
        ->where('entry', null)
        ->where('values.data', ['intro' => null, 'featured' => false])
        ->where('fields.0.handle', 'intro'));

    post('/admin/cms/collections/articles/entries', ['title' => 'Hello', 'data' => ['intro' => '']])->assertSessionHasErrors('data.intro');
    post('/admin/cms/collections/articles/entries', ['title' => 'Hello', 'data' => ['intro' => 'Hi']])
        ->assertRedirect('/admin/cms/collections/articles/entries/1/edit');

    expect(EntryTranslation::query()->firstOrFail()->only(['draft_title', 'draft_slug', 'status']))
        ->toBe(['draft_title' => 'Hello', 'draft_slug' => 'hello', 'status' => 'draft']);
});

it('opens an entry with its draft, status and revisions', function () {
    $translation = app(PublishEntry::class)->handle(draft());
    app(SaveDraft::class)->handle($translation, ['title' => 'Hello again']);
    editor('view');
    config(['inertia.testing.ensure_pages_exist' => false]); // the deferred reload looks for a page file: the CMS's are bundled

    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('entry.status', 'published')
        ->where('entry.hasUnpublishedChanges', true)
        ->where('entry.version', 3)
        ->where('values.title', 'Hello again')
        ->missing('revisions')
        ->where('can.update', false)
        // The sidebar's revisions load after the page shows.
        ->loadDeferredProps(fn (Assert $reload) => $reload->where('revisions.0.title', 'Hello')));
});

it('opens an entry whose blocks may hold themselves', function () {
    Fieldset::query()->create(['handle' => 'section', 'title' => 'Section', 'fields' => [['handle' => 'parts', 'type' => 'flexible', 'config' => ['blocks' => ['section']]]]]);
    Blueprint::query()->where('handle', 'article')->update(['fields' => json_encode([['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['section']]]])]);
    $translation = draft(data: ['sections' => [['type' => 'section', 'values' => ['parts' => [['type' => 'section', 'values' => []]]]]]]);
    editor('view');

    get(entryEditUrl($translation->entry_id))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('fields.0.blocks.0.fields.0.blocks.0.label', 'Section')
        ->where('values.data.sections.0.values.parts.0.type', 'section'));
});

it('saves the draft, refusing a stale version', function () {
    $translation = draft();
    editor('view', 'update');

    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Changed', 'data' => ['intro' => 'New'], 'version' => 1])->assertSessionHasNoErrors();
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Mine', 'version' => 1])->assertSessionHasErrors('version');

    expect($translation->fresh()?->only(['draft_title', 'status']))->toBe(['draft_title' => 'Changed', 'status' => 'draft']);
});

it('saves and publishes in one go only with the publish permission', function () {
    $translation = draft();
    editor('view', 'update');

    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Mine', 'publish' => true])->assertForbidden();
    expect($translation->fresh()?->draft_title)->toBe('Hello');

    editor('view', 'update', 'publish');
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Mine', 'publish' => true])->assertSessionHas('success', 'Published.');
    expect($translation->fresh()?->title)->toBe('Mine');
});

it('publishes on a date, and unpublishes', function () {
    travelTo('2026-10-19 09:00');
    $translation = draft();
    editor('view', 'publish');

    post("/admin/cms/entries/{$translation->entry_id}/publish", ['published_at' => '2026-10-25 20:00'])->assertSessionHas('success', 'Scheduled for Oct 25, 2026, 20:00.');
    expect($translation->fresh()?->isPublished())->toBeTrue()->and($translation->fresh()?->isLive())->toBeFalse();

    post("/admin/cms/entries/{$translation->entry_id}/publish", ['published_at' => 'whenever'])->assertSessionHasErrors('published_at');
    // Never live: unscheduled, not "taken off the site".
    post("/admin/cms/entries/{$translation->entry_id}/unpublish")->assertSessionHasNoErrors()->assertSessionHas('success', 'Unscheduled. It\'s a draft again.');
    expect($translation->fresh()?->status)->toBe('draft');

    // Unscheduled, then published: it goes live now, not on the date it was once scheduled for.
    post("/admin/cms/entries/{$translation->entry_id}/publish")->assertSessionHas('success', 'Published.');
    expect($translation->fresh()?->published_at?->toDateTimeString())->toBe('2026-10-19 09:00:00');
    // Published before, unpublished, published again: it keeps its first date.
    travelTo('2026-10-20 09:00');
    post("/admin/cms/entries/{$translation->entry_id}/unpublish")->assertSessionHas('success', 'Taken off the site. The draft is kept.');
    post("/admin/cms/entries/{$translation->entry_id}/publish");
    expect($translation->fresh()?->published_at?->toDateTimeString())->toBe('2026-10-19 09:00:00');

    // A live entry's date changes, back in time too, and it stays live.
    post("/admin/cms/entries/{$translation->entry_id}/publish", ['published_at' => '2026-10-19 08:00'])->assertSessionHas('success', 'Published.');
    post("/admin/cms/entries/{$translation->entry_id}/publish", ['published_at' => '2026-01-02 10:30'])->assertSessionHas('success', 'Published.');
    expect($translation->fresh()?->published_at?->format('Y-m-d H:i'))->toBe('2026-01-02 10:30')->and($translation->fresh()?->isLive())->toBeTrue();
});

it('trashes and restores', function () {
    $entry = draft()->entry;
    editor('view', 'delete');

    delete("/admin/cms/entries/{$entry->id}")->assertRedirect('/admin/cms/collections/articles/entries');
    expect(Entry::withTrashed()->find($entry->id)?->trashed())->toBeTrue();
    get(entryEditUrl($entry->id))->assertInertia(fn (Assert $page) => $page->where('entry.trashed', true));

    post("/admin/cms/entries/{$entry->id}/restore")->assertSessionHasNoErrors();
    expect($entry->fresh()?->trashed())->toBeFalse();
});

it('restores a revision into the draft', function () {
    $translation = app(PublishEntry::class)->handle(draft());
    app(SaveDraft::class)->handle($translation, ['title' => 'Changed']);
    editor('view', 'update');

    post("/admin/cms/entries/{$translation->entry_id}/revisions/".$translation->revisions()->firstOrFail()->id.'/restore')->assertSessionHasNoErrors();

    expect($translation->fresh()?->draft_title)->toBe('Hello');
});

it('hides entries of a deleted collection', function () {
    $entry = draft()->entry;
    $this->articles->delete();
    editor('view');

    get('/admin/cms/collections/articles/entries')->assertNotFound();
    get(entryEditUrl($entry->id))->assertNotFound();
});

it('shows the entry\'s comments in the editor, to those who may see them', function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->articles->update(['comments' => true]);
    $entry = draft()->entry;
    $other = draft('Other')->entry;
    foreach (['pending', 'approved', 'approved', 'spam'] as $i => $status) {
        Comment::query()->create(['entry_id' => $entry->id, 'author_name' => "Guest {$i}", 'body' => "Comment {$i}", 'status' => $status, 'locale' => 'en']);
    }
    Comment::query()->create(['entry_id' => $other->id, 'author_name' => 'Elsewhere', 'body' => 'Not here', 'status' => 'pending', 'locale' => 'en']);

    editor('view');
    get(entryEditUrl($entry->id))->assertInertia(fn (Assert $page) => $page->where('comments', null));

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.comments.view'));
    get(entryEditUrl($entry->id))->assertInertia(fn (Assert $page) => $page
        ->missing('comments')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('comments.counts', ['pending' => 1, 'approved' => 2, 'spam' => 1])
            ->has('comments.latest', 3)
            ->where('comments.latest.0.excerpt', 'Comment 2')
            ->where('comments.canModerate', false)));

    // Not when the collection takes no comments.
    $this->articles->update(['comments' => false]);
    get(entryEditUrl($entry->id))->assertInertia(fn (Assert $page) => $page->where('comments', null));
    // The comments page, filtered to the entry.
    actingAs(adminUser(Panel::ACCESS, 'cms.comments.view'));
    get("/admin/cms/comments?entry={$entry->id}")->assertInertia(fn (Assert $page) => $page->where('filters.entry', $entry->id)->has('comments.data', 3));
});

it('sends terms fields their taxonomy and the chosen terms\' titles, and the picker the terms', function () {
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'hierarchical' => true]);
    $news = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    $local = app(SaveTerm::class)->handle($topics, ['title' => 'Local', 'parent_id' => $news->id]);
    $sport = app(SaveTerm::class)->handle($topics, ['title' => 'Sport']);
    Taxonomy::query()->create(['handle' => 'other', 'title' => 'Other']);
    Blueprint::query()->where('handle', 'article')->update(['fields' => json_encode([['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]]])]);
    $entry = draft(data: ['topics' => [$local->id]])->entry;
    editor('view');

    get(entryEditUrl($entry->id))->assertInertia(fn (Assert $page) => $page
        ->where('fields.0.taxonomy', 'topics')->missing('fields.0.terms')
        ->where('termLabels', [(string) $local->id => 'Local']));

    $this->getJson('/admin/cms/terms/picker?taxonomy=topics')->assertOk()->assertExactJson(['items' => [
        ['id' => $news->id, 'label' => 'News', 'depth' => 0], ['id' => $local->id, 'label' => 'Local', 'depth' => 1], ['id' => $sport->id, 'label' => 'Sport', 'depth' => 0],
    ], 'more' => false]);
    $this->getJson('/admin/cms/terms/picker?taxonomy=topics&q=spo')->assertJsonPath('items', [['id' => $sport->id, 'label' => 'Sport', 'depth' => 0]]);
    // A taxonomy no field of theirs uses, and whose terms they may not see.
    $this->getJson("/admin/cms/terms/picker?taxonomy=other&ids[]={$sport->id}")->assertForbidden();
    $this->getJson("/admin/cms/terms/picker?taxonomy=topics&ids[]={$sport->id}")->assertJsonPath('items.0.label', 'Sport');
    $this->getJson('/admin/cms/terms/picker?taxonomy=nope')->assertNotFound();
    $this->getJson('/admin/cms/terms/picker')->assertUnprocessable();

    actingAs(adminUser(Panel::ACCESS));
    $this->getJson('/admin/cms/terms/picker?taxonomy=topics')->assertForbidden();
});

it('gives the term picker only taxonomies the person may see or their collections\' terms fields use', function () {
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    $sport = app(SaveTerm::class)->handle($topics, ['title' => 'Sport']);
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    app(SaveTerm::class)->handle($tags, ['title' => 'Green']);
    $salaries = Taxonomy::query()->create(['handle' => 'salaries', 'title' => 'Salaries']);
    $secret = app(SaveTerm::class)->handle($salaries, ['title' => 'Director pay band']);
    Blueprint::query()->where('handle', 'article')->update(['fields' => json_encode([
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics']],
        ['handle' => 'more', 'type' => 'group', 'fields' => [['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags']]]],
    ])]);
    Blueprint::query()->create(['handle' => 'memo', 'title' => 'Memo', 'fields' => [['handle' => 'band', 'type' => 'terms', 'config' => ['taxonomy' => 'salaries']]]]);
    Collection::query()->create(['handle' => 'memos', 'title' => 'Memos', 'blueprint' => 'memo']);

    // An author of articles, who may not see terms on their own.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.articles.view', 'cms.entries.articles.create', 'cms.entries.update-own'));
    $this->getJson('/admin/cms/terms/picker?taxonomy=topics')->assertOk()->assertJsonPath('items.0.label', 'Sport');
    $this->getJson('/admin/cms/terms/picker?taxonomy=tags')->assertOk()->assertJsonPath('items.0.label', 'Green');
    $this->getJson('/admin/cms/terms/picker?taxonomy=salaries')->assertForbidden();
    $this->getJson("/admin/cms/terms/picker?taxonomy=salaries&ids[]={$secret->id}")->assertForbidden();
    $this->getJson("/admin/cms/terms/picker?taxonomy=topics&ids[]={$sport->id}")->assertOk();

    // Allowed to see a taxonomy's terms (all, or that one).
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.articles.view', 'cms.terms.salaries.view'));
    $this->getJson('/admin/cms/terms/picker?taxonomy=salaries')->assertOk()->assertJsonPath('items.0.label', 'Director pay band');
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.articles.view', 'cms.terms.view'));
    $this->getJson('/admin/cms/terms/picker?taxonomy=salaries')->assertOk();
});

it('keeps the editor to a query budget', function () {
    Blueprint::query()->where('handle', 'article')->update(['fields' => json_encode([
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
        ['handle' => 'related', 'type' => 'entries', 'config' => ['multiple' => true]],
        ['handle' => 'rows', 'type' => 'repeater', 'fields' => [['handle' => 'tag', 'type' => 'terms', 'config' => ['taxonomy' => 'topics']]]],
    ])]);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    $terms = array_map(fn (int $i) => app(SaveTerm::class)->handle($topics, ['title' => "Topic {$i}"])->id, range(1, 30));
    $translation = draft(data: ['topics' => array_slice($terms, 0, 10), 'rows' => array_map(fn (int $id) => ['tag' => $id], array_slice($terms, 10, 10))]);
    foreach (range(1, 5) as $i) {
        app(PublishEntry::class)->handle(app(SaveDraft::class)->handle($translation->fresh(), ['title' => "Version {$i}"]));
    }
    editor('view', 'update');
    $url = entryEditUrl($translation->entry_id);
    get($url)->assertOk(); // warms the caches

    DB::flushQueryLog();
    DB::enableQueryLog();
    get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->has('termLabels', 20));
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // However many terms, revisions and rows: no queries in loops, and the term options aren't in the page.
    expect($queries)->toBeLessThanOrEqual(10);
});

it('changes a published entry\'s date as main does: publishing what\'s in the editor with that date', function () {
    travelTo('2026-10-19 09:00');
    $translation = app(PublishEntry::class)->handle(draft('Hello'));
    editor('view', 'update', 'publish');

    // The Status card's Publish date: the form saved and published with the date chosen (no endpoint of its own).
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Hello again', 'data' => ['intro' => 'Hi'], 'publish' => true, 'published_at' => '2026-09-01 08:00'])->assertSessionHasNoErrors();
    $fresh = $translation->fresh();
    expect($fresh?->published_at?->toDateTimeString())->toBe('2026-09-01 08:00:00')
        ->and($fresh?->isLive())->toBeTrue()->and($fresh?->title)->toBe('Hello again')->and($fresh?->hasUnpublishedChanges())->toBeFalse();
    // A date to come: scheduled.
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Hello again', 'data' => ['intro' => 'Hi'], 'publish' => true, 'published_at' => '2026-11-01 08:00'])->assertSessionHasNoErrors();
    expect($translation->fresh()?->status())->toBe('scheduled');

    expect(app('router')->has('sunrice.admin.cms.entries.date'))->toBeFalse();
    post("/admin/cms/entries/{$translation->entry_id}/date", ['published_at' => '2026-09-01 08:00'])->assertNotFound();
});

it('shows who published each revision, what changed, and compares one with the draft', function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $ayu = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.entries.publish');
    $ayu->forceFill(['name' => 'Ayu'])->save();
    actingAs($ayu);
    $translation = app(PublishEntry::class)->handle(draft('Hello', ['intro' => 'Hi']));
    $translation = app(SaveDraft::class)->handle($translation->fresh(), ['title' => 'Hello there', 'data' => ['intro' => 'Hi', 'featured' => true]]);
    app(PublishEntry::class)->handle($translation);
    app(SaveDraft::class)->handle($translation->fresh(), ['data' => ['intro' => 'Hello everyone', 'featured' => true]]);

    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('revisions.0.title', 'Hello there')->where('revisions.0.author', 'Ayu')->where('revisions.0.changed', ['Title', 'Featured'])
            ->where('revisions.1.author', 'Ayu')->where('revisions.1.changed', null)));

    $revision = $translation->revisions()->first();
    get("/admin/cms/entries/{$translation->entry_id}/revisions/{$revision?->id}/compare")->assertOk()->assertExactJson(['fields' => [
        ['label' => 'Intro', 'revision' => 'Hi', 'draft' => 'Hello everyone'],
    ]]);

    // Only for those who may see the entry.
    actingAs(adminUser(Panel::ACCESS));
    get("/admin/cms/entries/{$translation->entry_id}/revisions/{$revision?->id}/compare")->assertForbidden();
});

it('checks limits on the server, where the editor shows them inline (the form has noValidate)', function () {
    Blueprint::query()->create(['handle' => 'property', 'title' => 'Property', 'fields' => [
        ['handle' => 'summary', 'type' => 'textarea', 'config' => ['max' => 300]],
        ['handle' => 'rating', 'type' => 'number', 'config' => ['min' => 1, 'max' => 5, 'step' => 1]],
        ['handle' => 'price', 'type' => 'number', 'config' => ['min' => 0]],
    ]]);
    $properties = Collection::query()->create(['handle' => 'properties', 'title' => 'Properties', 'blueprint' => 'property']);
    $translation = app(CreateEntry::class)->handle($properties, ['title' => 'Villa']);
    editor('view', 'update');

    put("/admin/cms/entries/{$translation->entry_id}", ['title' => '', 'slug' => 'villa', 'data' => [
        'summary' => str_repeat('x', 371), 'rating' => 6, 'price' => -1,
    ]])->assertSessionHasErrors(['title', 'data.summary', 'data.rating', 'data.price']);
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Villa', 'slug' => 'villa', 'data' => ['rating' => 2.5]])
        ->assertSessionHasErrors(['data.rating']);
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Villa', 'slug' => 'villa', 'data' => ['summary' => str_repeat('x', 300), 'rating' => 5, 'price' => 0]])
        ->assertSessionHasNoErrors();
});

it('tells the editor whether the collection has pages, so Preview shows only where there is one', function () {
    $testimonials = Collection::query()->create(['handle' => 'testimonials', 'title' => 'Testimonials']);
    $quote = app(CreateEntry::class)->handle($testimonials, ['title' => 'Great agents']);
    $post = app(CreateEntry::class)->handle(Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'route' => '/posts/{slug}']), ['title' => 'Hello']);
    editor('view', 'update');

    get(entryEditUrl($quote->entry_id))->assertInertia(fn (Assert $page) => $page->where('collection.pages', false));
    get(entryEditUrl($post->entry_id))->assertInertia(fn (Assert $page) => $page->where('collection.pages', true));
});

it('tells the editor which template files are tried for the page, and which exist', function () {
    $this->articles->update(['template' => 'sunrice-cms::components.seo']);
    $translation = draft();
    $translation->entry->update(['template' => 'cms.special']);
    editor('view', 'update');

    get("/admin/cms/collections/articles/entries/{$translation->entry_id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('templates.own', ['view' => 'cms.special', 'exists' => false])
        ->where('templates.fallbacks', [
            ['view' => 'sunrice-cms::components.seo', 'exists' => true],
            ['view' => 'cms.articles', 'exists' => false],
            ['view' => 'cms.entry', 'exists' => false],
        ]));
});

it('compares SEO and structured values in words, setting by setting, never as JSON', function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    Storage::fake('public');
    config(['sunrice-admin.media.queue' => false]);
    $old = app(UploadMedia::class)->handle(UploadedFile::fake()->image('old-share.jpg', 20, 20));
    $new = app(UploadMedia::class)->handle(UploadedFile::fake()->image('new-share.jpg', 20, 20));
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    $news = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    $tips = app(SaveTerm::class)->handle($topics, ['title' => 'Tips']);
    Blueprint::query()->where('handle', 'article')->update(['fields' => [
        ['handle' => 'intro', 'type' => 'textarea', 'required' => true],
        ['handle' => 'more', 'type' => 'link', 'label' => 'Read more'],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
        ['handle' => 'photos', 'type' => 'media', 'config' => ['multiple' => true]],
        ['handle' => 'facts', 'type' => 'repeater', 'fields' => [['handle' => 'label', 'type' => 'text']]],
    ]]);
    editor('view', 'update', 'publish');
    $translation = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->articles, ['title' => 'Hello', 'data' => [
        'intro' => 'Hi', 'more' => ['type' => 'url', 'url' => 'https://example.com/a'], 'topics' => [$news->id], 'photos' => [$old->id],
        'facts' => [['_id' => 'r1', 'label' => 'Sunny']],
    ], 'seo' => ['title' => 'Old title', 'image' => $old->id]]));
    app(SaveDraft::class)->handle($translation->fresh(), ['data' => [
        'intro' => 'Hi', 'more' => ['type' => 'url', 'url' => 'https://example.com/b'], 'topics' => [$news->id, $tips->id], 'photos' => [$new->id],
        'facts' => [['_id' => 'r1', 'label' => 'Sunny'], ['_id' => 'r2', 'label' => 'Quiet']],
    ], 'seo' => ['title' => 'New title', 'description' => null, 'image' => $new->id, 'noindex' => true]]);

    $revision = $translation->revisions()->first();
    $fields = get("/admin/cms/entries/{$translation->entry_id}/revisions/{$revision?->id}/compare")->assertOk()->json('fields');

    expect($fields)->toBe([
        ['label' => 'Read more', 'revision' => 'https://example.com/a', 'draft' => 'https://example.com/b'],
        ['label' => 'Topics', 'revision' => 'News', 'draft' => 'News, Tips'],
        ['label' => 'Photos', 'revision' => 'old-share.jpg', 'draft' => 'new-share.jpg'],
        ['label' => 'Facts', 'revision' => 'Label: Sunny', 'draft' => "Label: Sunny\nLabel: Quiet"],
        ['label' => 'Meta title', 'revision' => 'Old title', 'draft' => 'New title'],
        ['label' => 'Open Graph image', 'revision' => 'old-share.jpg', 'draft' => 'new-share.jpg'],
        ['label' => 'Hide from search engines (noindex)', 'revision' => 'No', 'draft' => 'Yes'],
    ]);

    // Each revision's changes name SEO settings the same way.
    app(PublishEntry::class)->handle($translation->fresh());
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload->where('revisions.0.changed', ['Read more', 'Topics', 'Photos', 'Facts', 'Meta title', 'Open Graph image', 'Hide from search engines (noindex)'])));
});
