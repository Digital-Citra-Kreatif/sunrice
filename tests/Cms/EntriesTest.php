<?php

declare(strict_types=1);

/*
 * Fields are compared with toEqual, not toBe: MySQL's JSON columns
 * reorder object keys.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\RestoreEntry;
use Sunrice\Cms\Actions\Entries\RestoreRevision;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Entries\UnpublishEntry;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Revision;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    $this->user = adminUser();
    actingAs($this->user);
    Blueprint::query()->create(['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea', 'label' => 'Intro', 'required' => true],
        ['handle' => 'featured', 'type' => 'toggle'],
        ['handle' => 'day', 'type' => 'date'],
    ]]);
    $this->articles = Collection::query()->create(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article']);
});

function article(array $input = []): EntryTranslation
{
    return app(CreateEntry::class)->handle(test()->articles, $input + ['title' => 'Hello world', 'data' => ['intro' => 'Hi']]);
}

it('creates an entry as a draft, with defaults and a slug from its title', function () {
    $translation = article();

    expect($translation->only(['locale', 'status', 'draft_title', 'draft_slug', 'title', 'slug']))
        ->toBe(['locale' => 'en', 'status' => 'draft', 'draft_title' => 'Hello world', 'draft_slug' => 'hello-world', 'title' => null, 'slug' => null])
        ->and($translation->draft_data)->toEqual(['intro' => 'Hi', 'featured' => false, 'day' => null])
        ->and($translation->entry->author_id)->toBe($this->user->id)
        ->and($translation->isLive())->toBeFalse()
        ->and(Activity::query()->latest('id')->first()?->only(['action', 'subject_type', 'subject_label']))
        ->toBe(['action' => 'created', 'subject_type' => 'entry', 'subject_label' => 'Hello world']);
});

it('validates the title, slug and fields', function (array $input, string $error) {
    expect(fn () => article($input))->toThrow(function (ValidationException $e) use ($error) {
        expect($e->errors())->toHaveKey($error);
    });
})->with([
    [['title' => ''], 'title'],
    [['slug' => 'Not A Slug'], 'slug'],
    [['data' => ['intro' => '']], 'data.intro'],
    [['data' => ['intro' => 'Hi', 'day' => 'someday']], 'data.day'],
    [['locale' => 'fr'], 'locale'],
]);

it('keeps slugs unique per collection and language, trashed entries included', function () {
    $first = article();
    app(TrashEntry::class)->handle($first->entry);

    expect(article()->draft_slug)->toBe('hello-world-2')
        ->and(fn () => article(['slug' => 'hello-world']))->toThrow(ValidationException::class, 'already uses "hello-world"');

    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    expect(app(CreateEntry::class)->handle($pages, ['title' => 'Hello world'])->draft_slug)->toBe('hello-world');
});

it('doesn\'t store fields the blueprint doesn\'t have', function () {
    expect(article(['data' => ['intro' => 'Hi', 'secret' => 'x']])->draft_data)->not->toHaveKey('secret');
});

it('saves drafts without touching the live version', function () {
    $translation = app(PublishEntry::class)->handle(article());
    // A later request: the log merges edits into a line written in the same one.
    app()->instance('request', request()->duplicate());
    app(SaveDraft::class)->handle($translation, ['title' => 'Hello again', 'data' => ['featured' => true]]);

    expect($translation->title)->toBe('Hello world')
        ->and($translation->draft_title)->toBe('Hello again')
        ->and($translation->draft_data)->toEqual(['intro' => 'Hi', 'featured' => true, 'day' => null])
        ->and($translation->data['featured'])->toBeFalse()
        ->and($translation->version)->toBe(2)
        ->and($translation->hasUnpublishedChanges())->toBeTrue()
        ->and(Activity::query()->latest('id')->first()?->properties)->toMatchArray(['changed' => ['title', 'featured']]);
});

it('refuses a save over a newer one', function () {
    $translation = article();
    $stale = $translation->replicate()->setRawAttributes($translation->getAttributes());
    app(SaveDraft::class)->handle($translation, ['title' => 'Mine', 'version' => 1]);

    expect(fn () => app(SaveDraft::class)->handle($stale, ['title' => 'Theirs', 'version' => 1]))->toThrow(ValidationException::class, 'Someone else saved')
        ->and($translation->fresh()?->draft_title)->toBe('Mine');
});

it('does nothing when nothing changed', function () {
    $translation = article();
    app(SaveDraft::class)->handle($translation, ['title' => 'Hello world']);

    expect($translation->fresh()?->version)->toBe(1);
});

it('publishes the draft and keeps a revision', function () {
    travelTo('2026-10-19 09:00:00');
    $translation = app(PublishEntry::class)->handle(article());

    expect($translation->only(['status', 'title', 'slug']))->toBe(['status' => 'published', 'title' => 'Hello world', 'slug' => 'hello-world'])
        ->and($translation->published_at?->toDateTimeString())->toBe('2026-10-19 09:00:00')
        ->and($translation->isLive())->toBeTrue()
        ->and($translation->hasUnpublishedChanges())->toBeFalse()
        ->and($translation->revisions)->toHaveCount(1)
        ->and($translation->revisions->first()?->user_id)->toBe($this->user->id)
        ->and(EntryTranslation::query()->live()->pluck('id')->all())->toBe([$translation->id]);

    // Publishing again keeps the date.
    travelTo('2026-10-20 09:00:00');
    app(SaveDraft::class)->handle($translation, ['title' => 'Hello again']);
    app(PublishEntry::class)->handle($translation);
    expect($translation->published_at?->toDateTimeString())->toBe('2026-10-19 09:00:00')
        ->and($translation->title)->toBe('Hello again');
});

it('goes live on a date in the future', function () {
    travelTo('2026-10-19 09:00:00');
    $translation = app(PublishEntry::class)->handle(article(), new DateTimeImmutable('2026-10-25 08:00:00'));

    expect($translation->isPublished())->toBeTrue()
        ->and($translation->isLive())->toBeFalse()
        ->and(EntryTranslation::query()->live()->count())->toBe(0);

    travelTo('2026-10-25 08:00:00');
    expect(EntryTranslation::query()->live()->count())->toBe(1);
});

it('checks the draft again before publishing', function () {
    $translation = article();
    Blueprint::query()->where('handle', 'article')->update(['fields' => json_encode([['handle' => 'body', 'type' => 'textarea', 'required' => true]])]);
    $translation->entry->collection->flushSchema();

    app(PublishEntry::class)->handle($translation);
})->throws(ValidationException::class, 'The Body field is required');

it('keeps only the latest revisions', function () {
    config(['sunrice-cms.revisions.keep' => 2]);
    $translation = article();
    foreach (['A', 'B', 'C'] as $title) {
        app(SaveDraft::class)->handle($translation, ['title' => $title]);
        app(PublishEntry::class)->handle($translation);
    }

    expect(Revision::query()->orderBy('id')->pluck('title')->all())->toBe(['B', 'C']);
});

it('restores a revision into the draft', function () {
    $translation = app(PublishEntry::class)->handle(article());
    $revision = $translation->revisions()->firstOrFail();
    app(SaveDraft::class)->handle($translation, ['title' => 'Changed', 'slug' => 'changed']);

    app(RestoreRevision::class)->handle($revision);
    $translation->refresh();

    expect($translation->draft_title)->toBe('Hello world')
        ->and($translation->draft_slug)->toBe('hello-world')
        ->and($translation->version)->toBe(3)
        ->and(Activity::query()->latest('id')->first()?->action)->toBe('reverted');
});

it('keeps the current slug when restoring one that was taken since', function () {
    $translation = app(PublishEntry::class)->handle(article());
    $revision = $translation->revisions()->firstOrFail();
    app(SaveDraft::class)->handle($translation, ['slug' => 'renamed']);
    app(PublishEntry::class)->handle($translation);
    article(['slug' => 'hello-world']);

    app(RestoreRevision::class)->handle($revision);

    expect($translation->fresh()?->draft_slug)->toBe('renamed');
});

it('unpublishes, keeping the date', function () {
    $translation = app(PublishEntry::class)->handle(article());
    $date = $translation->published_at;
    app(UnpublishEntry::class)->handle($translation);

    expect($translation->fresh()?->status)->toBe('draft')
        ->and($translation->fresh()?->published_at?->eq($date))->toBeTrue()
        ->and(EntryTranslation::query()->live()->count())->toBe(0);
});

it('trashes and restores entries', function () {
    $translation = app(PublishEntry::class)->handle(article());
    app(TrashEntry::class)->handle($translation->entry);

    expect(EntryTranslation::query()->live()->count())->toBe(0)
        ->and(fn () => app(PublishEntry::class)->handle($translation->fresh() ?? $translation))->toThrow(ValidationException::class, 'in the trash');

    app(RestoreEntry::class)->handle($translation->entry);
    expect(EntryTranslation::query()->live()->count())->toBe(1)
        ->and(Activity::query()->latest('id')->first()?->only(['action', 'subject_label']))->toBe(['action' => 'restored', 'subject_label' => 'Hello world']);
});

it('hides entries of a deleted collection from the site', function () {
    app(PublishEntry::class)->handle(article());
    $this->articles->delete();

    expect(EntryTranslation::query()->live()->count())->toBe(0);
});

it('gives templates the live values, or the draft in a preview', function () {
    $translation = app(PublishEntry::class)->handle(article(['data' => ['intro' => 'Hi', 'day' => '2026-10-19']]));
    app(SaveDraft::class)->handle($translation, ['data' => ['intro' => 'Draft intro']]);

    expect($translation->values()['intro'])->toBe('Hi')
        ->and($translation->values()['day']?->toDateString())->toBe('2026-10-19')
        ->and($translation->values(new HydrationContext('en', preview: true))['intro'])->toBe('Draft intro');
});

it('uses the configured languages', function () {
    config(['sunrice-cms.locales' => ['id', 'en']]);

    expect(article()->locale)->toBe('id')
        ->and(article(['locale' => 'en'])->draft_slug)->toBe('hello-world');
});

it('keeps data when the blueprint loses a field', function () {
    $translation = article(['data' => ['intro' => 'Hi', 'featured' => true]]);
    DB::table('sunrice_blueprints')->where('handle', 'article')->update(['fields' => json_encode([['handle' => 'intro', 'type' => 'textarea']])]);
    $translation->entry->collection->flushSchema();
    app(SaveDraft::class)->handle($translation, ['data' => ['intro' => 'Changed', 'featured' => false]]);

    expect($translation->draft_data)->toEqual(['intro' => 'Changed', 'featured' => true, 'day' => null]);
});

it('names fields by their labels in messages', function () {
    expect(fn () => article(['data' => ['intro' => '']]))->toThrow(function (ValidationException $e) {
        expect($e->errors()['data.intro'][0])->toBe('The Intro field is required.');
    });
});
