<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Locks\EditLocks;
use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\GlobalSet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea', 'label' => 'Intro', 'required' => true],
        ['handle' => 'kind', 'type' => 'select', 'config' => ['options' => [['value' => 'news', 'label' => 'News']]]],
    ]]);
    $this->articles = Collection::query()->create(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article', 'route' => '/articles/{slug}']);
});

function managed(string $title, array $data = ['intro' => 'Hi']): EntryTranslation
{
    return app(CreateEntry::class)->handle(test()->articles, ['title' => $title, 'data' => $data]);
}

function manager(string ...$abilities): void
{
    actingAs(adminUser(Panel::ACCESS, ...array_map(fn ($a) => "cms.entries.{$a}", $abilities)));
}

it('publishes, unpublishes, trashes, restores and deletes in bulk', function () {
    $a = managed('A');
    $b = managed('B');
    $draft = managed('Draft');
    $ids = [$a->entry_id, $b->entry_id];

    manager('view');
    post('/admin/cms/collections/articles/entries/bulk', ['action' => 'publish', 'ids' => $ids])->assertSessionHas('error', 'Nothing was changed.');

    manager('view', 'publish', 'delete');
    post('/admin/cms/collections/articles/entries/bulk', ['action' => 'publish', 'ids' => $ids])->assertSessionHas('success');
    expect(EntryTranslation::query()->live()->pluck('draft_title')->sort()->values()->all())->toBe(['A', 'B']);

    // Drafts aren't unpublished: skipped and counted.
    post('/admin/cms/collections/articles/entries/bulk', ['action' => 'unpublish', 'ids' => [$a->entry_id, $draft->entry_id]])
        ->assertSessionHas('success', 'Done for 1 of 2 entries; the others can\'t be (not allowed, or not ready to publish).');
    expect($a->fresh()?->isPublished())->toBeFalse();

    post('/admin/cms/collections/articles/entries/bulk', ['action' => 'trash', 'ids' => $ids]);
    expect(Entry::query()->pluck('id')->all())->toBe([$draft->entry_id]);
    post('/admin/cms/collections/articles/entries/bulk', ['action' => 'restore', 'ids' => [$a->entry_id]]);
    post('/admin/cms/collections/articles/entries/bulk', ['action' => 'delete', 'ids' => [$b->entry_id, $a->entry_id]]);
    expect(Entry::query()->withTrashed()->pluck('id')->sort()->values()->all())->toBe([$a->entry_id, $draft->entry_id]);

    post('/admin/cms/collections/articles/entries/bulk', ['action' => 'explode', 'ids' => $ids])->assertSessionHasErrors('action');
});

it('only acts on the collection\'s own entries', function () {
    $other = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'article']);
    $page = app(CreateEntry::class)->handle($other, ['title' => 'Page', 'data' => ['intro' => 'x']]);
    manager('view', 'delete');

    post('/admin/cms/collections/articles/entries/bulk', ['action' => 'trash', 'ids' => [$page->entry_id]]);
    expect($page->entry->fresh()?->trashed())->toBeFalse();
});

it('exports the collection as CSV', function () {
    app(PublishEntry::class)->handle(managed('=Sum', ['intro' => 'Hello', 'kind' => 'news']));
    managed('Gone')->entry->delete();
    manager('view');
    get('/admin/cms/collections/articles/entries/export')->assertForbidden(); // viewing isn't exporting
    get('/admin/cms/collections/articles/entries')->assertInertia(fn (Assert $page) => $page->where('can.export', false));

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.articles.view', 'cms.entries.articles.export'));
    get('/admin/cms/collections/articles/entries')->assertInertia(fn (Assert $page) => $page->where('can.export', true));
    $csv = get('/admin/cms/collections/articles/entries/export')->assertOk()->streamedContent();

    expect($csv)->toStartWith('ID,Title,Slug,Status,Published,URL,Updated,Author,Intro,Kind')
        ->toContain("'=Sum")->toContain('published')->toContain(url('/articles/sum'))->toContain('News')
        ->not->toContain('Gone');
    expect(Activity::query()->where('action', 'exported')->exists())->toBeTrue();
});

it('duplicates an entry as a draft in every language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $hello = app(PublishEntry::class)->handle(managed('Hello'));
    manager('view', 'create');

    post("/admin/cms/entries/{$hello->entry_id}/duplicate")->assertRedirect();
    post("/admin/cms/entries/{$hello->entry_id}/duplicate");

    $copies = EntryTranslation::query()->where('entry_id', '!=', $hello->entry_id)->orderBy('id')->get();
    expect($copies->pluck('draft_slug')->all())->toBe(['hello-copy', 'hello-copy-2'])
        ->and($copies->pluck('draft_title')->unique()->all())->toBe(['Hello (copy)'])
        ->and($copies->every(fn (EntryTranslation $t) => $t->status === EntryTranslation::DRAFT && $t->published_at === null && $t->draft_data == $hello->draft_data))->toBeTrue();
});

it('deletes for good only from the trash', function () {
    $hello = managed('Hello');
    manager('view', 'delete');
    delete("/admin/cms/entries/{$hello->entry_id}/force")->assertSessionHasErrors('entry');

    $hello->entry->delete();
    delete("/admin/cms/entries/{$hello->entry_id}/force")->assertRedirect('/admin/cms/collections/articles/entries?status=trashed');
    expect(Entry::query()->withTrashed()->count())->toBe(0)->and(EntryTranslation::query()->count())->toBe(0);
});

it('undoes a revision restore in the same session', function () {
    $hello = app(PublishEntry::class)->handle(managed('Hello'));
    app(SaveDraft::class)->handle($hello->fresh() ?? $hello, ['title' => 'Hello, newer']);
    manager('view', 'update');
    $revision = $hello->revisions()->sole();

    post("/admin/cms/entries/{$hello->entry_id}/revisions/{$revision->id}/restore");
    expect($hello->fresh()?->draft_title)->toBe('Hello');
    get(entryEditUrl($hello->entry_id))->assertInertia(fn (Assert $page) => $page->where('undoRestore', $hello->id));

    post("/admin/cms/entries/{$hello->entry_id}/translations/{$hello->locale}/undo-restore")->assertSessionHas('success');
    expect($hello->fresh()?->draft_title)->toBe('Hello, newer');
    post("/admin/cms/entries/{$hello->entry_id}/translations/{$hello->locale}/undo-restore")->assertSessionHas('error');
});

it('saves over a newer version when asked to', function () {
    $hello = managed('Hello');
    app(SaveDraft::class)->handle($hello, ['title' => 'Theirs']);
    manager('view', 'update');

    put("/admin/cms/entries/{$hello->entry_id}", ['title' => 'Mine', 'version' => 1])->assertSessionHasErrors('version');
    put("/admin/cms/entries/{$hello->entry_id}", ['title' => 'Mine', 'version' => null])->assertSessionHasNoErrors();
    expect($hello->fresh()?->draft_title)->toBe('Mine');
});

it('locks entries and global sets for those who may edit them', function () {
    $hello = managed('Hello');
    manager('view');
    postJson("/admin/locks/cms.entry/{$hello->entry_id}", ['editor' => 'tab-a', 'locale' => 'en'])->assertForbidden();

    manager('view', 'update');
    postJson("/admin/locks/cms.entry/{$hello->entry_id}", ['editor' => 'tab-a', 'locale' => 'en'])->assertJson(['status' => 'ok']);
    postJson('/admin/locks/cms.entry/999', ['editor' => 'tab-a'])->assertForbidden();
});

it('releases an entry\'s lock after it went to the trash (NEW-editor-4)', function () {
    $hello = managed('Hello');
    manager('view', 'update', 'delete');
    postJson("/admin/locks/cms.entry/{$hello->entry_id}", ['editor' => 'tab-a', 'locale' => 'en'])->assertJson(['status' => 'ok']);

    app(TrashEntry::class)->handle($hello->entry);
    postJson("/admin/locks/cms.entry/{$hello->entry_id}/release", ['editor' => 'tab-a', 'locale' => 'en'])->assertOk()->assertJson(['status' => 'released']);
    expect(app(EditLocks::class)->current(EditLocks::key('cms.entry', (string) $hello->entry_id, 'en')))->toBeNull();
});

it('lets only the collection\'s editors pick a template, under the site\'s prefixes', function () {
    $hello = app(PublishEntry::class)->handle(managed('Hello'));
    $views = sys_get_temp_dir().'/sunrice-views-'.uniqid();
    mkdir("{$views}/layouts", recursive: true);
    file_put_contents("{$views}/layouts/app.blade.php", 'THE APP LAYOUT');
    View::addLocation($views);
    manager('view', 'update');

    // No other view of the app can be made a public page.
    put("/admin/cms/entries/{$hello->entry_id}/settings", ['template' => 'layouts.app'])->assertSessionHasErrors('template');
    put("/admin/cms/entries/{$hello->entry_id}/settings", ['template' => 'emails.invoice'])->assertSessionHasErrors('template');
    put("/admin/cms/entries/{$hello->entry_id}/settings", ['template' => 'cms.landing'])->assertSessionHasNoErrors();
    config(['sunrice-cms.site.template_prefixes' => ['cms.', 'pages.']]);
    put("/admin/cms/entries/{$hello->entry_id}/settings", ['template' => 'pages.landing'])->assertSessionHasNoErrors();
    expect($hello->entry->fresh()?->template)->toBe('pages.landing');

    // Authors of their own entries may change the rest, keeping the template as it is.
    $author = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update-own');
    actingAs($author);
    $mine = managed('Mine');
    put("/admin/cms/entries/{$mine->entry_id}/settings", ['template' => 'cms.landing'])->assertForbidden();
    put("/admin/cms/entries/{$mine->entry_id}/settings", ['template' => '', 'comments_closed' => '1'])->assertSessionHasNoErrors();
    expect($mine->entry->fresh()?->only(['template', 'comments_closed']))->toBe(['template' => null, 'comments_closed' => true]);

    // A template stored outside the prefixes (from before, or after the config changed) isn't rendered.
    $hello->entry->forceFill(['template' => 'layouts.app'])->save();
    get('/articles/hello')->assertOk()->assertDontSee('THE APP LAYOUT')->assertSee('Hello');
});

it('tells the media library where entries and globals use files', function () {
    Storage::fake('public');
    $photo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('photo.jpg'));
    $share = app(UploadMedia::class)->handle(UploadedFile::fake()->image('share.jpg'));
    Blueprint::query()->create(['handle' => 'with-image', 'title' => 'With image', 'fields' => [['handle' => 'image', 'type' => 'media']]]);
    $gallery = Collection::query()->create(['handle' => 'gallery', 'title' => 'Gallery', 'blueprint' => 'with-image']);

    $entry = app(CreateEntry::class)->handle($gallery, ['title' => 'Sunset', 'data' => ['image' => $photo->id], 'seo' => ['image' => $share->id]]);
    expect(app(MediaUsages::class)->of($photo)[0]['label'])->toBe('Gallery: Sunset (EN)')
        ->and(app(MediaUsages::class)->of($share))->toHaveCount(1);

    app(SaveDraft::class)->handle($entry, ['data' => ['image' => null], 'seo' => []]);
    expect(app(MediaUsages::class)->of($photo))->toBe([]);
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle($entry->fresh() ?? $entry, ['data' => ['image' => $photo->id]]));
    expect(app(MediaUsages::class)->of($photo))->toHaveCount(1);

    $set = GlobalSet::query()->create(['handle' => 'brand', 'title' => 'Brand', 'blueprint' => 'with-image']);
    app(SaveGlobals::class)->handle($set, ['image' => $share->id]);
    expect(array_column(app(MediaUsages::class)->of($share), 'label'))->toBe(['Globals: Brand (EN)']);

    $entry->entry->delete();
    app(ForceDeleteEntry::class)->handle($entry->entry);
    expect(app(MediaUsages::class)->of($photo))->toBe([]);
});

it('saves the page settings with the draft, and the parent when creating', function () {
    $this->articles->update(['hierarchical' => true]);
    $about = managed('About');
    manager('view', 'create', 'update');

    // Created under a parent; a parent from another collection is refused.
    post('/admin/cms/collections/articles/entries', ['title' => 'Story', 'data' => ['intro' => 'Hi'], 'parent_id' => $about->entry_id])->assertSessionHasNoErrors();
    $story = EntryTranslation::query()->where('draft_title', 'Story')->sole();
    expect($story->entry->parent_id)->toBe($about->entry_id);
    $other = app(CreateEntry::class)->handle(Collection::query()->create(['handle' => 'other', 'title' => 'Other']), ['title' => 'Elsewhere']);
    post('/admin/cms/collections/articles/entries', ['title' => 'Lost', 'data' => ['intro' => 'Hi'], 'parent_id' => $other->entry_id])->assertSessionHasErrors('parent_id');
    expect(EntryTranslation::query()->where('draft_title', 'Lost')->exists())->toBeFalse();

    // Saving the draft saves the settings with it, all or nothing.
    $team = managed('Team');
    put("/admin/cms/entries/{$team->entry_id}", ['title' => 'Team', 'data' => ['intro' => 'Hi'], 'version' => $team->version, 'parent_id' => $about->entry_id, 'template' => '', 'comments_closed' => true])
        ->assertSessionHasNoErrors();
    expect($team->entry->fresh()?->only(['parent_id', 'comments_closed']))->toBe(['parent_id' => $about->entry_id, 'comments_closed' => true]);
    put("/admin/cms/entries/{$team->entry_id}", ['title' => 'Our team', 'data' => ['intro' => 'Hi'], 'version' => $team->fresh()?->version, 'parent_id' => $team->entry_id])
        ->assertSessionHasErrors('parent_id');
    expect($team->fresh()?->draft_title)->toBe('Team');
    put("/admin/cms/entries/{$team->entry_id}", ['title' => 'Our team', 'data' => ['intro' => 'Hi'], 'version' => 999, 'parent_id' => null])
        ->assertSessionHasErrors('version');
    expect($team->entry->fresh()?->parent_id)->toBe($about->entry_id);
    // Publishing saves them too; without them, they stay as they are.
    manager('view', 'update', 'publish');
    put("/admin/cms/entries/{$team->entry_id}", ['title' => 'Team', 'data' => ['intro' => 'Hi'], 'version' => $team->fresh()?->version, 'publish' => true, 'parent_id' => null])
        ->assertSessionHasNoErrors();
    expect($team->entry->fresh()?->parent_id)->toBeNull();
    put("/admin/cms/entries/{$team->entry_id}", ['title' => 'Team', 'data' => ['intro' => 'Hi'], 'version' => $team->fresh()?->version])->assertSessionHasNoErrors();
    expect($team->entry->fresh()?->comments_closed)->toBeTrue();

    // Authors of their own entries keep the template as it is.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update-own'));
    $mine = managed('Mine');
    put("/admin/cms/entries/{$mine->entry_id}", ['title' => 'Mine', 'data' => ['intro' => 'Hi'], 'version' => $mine->version, 'template' => 'cms.landing'])->assertForbidden();
    put("/admin/cms/entries/{$mine->entry_id}", ['title' => 'Mine', 'data' => ['intro' => 'Hi'], 'version' => $mine->version, 'template' => '', 'parent_id' => $about->entry_id])->assertSessionHasNoErrors();
    expect($mine->entry->fresh()?->parent_id)->toBe($about->entry_id);
});
