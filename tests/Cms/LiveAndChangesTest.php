<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\DiscardChanges;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\UnpublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Mail\ReviewRequested;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * An entry translation has a live version (what visitors see) and the
 * unpublished changes (the saved draft): the editor tells them apart,
 * compares them, and can throw the changes away.
 */

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'note', 'title' => 'Note', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea'],
        ['handle' => 'featured', 'type' => 'toggle'],
    ]]);
    $this->notes = Collection::query()->create(['handle' => 'notes', 'title' => 'Notes', 'blueprint' => 'note', 'route' => '/notes/{slug}']);
    $this->publisher = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.entries.publish', 'cms.entries.view-drafts');
    $this->publisher->forceFill(['name' => 'Ayu'])->save();
    actingAs($this->publisher);
});

/** A live note, then saved again with other words: live, with unpublished changes. */
function changedNote(): EntryTranslation
{
    $translation = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->notes, ['title' => 'Hello', 'data' => ['intro' => 'Live words', 'featured' => false]]));

    return app(SaveDraft::class)->handle($translation->fresh(), ['title' => 'Hello again', 'data' => ['intro' => 'New words', 'featured' => false], 'seo' => ['description' => 'Fresh']]);
}

it('discards the unpublished changes: the draft is the live version again, versioned and logged', function () {
    $translation = changedNote();
    $version = $translation->version;

    $discarded = app(DiscardChanges::class)->handle($translation, $version);

    expect($discarded->draft_title)->toBe('Hello')
        ->and($discarded->draft_slug)->toBe('hello')
        ->and($discarded->draft_data)->toBe($discarded->data)
        ->and($discarded->draft_data['intro'])->toBe('Live words')
        ->and($discarded->draft_data)->not->toHaveKey('seo')
        ->and($discarded->hasUnpublishedChanges())->toBeFalse()
        ->and($discarded->version)->toBe($version + 1)
        ->and($discarded->isLive())->toBeTrue()
        ->and(Activity::query()->where('action', 'discarded')->where('subject_label', 'Hello')->exists())->toBeTrue();
    // Nothing left to discard: nothing changes, nothing logged.
    app(DiscardChanges::class)->handle($discarded->fresh());
    expect($discarded->fresh()?->version)->toBe($version + 1)
        ->and(Activity::query()->where('action', 'discarded')->count())->toBe(1);
});

it('refuses to discard over a newer save, and where there is no live version', function () {
    $translation = changedNote();
    $opened = $translation->version;
    app(SaveDraft::class)->handle($translation->fresh(), ['title' => 'Someone else']);

    expect(fn () => app(DiscardChanges::class)->handle($translation->fresh(), $opened))->toThrow(ValidationException::class);
    expect($translation->fresh()?->draft_title)->toBe('Someone else');

    $draft = app(CreateEntry::class)->handle($this->notes, ['title' => 'Never published']);
    expect(fn () => app(DiscardChanges::class)->handle($draft))->toThrow(ValidationException::class);
    // Taken off the site: no live version to go back to either.
    app(UnpublishEntry::class)->handle($translation->fresh());
    expect(fn () => app(DiscardChanges::class)->handle($translation->fresh()))->toThrow(ValidationException::class);
});

it('discards from the editor with the permission to edit, and no other', function () {
    $translation = changedNote();
    $url = "/admin/cms/entries/{$translation->entry_id}/translations/en/discard";

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.publish'));
    post($url, ['version' => $translation->version])->assertForbidden();
    expect($translation->fresh()?->hasUnpublishedChanges())->toBeTrue();

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    post($url, ['version' => $translation->version - 1])->assertSessionHasErrors('version');
    post($url, ['version' => $translation->version])->assertRedirect()->assertSessionHas('success', 'Changes discarded. The editor shows the live version again.');
    expect($translation->fresh()?->hasUnpublishedChanges())->toBeFalse();
    post("/admin/cms/entries/{$translation->entry_id}/translations/fr/discard")->assertNotFound();
});

it('tells the editor whether visitors see the draft, and only then loads the live version\'s details', function () {
    $translation = changedNote();

    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('entry.status', 'published')
        ->where('entry.hasUnpublishedChanges', true)
        ->where('entry.everPublished', true)
        ->where('entry.live.by', 'Ayu')
        ->where('entry.live.publishedAt', fn (?string $at) => $at !== null)
        // What the saved draft changed from the live version: the editor's "Changed" markers.
        ->where('entry.live.changed', ['title', 'intro', 'seo.description'])
        ->where('entry.url', url('/notes/hello'))
        ->where('entry.previewUrl', fn (string $url) => str_contains($url, 'cms-preview/'.$translation->id) && str_contains($url, 'signature='))
        ->where('entry.previewExpiresAt', fn (?string $at) => $at !== null && now()->addMinutes(29)->lt($at)));

    app(PublishEntry::class)->handle($translation->fresh());
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('entry.hasUnpublishedChanges', false)->where('entry.live', null));

    $draft = app(CreateEntry::class)->handle($this->notes, ['title' => 'Never published']);
    get(entryEditUrl($draft->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('entry.status', 'draft')->where('entry.everPublished', false)->where('entry.live', null)->where('entry.url', null));

    app(UnpublishEntry::class)->handle($translation->fresh());
    get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('entry.status', 'draft')->where('entry.everPublished', true)->where('entry.live', null));
});

it('compares the live version with the unpublished changes, in words', function () {
    $translation = changedNote();

    get("/admin/cms/entries/{$translation->entry_id}/translations/en/compare-live")->assertOk()->assertExactJson(['fields' => [
        ['label' => 'Title', 'live' => 'Hello', 'draft' => 'Hello again'],
        ['label' => 'Intro', 'live' => 'Live words', 'draft' => 'New words'],
        ['label' => 'Meta description', 'live' => '', 'draft' => 'Fresh'],
    ]]);

    $draft = app(CreateEntry::class)->handle($this->notes, ['title' => 'Never published']);
    get("/admin/cms/entries/{$draft->entry_id}/translations/en/compare-live")->assertNotFound();
    actingAs(adminUser(Panel::ACCESS));
    get("/admin/cms/entries/{$translation->entry_id}/translations/en/compare-live")->assertForbidden();
});

it('marks live entries with unpublished changes in the list, picker and dashboard, without a query per row', function () {
    $changed = changedNote();
    $live = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->notes, ['title' => 'Plain']));
    app(CreateEntry::class)->handle($this->notes, ['title' => 'Draft only']);

    get('/admin/cms/collections/notes/entries')->assertInertia(fn (Assert $page) => $page
        ->where('entries.data', fn ($rows) => collect($rows)->mapWithKeys(fn ($row) => [$row['title'] => [$row['status'], $row['changes']]])->sortKeys()->all() === [
            'Draft only' => ['draft', false], 'Hello again' => ['published', true], 'Plain' => ['published', false],
        ]));

    $items = collect(get('/admin/cms/entries/picker?'.http_build_query(['ids' => [$changed->entry_id, $live->entry_id]]))->assertOk()->json('items'));
    expect($items->pluck('changes', 'id')->all())->toBe([$changed->entry_id => true, $live->entry_id => false]);

    $rows = collect(app(Panel::class)->getWidgets()['cms.recent-edits']->resolve($this->publisher)['rows']);
    expect($rows->pluck('badge', 'title')->all())->toMatchArray(['Hello again' => 'Live · Changes pending', 'Plain' => 'Live', 'Draft only' => 'Draft'])
        // In the list's colours: "Changes pending" in amber.
        ->and($rows->pluck('tone', 'title')->all())->toMatchArray(['Hello again' => 'warning', 'Plain' => 'success', 'Draft only' => 'neutral']);

    // Scheduled, with a newer draft: said so as the list says it.
    $later = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->notes, ['title' => 'Later']), now()->addWeek());
    app(SaveDraft::class)->handle($later->fresh(), ['title' => 'Later, changed']);
    $rows = collect(app(Panel::class)->getWidgets()['cms.recent-edits']->resolve($this->publisher)['rows']);
    expect($rows->firstWhere('title', 'Later, changed'))->toMatchArray(['badge' => 'Scheduled · Changes pending', 'tone' => 'warning']);

    // As many queries for many rows as for a few.
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        get('/admin/cms/collections/notes/entries')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $count();
    $few = $count();
    foreach (range(1, 5) as $i) {
        $more = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->notes, ['title' => "More {$i}"]));
        app(SaveDraft::class)->handle($more->fresh(), ['title' => "More {$i} again"]);
    }
    expect($count())->toBe($few);
});

it('speaks of unpublished changes and the live page on the site and in review requests', function () {
    $translation = changedNote();

    get('/notes/hello')->assertOk()->assertSee('aria-label="Unpublished changes"', false)
        ->assertSee('This page has unpublished changes.')->assertSee('View the unpublished changes');
    get('/notes/hello?draft')->assertOk()->assertSee('You are viewing unpublished changes.')->assertSee('View the live page');
    $preview = URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(5), ['translation' => $translation->id]);
    get($preview)->assertOk()->assertSee('Preview of unpublished changes')->assertSee('View the live page')->assertDontSee('Preview of the draft');

    $draft = app(CreateEntry::class)->handle($this->notes, ['title' => 'Never published']);
    get(URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(5), ['translation' => $draft->id]))->assertOk()
        ->assertSee('aria-label="Draft"', false)->assertSee('Preview of the draft');

    $mail = (new ReviewRequested($translation->fresh(), $this->publisher))->render();
    expect($mail)->toContain('review the unpublished changes to <strong>Hello again</strong>')->toContain('View the live page')->toContain(url('/notes/hello'));
    expect((new ReviewRequested($draft, $this->publisher))->render())->toContain('asks you to review <strong>Never published</strong>')->not->toContain('View the live page');
});

it('offers the views that exist, in cms/ and elsewhere, without partials and layouts', function () {
    $views = (string) (config('view.paths')[0] ?? resource_path('views'));
    // The starter templates' test wants no resources/views/cms: made here, removed after.
    expect(is_dir("{$views}/cms"))->toBeFalse();
    File::ensureDirectoryExists("{$views}/cms/collections/notes");
    File::ensureDirectoryExists("{$views}/cms/partials");
    File::put("{$views}/cms/collections/notes/show.blade.php", 'note');
    File::put("{$views}/cms/landing.blade.php", 'landing');
    File::put("{$views}/cms/partials/header.blade.php", 'header');
    File::ensureDirectoryExists("{$views}/pages");
    File::ensureDirectoryExists("{$views}/layouts");
    File::put("{$views}/pages/about.blade.php", 'about');
    File::put("{$views}/layouts/site.blade.php", 'layout');
    try {
        $translation = changedNote();
        // Not with the page: finding them reads the views folders. Loaded when the Template choice opens.
        get(entryEditUrl($translation->entry_id))->assertInertia(fn (Assert $page) => $page
            ->missing('templates.choices')->missing('templateChoices')->where('templates.example', 'pages.notes-landing')
            ->reloadOnly('templateChoices', fn (Assert $reload) => $reload
                ->where('templateChoices', fn ($choices) => collect($choices)->contains('cms.collections.notes.show')
                    && collect($choices)->contains('cms.landing') && collect($choices)->contains('pages.about')
                    && ! collect($choices)->contains('cms.partials.header') && ! collect($choices)->contains('layouts.site'))));
    } finally {
        File::deleteDirectory("{$views}/cms");
        File::deleteDirectory("{$views}/pages");
        File::deleteDirectory("{$views}/layouts");
    }
});
