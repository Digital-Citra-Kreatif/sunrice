<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Mail\ReviewRequested;
use Sunrice\Cms\Mail\ReviewSentBack;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $this->journal = Collection::query()->create(['handle' => 'journal', 'title' => 'Journal']);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    $this->author = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update-own');
    $this->editor = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.entries.publish');
    $this->pagesEditor = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.pages.publish');
    actingAs($this->author);
    $this->draft = app(CreateEntry::class)->handle($this->journal, ['title' => 'Our first year']);
});

it('lets an author submit a draft for review, telling those who may publish it', function () {
    Mail::fake();

    post("/admin/cms/entries/{$this->draft->entry_id}/review")
        ->assertRedirect()->assertSessionHas('success', 'Sent for review. Those who can publish it have been told.');

    $translation = $this->draft->fresh();
    expect($translation->review_requested_at)->not->toBeNull()
        ->and($translation->review_requested_by)->toBe($this->author->id)
        ->and(Activity::query()->where('action', 'review requested')->exists())->toBeTrue();
    // The editor may publish journal entries; the pages editor may not, the author asked.
    Mail::assertQueued(ReviewRequested::class, fn (ReviewRequested $mail) => $mail->hasTo($this->editor->email));
    Mail::assertQueuedCount(1);
    expect((new ReviewRequested($translation, $this->author))->render())->toContain('Rina')->toContain('Our first year');

    get(entryEditUrl($this->draft->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('entry.review.by', 'Rina')
        ->where('can.publish', false));
});

it('shows the waiting drafts on the dashboard of those who may publish them, until published', function () {
    Mail::fake();
    post("/admin/cms/entries/{$this->draft->entry_id}/review");

    expect(app(Panel::class)->getWidgets()['cms.review']->isVisibleTo($this->author))->toBeFalse();
    $data = app(Panel::class)->getWidgets()['cms.review']->resolve($this->editor);
    expect($data['count'])->toBe(1)
        ->and($data['rows'][0]['title'])->toBe('Our first year')
        ->and($data['rows'][0]['meta'])->toContain('from Rina')
        ->and(app(Panel::class)->getWidgets()['cms.review']->resolve($this->pagesEditor)['rows'])->toBe([]);

    actingAs($this->editor);
    app(PublishEntry::class)->handle($this->draft->fresh());
    expect($this->draft->fresh()->review_requested_at)->toBeNull()
        ->and(app(Panel::class)->getWidgets()['cms.review']->resolve($this->editor)['rows'])->toBe([]);
});

it('refuses a review of what the site already shows, and to those who may not edit the entry', function () {
    actingAs($this->editor);
    $live = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->journal, ['title' => 'Live']));
    post("/admin/cms/entries/{$live->entry_id}/review")->assertSessionHasErrors('entry');

    // Not the author's own entry.
    actingAs($this->author);
    post("/admin/cms/entries/{$live->entry_id}/review")->assertForbidden();
    expect(EntryTranslation::query()->whereNotNull('review_requested_at')->count())->toBe(0);
});

it('lets the one who asked withdraw the request, and nobody else', function () {
    Mail::fake();
    post("/admin/cms/entries/{$this->draft->entry_id}/review");

    get(entryEditUrl($this->draft->entry_id))->assertInertia(fn (Assert $page) => $page->where('entry.review.mine', true));
    actingAs($this->editor);
    get(entryEditUrl($this->draft->entry_id))->assertInertia(fn (Assert $page) => $page->where('entry.review.mine', false));
    post("/admin/cms/entries/{$this->draft->entry_id}/review/withdraw")->assertSessionHasErrors('entry');
    expect($this->draft->fresh()->review_requested_at)->not->toBeNull();

    actingAs($this->author);
    post("/admin/cms/entries/{$this->draft->entry_id}/review/withdraw")
        ->assertSessionHas('success', 'Request withdrawn. It\'s a draft again, for you to keep working on.');
    expect($this->draft->fresh()->review_requested_at)->toBeNull()
        ->and($this->draft->fresh()->review_requested_by)->toBeNull()
        ->and(Activity::query()->where('action', 'review withdrawn')->exists())->toBeTrue()
        ->and(app(Panel::class)->getWidgets()['cms.review']->resolve($this->editor)['rows'])->toBe([]);
    // Nothing left to withdraw.
    post("/admin/cms/entries/{$this->draft->entry_id}/review/withdraw")->assertSessionHasErrors('entry');
});

it('lets a publisher send a draft back with a note, emailed to the one who asked', function () {
    Mail::fake();
    post("/admin/cms/entries/{$this->draft->entry_id}/review");

    // Those who may not publish it can't send it back.
    post("/admin/cms/entries/{$this->draft->entry_id}/review/send-back", ['note' => 'No'])->assertForbidden();
    actingAs($this->pagesEditor);
    post("/admin/cms/entries/{$this->draft->entry_id}/review/send-back")->assertForbidden();

    actingAs($this->editor);
    post("/admin/cms/entries/{$this->draft->entry_id}/review/send-back", ['note' => str_repeat('x', 2001)])->assertSessionHasErrors('note');
    post("/admin/cms/entries/{$this->draft->entry_id}/review/send-back", ['note' => "Shorter intro, please.\nAnd a photo."])
        ->assertSessionHas('success', 'Sent back. The one who asked has been told.');

    $translation = $this->draft->fresh();
    expect($translation->review_requested_at)->toBeNull()
        ->and($translation->status())->toBe('draft')
        ->and(Activity::query()->where('action', 'review sent back')->exists())->toBeTrue();
    Mail::assertQueued(ReviewSentBack::class, fn (ReviewSentBack $mail) => $mail->hasTo($this->author->email));
    $html = (new ReviewSentBack($translation, $this->editor->name, "Shorter intro, please.\nAnd a photo."))->render();
    expect($html)->toContain('Our first year')->toContain('Shorter intro, please.')->toContain(e($this->editor->name))
        ->not->toContain('"email"');
    // Without a note, no quote.
    expect((new ReviewSentBack($translation, null, null))->render())->toContain('Someone')->not->toContain('<blockquote');

    post("/admin/cms/entries/{$this->draft->entry_id}/review/send-back")->assertSessionHasErrors('entry');
});
