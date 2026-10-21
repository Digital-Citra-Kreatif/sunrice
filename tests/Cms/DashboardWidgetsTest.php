<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Dashboard\Widget;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Comment;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Submission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => []]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post']);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'blueprint' => 'post']);
});

function dashPost(Collection $collection, string $title, ?DateTimeInterface $publish = null, bool $draft = false): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title]);

    return $draft ? $translation : app(PublishEntry::class)->handle($translation, $publish);
}

/** The data of a dashboard widget for this user. */
function widgetData(string $key, Authorizable $user): array
{
    return app(Panel::class)->getWidgets()[$key]->resolve($user);
}

/** The keys of the widgets this user sees. */
function widgetKeys(Authorizable $user): array
{
    return array_map(fn (Widget $widget) => $widget->key, app(Panel::class)->widgetsFor($user));
}

it('counts entries per collection the user may view', function () {
    dashPost($this->blog, 'Live one');
    dashPost($this->blog, 'Live two');
    dashPost($this->blog, 'Draft', draft: true);
    dashPost($this->blog, 'Later', now()->addDay());
    dashPost($this->news, 'News item');
    $trashed = dashPost($this->blog, 'Gone');
    app(TrashEntry::class)->handle($trashed->entry);

    $editor = adminUser(Panel::ACCESS, 'cms.entries.view');
    expect(widgetData('cms.collections', $editor)['rows'])->toBe([
        ['title' => 'Blog', 'meta' => '1 draft · 1 scheduled', 'url' => 'cms/collections/blog/entries', 'badge' => '2 live', 'tone' => 'success', 'at' => null],
        ['title' => 'News', 'meta' => 'No drafts', 'url' => 'cms/collections/news/entries', 'badge' => '1 live', 'tone' => 'success', 'at' => null],
    ]);

    // Scoped to one collection: only that one, here and in the other widgets.
    $newsOnly = adminUser(Panel::ACCESS, 'cms.entries.news.view');
    expect(array_column(widgetData('cms.collections', $newsOnly)['rows'], 'title'))->toBe(['News'])
        ->and(array_column(widgetData('cms.recent-edits', $newsOnly)['rows'], 'title'))->toBe(['News item'])
        ->and(widgetData('cms.scheduled', $newsOnly)['rows'])->toBe([]);

    expect(widgetKeys($newsOnly))->toBe(['cms.collections', 'cms.recent-edits', 'cms.scheduled'])
        ->and(widgetKeys(adminUser(Panel::ACCESS)))->toBe([]);
});

it('lists recent edits and what is scheduled', function () {
    $this->travel(-2)->hours();
    dashPost($this->blog, 'Older');
    $this->travelBack();
    dashPost($this->blog, 'Later', now()->addDays(3));
    $soon = dashPost($this->news, 'Soon', now()->addHour());
    $newest = dashPost($this->blog, 'Newest draft', draft: true);

    $editor = adminUser(Panel::ACCESS, 'cms.entries.view');
    $recent = widgetData('cms.recent-edits', $editor)['rows'];
    expect(array_column($recent, 'title'))->toBe(['Newest draft', 'Soon', 'Later', 'Older'])
        ->and(array_column($recent, 'badge'))->toBe(['Draft', 'Scheduled', 'Scheduled', 'Live'])
        ->and($recent[0]['url'])->toBe("cms/collections/blog/entries/{$newest->entry_id}/edit")
        ->and($recent[1]['url'])->toBe("cms/collections/news/entries/{$soon->entry_id}/edit");

    expect(array_column(widgetData('cms.scheduled', $editor)['rows'], 'title'))->toBe(['Soon', 'Later']);
});

it('shows comments waiting and the latest submissions per form permission', function () {
    $post = dashPost($this->blog, 'Commented');
    foreach (['First', 'Second', 'Third'] as $i => $name) {
        Comment::query()->create(['entry_id' => $post->entry_id, 'author_name' => $name, 'body' => "Comment {$i}", 'status' => Comment::PENDING, 'locale' => 'en']);
    }
    Comment::query()->create(['entry_id' => $post->entry_id, 'author_name' => 'Ok', 'body' => 'Fine', 'status' => Comment::APPROVED, 'locale' => 'en']);

    $moderator = adminUser(Panel::ACCESS, 'cms.comments.view');
    $comments = widgetData('cms.comments', $moderator);
    expect($comments['count'])->toBe(3)
        ->and($comments['rows'][0]['title'])->toBe('Third on Commented')
        ->and($comments['rows'][0]['meta'])->toBe('Comment 2')
        ->and(widgetKeys($moderator))->toBe(['cms.comments']);

    $contact = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    $jobs = Form::query()->create(['handle' => 'jobs', 'title' => 'Jobs', 'blueprint' => 'post']);
    Submission::query()->create(['form_id' => $contact->id, 'locale' => 'en', 'data' => ['name' => 'Ana', 'message' => 'Hello']]);
    Submission::query()->create(['form_id' => $jobs->id, 'locale' => 'en', 'data' => ['cv' => ['path' => 'x.pdf'], 'name' => 'Budi']]);

    $contactOnly = adminUser(Panel::ACCESS, 'cms.submissions.contact.view');
    expect(widgetData('cms.submissions', $contactOnly)['rows'])->toHaveCount(1)
        ->and(widgetData('cms.submissions', $contactOnly)['rows'][0])->toMatchArray(['title' => 'Ana', 'meta' => 'Contact', 'url' => 'cms/forms/contact/submissions'])
        ->and(array_column(widgetData('cms.submissions', adminUser(Panel::ACCESS, 'cms.submissions.view'))['rows'], 'title'))->toBe(['Budi', 'Ana']);
});

it('keeps to one query per widget', function () {
    foreach (range(1, 12) as $i) {
        dashPost($i % 2 ? $this->blog : $this->news, "Post {$i}", $i % 3 ? null : now()->addDays($i));
    }
    $post = dashPost($this->blog, 'Commented');
    $signedIn = adminUser();
    foreach (range(1, 6) as $i) {
        Comment::query()->create(['entry_id' => $post->entry_id, 'user_id' => $i % 2 ? $signedIn->id : null, 'author_name' => "Guest {$i}", 'body' => 'Hi', 'status' => Comment::PENDING, 'locale' => 'en']);
    }
    $form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    foreach (range(1, 10) as $i) {
        Submission::query()->create(['form_id' => $form->id, 'locale' => 'en', 'data' => ['name' => "Person {$i}"]]);
    }
    $user = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.comments.view', 'cms.submissions.view');

    $budget = ['cms.collections' => 1, 'cms.recent-edits' => 1, 'cms.scheduled' => 1, 'cms.submissions' => 1, 'cms.comments' => 2];
    foreach ($budget as $key => $queries) {
        widgetData($key, $user); // warms the permission and handle caches
        DB::flushQueryLog();
        DB::enableQueryLog();
        widgetData($key, $user);
        expect(count(DB::getQueryLog()))->toBe($queries, $key);
        DB::disableQueryLog();
    }
});

it('loads the CMS widgets after the page, in their own request', function () {
    dashPost($this->blog, 'Hello');
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));

    get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('widgets.0.key', 'cms.collections')
        ->missing('widget_cms_collections')
        ->loadDeferredProps('widgets-cms', fn (Assert $reload) => $reload
            ->where('widget_cms_collections.data.rows.0.badge', '1 live')
            ->where('widget_cms_recent__edits.data.rows.0.title', 'Hello')));
});
