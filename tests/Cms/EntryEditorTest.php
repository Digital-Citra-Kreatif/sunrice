<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;

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
    get("/admin/cms/entries/{$entry->id}/edit")->assertOk();
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
    get('/admin/cms/collections/articles/entries?search=%25')->assertInertia(fn (Assert $page) => $page->where('entries.total', 0));
    get('/admin/cms/collections/articles/entries?trashed=1')->assertInertia(fn (Assert $page) => $page
        ->where('entries.total', 1)->where('entries.data.0.title', 'Gone'));
});

it('creates an entry from the editor', function () {
    editor('view', 'create');

    get('/admin/cms/collections/articles/entries/create')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Entries/Editor', shouldExist: false)
        ->where('entry', null)
        ->where('values.data', ['intro' => null, 'featured' => false])
        ->where('fields.0.handle', 'intro'));

    post('/admin/cms/collections/articles/entries', ['title' => 'Hello', 'data' => ['intro' => '']])->assertSessionHasErrors('data.intro');
    post('/admin/cms/collections/articles/entries', ['title' => 'Hello', 'data' => ['intro' => 'Hi']])
        ->assertRedirect('/admin/cms/entries/1/edit');

    expect(EntryTranslation::query()->firstOrFail()->only(['draft_title', 'draft_slug', 'status']))
        ->toBe(['draft_title' => 'Hello', 'draft_slug' => 'hello', 'status' => 'draft']);
});

it('opens an entry with its draft, status and revisions', function () {
    $translation = app(PublishEntry::class)->handle(draft());
    app(SaveDraft::class)->handle($translation, ['title' => 'Hello again']);
    editor('view');

    get("/admin/cms/entries/{$translation->entry_id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('entry.status', 'published')
        ->where('entry.hasUnpublishedChanges', true)
        ->where('entry.version', 2)
        ->where('values.title', 'Hello again')
        ->where('revisions.0.title', 'Hello')
        ->where('can.update', false));
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

    post("/admin/cms/entries/{$translation->entry_id}/publish", ['published_at' => '2026-10-25 08:00'])->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Scheduled for'));
    expect($translation->fresh()?->isPublished())->toBeTrue()->and($translation->fresh()?->isLive())->toBeFalse();

    post("/admin/cms/entries/{$translation->entry_id}/publish", ['published_at' => 'whenever'])->assertSessionHasErrors('published_at');
    post("/admin/cms/entries/{$translation->entry_id}/unpublish")->assertSessionHasNoErrors();
    expect($translation->fresh()?->status)->toBe('draft');
});

it('trashes and restores', function () {
    $entry = draft()->entry;
    editor('view', 'delete');

    delete("/admin/cms/entries/{$entry->id}")->assertRedirect('/admin/cms/collections/articles/entries');
    expect(Entry::withTrashed()->find($entry->id)?->trashed())->toBeTrue();
    get("/admin/cms/entries/{$entry->id}/edit")->assertInertia(fn (Assert $page) => $page->where('entry.trashed', true));

    post("/admin/cms/entries/{$entry->id}/restore")->assertSessionHasNoErrors();
    expect($entry->fresh()?->trashed())->toBeFalse();
});

it('restores a revision into the draft', function () {
    $translation = app(PublishEntry::class)->handle(draft());
    app(SaveDraft::class)->handle($translation, ['title' => 'Changed']);
    editor('view', 'update');

    post('/admin/cms/revisions/'.$translation->revisions()->firstOrFail()->id.'/restore')->assertSessionHasNoErrors();

    expect($translation->fresh()?->draft_title)->toBe('Hello');
});

it('hides entries of a deleted collection', function () {
    $entry = draft()->entry;
    $this->articles->delete();
    editor('view');

    get('/admin/cms/collections/articles/entries')->assertNotFound();
    get("/admin/cms/entries/{$entry->id}/edit")->assertNotFound();
});
