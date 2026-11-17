<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Comments\PostComment;
use Sunrice\Cms\Actions\Comments\ReplyToComment;
use Sunrice\Cms\Actions\Comments\SetCommentStatus;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\DuplicateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Entries\UnpublishEntry;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Menus\ReorderMenuItems;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\CreateTerm;
use Sunrice\Cms\Actions\Terms\DeleteTerm;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Events;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Comment;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Submission;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

/*
 * The CMS's events and hooks (docs: packages/cms/docs/events.md): fired
 * once from the actions (so the editor, AI agents and imports alike),
 * with the acting user; "before" events change what's saved or refuse
 * it like a validation error, and what they leave is still validated;
 * "after" events fire once the transaction commits.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id'], 'honeypot.enabled' => false]);
    Mail::fake();
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SyncPermissions::class)->handle();
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text', 'required' => true],
        ['handle' => 'summary', 'type' => 'text', 'validation' => ['max:20']],
        ['handle' => 'image', 'type' => 'media'],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'translatable' => true, 'comments' => true]);
    $this->editor = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update', 'cms.entries.publish', 'cms.entries.delete');
    actingAs($this->editor);
});

/**
 * Records the events of these classes as they fire.
 *
 * @param  list<class-string>  $classes
 * @return ArrayObject<int, object>
 */
function hooked(array $classes): ArrayObject
{
    $fired = new ArrayObject;
    Event::listen($classes, function (object $event) use ($fired): void {
        $fired[] = $event;
    });

    return $fired;
}

/** @param ArrayObject<int, object> $fired */
function hookNames(ArrayObject $fired): array
{
    return array_map(fn (object $event): string => class_basename($event), $fired->getArrayCopy());
}

function hookedPost(array $data = ['intro' => 'Hi']): EntryTranslation
{
    return app(CreateEntry::class)->handle(test()->blog, ['title' => 'Hello', 'data' => $data]);
}

it('fires EntrySaving, EntryCreated and EntrySaved once for a new entry and a save, with the user', function () {
    $fired = hooked([Events\EntrySaving::class, Events\EntryCreated::class, Events\EntrySaved::class]);

    $translation = hookedPost();
    expect(hookNames($fired))->toBe(['EntrySaving', 'EntryCreated', 'EntrySaved'])
        ->and($fired[0]->translation)->toBeNull()
        ->and($fired[0]->collection->is($this->blog))->toBeTrue()
        ->and($fired[0]->locale)->toBe('en')
        ->and($fired[0]->data)->toMatchArray(['intro' => 'Hi'])
        ->and($fired[1]->translation->is($translation))->toBeTrue()
        ->and($fired[2]->created)->toBeTrue()
        ->and($fired[2]->changed)->toBe(['title', 'slug', 'intro'])
        ->and($fired[2]->user?->getAuthIdentifier())->toBe($this->editor->id)
        ->and($fired[2]->source)->toBe('admin');

    $fired->exchangeArray([]);
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Hello', 'data' => ['summary' => 'Short']])->assertSessionHasNoErrors();
    expect(hookNames($fired))->toBe(['EntrySaving', 'EntrySaved'])
        ->and($fired[0]->translation?->is($translation))->toBeTrue()
        ->and($fired[0]->data)->toMatchArray(['intro' => 'Hi', 'summary' => 'Short'])
        ->and($fired[1]->created)->toBeFalse()
        ->and($fired[1]->changed)->toBe(['summary']);

    // Nothing changed: asked, but not saved.
    $fired->exchangeArray([]);
    app(SaveDraft::class)->handle($translation->fresh(), ['data' => ['summary' => 'Short']]);
    expect(hookNames($fired))->toBe(['EntrySaving']);
});

it('saves what an EntrySaving listener changes, still validated', function () {
    Event::listen(Events\EntrySaving::class, function (Events\EntrySaving $event): void {
        $event->data['summary'] = mb_substr((string) $event->data['intro'], 0, 10);
        $event->title = mb_strtoupper((string) $event->title);
        $event->data['unknown'] = 'dropped';
    });

    $translation = hookedPost(['intro' => 'A long introduction']);
    expect($translation->draft_title)->toBe('HELLO')
        ->and($translation->draft_data)->toMatchArray(['intro' => 'A long introduction', 'summary' => 'A long int'])
        ->and($translation->draft_data)->not->toHaveKey('unknown');

    // A listener can't store what the field's rules refuse.
    Event::listen(Events\EntrySaving::class, function (Events\EntrySaving $event): void {
        $event->data['summary'] = str_repeat('x', 30);
    });
    expect(fn () => app(SaveDraft::class)->handle($translation, ['data' => ['intro' => 'Changed']]))
        ->toThrow(ValidationException::class);
    expect($translation->fresh()?->draft_data['intro'])->toBe('A long introduction');
});

it('stops a save an EntrySaving listener refuses, as a validation error everywhere', function () {
    $translation = hookedPost();
    $saved = hooked([Events\EntrySaved::class, Events\EntryCreated::class]);
    Event::listen(Events\EntrySaving::class, function (Events\EntrySaving $event): void {
        if (str_contains((string) ($event->data['summary'] ?? ''), 'spam')) {
            throw ValidationException::withMessages(['data.summary' => 'No spam here.']);
        }
    });

    expect(fn () => app(CreateEntry::class)->handle($this->blog, ['title' => 'Other', 'data' => ['intro' => 'x', 'summary' => 'spam']]))
        ->toThrow(ValidationException::class, 'No spam here.');
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Hello', 'data' => ['summary' => 'spam']])
        ->assertSessionHasErrors(['data.summary' => 'No spam here.']);

    // An AI agent gets it as the tool's error.
    $role = Role::findOrCreate('hook-agent', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'cms.entries.view', 'cms.entries.update']);
    $agent = User::query()->create(['name' => 'Agent', 'email' => uniqid().'@example.com', 'password' => 'x'])->assignRole($role);
    $token = AiToken::issue((string) $agent->id, 'Claude')[1];
    $response = postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'cms_save_entry', 'arguments' => ['id' => $translation->entry_id, 'data' => ['summary' => 'spam']]]], ['Authorization' => "Bearer {$token}"]);
    expect($response->json('result.isError'))->toBeTrue()
        ->and($response->json('result.content.0.text'))->toContain('No spam here.');

    expect(Entry::query()->count())->toBe(1)
        ->and($translation->fresh()?->draft_data['summary'] ?? null)->toBeNull()
        ->and($saved->count())->toBe(0);
});

it('tells listeners an AI agent saved, as its token\'s user', function () {
    $translation = hookedPost();
    $fired = hooked([Events\EntrySaved::class]);
    $role = Role::findOrCreate('hook-agent', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'cms.entries.view', 'cms.entries.update']);
    $agent = User::query()->create(['name' => 'Agent', 'email' => uniqid().'@example.com', 'password' => 'x'])->assignRole($role);
    $token = AiToken::issue((string) $agent->id, 'Claude')[1];

    postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'cms_save_entry', 'arguments' => ['id' => $translation->entry_id, 'data' => ['summary' => 'By AI']]]], ['Authorization' => "Bearer {$token}"])
        ->assertOk();

    expect($fired->count())->toBe(1)
        ->and($fired[0]->source)->toBe('ai')
        ->and($fired[0]->user?->getAuthIdentifier())->toBe($agent->id);
});

it('lets an EntryPublishing listener refuse or change what is published', function () {
    $translation = hookedPost();
    $published = hooked([Events\EntryPublished::class]);
    Event::listen(Events\EntryPublishing::class, function (Events\EntryPublishing $event): void {
        if (($event->data['image'] ?? null) === null && ($event->data['summary'] ?? null) === null) {
            throw ValidationException::withMessages(['data.image' => 'Add an image before publishing.']);
        }
        $event->data['summary'] = 'Published summary';
    });

    post("/admin/cms/entries/{$translation->entry_id}/publish")->assertSessionHasErrors(['data.image' => 'Add an image before publishing.']);
    expect($translation->fresh()?->isPublished())->toBeFalse()
        ->and($published->count())->toBe(0);

    app(SaveDraft::class)->handle($translation, ['data' => ['summary' => 'Draft summary']]);
    app(PublishEntry::class)->handle($translation->fresh() ?? $translation);
    $translation->refresh();
    expect($translation->isPublished())->toBeTrue()
        ->and($translation->data['summary'])->toBe('Published summary')
        ->and($translation->draft_data['summary'])->toBe('Published summary')
        ->and($published->count())->toBe(1)
        ->and($published[0]->user?->getAuthIdentifier())->toBe($this->editor->id)
        ->and($published[0]->scheduled)->toBeFalse();
});

it('fires EntryDeleting before the trash and before deleting for good, and lets it refuse', function () {
    $translation = hookedPost();
    $fired = hooked([Events\EntryDeleting::class, Events\EntryTrashed::class, Events\EntryDeleted::class]);
    $refuse = true;
    Event::listen(Events\EntryDeleting::class, function (Events\EntryDeleting $event) use (&$refuse): void {
        if ($refuse && ! $event->permanent) {
            throw ValidationException::withMessages(['entry' => 'Keep it.']);
        }
    });

    expect(fn () => app(TrashEntry::class)->handle($translation->entry))->toThrow(ValidationException::class, 'Keep it.');
    expect($translation->entry->fresh()?->trashed())->toBeFalse();

    $refuse = false;
    app(TrashEntry::class)->handle($translation->entry);
    app(ForceDeleteEntry::class)->handle($translation->entry);

    expect(array_map(fn (object $event): string => class_basename($event).(property_exists($event, 'permanent') ? ($event->permanent ? ':permanent' : ':trash') : ''), $fired->getArrayCopy()))
        ->toBe(['EntryDeleting:trash', 'EntryDeleting:trash', 'EntryTrashed', 'EntryDeleting:permanent', 'EntryDeleted'])
        ->and(Entry::withTrashed()->count())->toBe(0);
});

it('fires EntryTranslationCreated for a new language and EntryCreated for a copy', function () {
    $translation = hookedPost();
    $fired = hooked([Events\EntryTranslationCreated::class, Events\EntryCreated::class, Events\EntrySaving::class]);

    $indonesian = app(CreateTranslation::class)->handle($translation->entry, 'id');
    $copy = app(DuplicateEntry::class)->handle($translation->entry);

    expect(hookNames($fired))->toBe(['EntryTranslationCreated', 'EntryCreated'])
        ->and($fired[0]->translation->is($indonesian))->toBeTrue()
        ->and($fired[1]->translation->entry_id)->toBe($copy->id)
        ->and($fired[1]->copiedFrom?->is($translation->entry))->toBeTrue();
});

it('fires the after events once the transaction commits, and none for one rolled back', function () {
    $fired = hooked([Events\EntryCreated::class, Events\EntrySaved::class, Events\EntryPublished::class, Events\EntryUnpublished::class]);

    DB::beginTransaction();
    $translation = hookedPost();
    app(PublishEntry::class)->handle($translation);
    expect($fired->count())->toBe(0);
    DB::rollBack();
    expect($fired->count())->toBe(0);

    DB::transaction(function () use ($fired): void {
        $translation = hookedPost();
        app(PublishEntry::class)->handle($translation);
        app(UnpublishEntry::class)->handle($translation);
        expect($fired->count())->toBe(0);
    });
    expect(hookNames($fired))->toBe(['EntryCreated', 'EntrySaved', 'EntryPublished', 'EntryUnpublished']);
});

it('fires TermSaving and TermSaved once per save of a term, and lets listeners change or refuse it', function () {
    Blueprint::query()->create(['handle' => 'topic', 'title' => 'Topic', 'fields' => [['handle' => 'blurb', 'type' => 'text', 'validation' => ['max:20']]]]);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}', 'blueprint' => 'topic']);
    $fired = hooked([Events\TermSaving::class, Events\TermSaved::class]);
    Event::listen(Events\TermSaving::class, function (Events\TermSaving $event): void {
        if ($event->title === 'Forbidden') {
            throw ValidationException::withMessages(['title' => 'Not that name.']);
        }
        if (is_array($event->data) && ($event->data['blurb'] ?? null) === null) {
            $event->data['blurb'] = 'Filled in';
        }
    });

    $term = app(CreateTerm::class)->handle($topics, ['title' => 'Travel', 'data' => []]);
    expect(hookNames($fired))->toBe(['TermSaving', 'TermSaved'])
        ->and($fired[0]->term)->toBeNull()
        ->and($fired[1]->created)->toBeTrue()
        ->and($fired[1]->term->is($term))->toBeTrue()
        ->and($term->translations()->sole()->data)->toMatchArray(['blurb' => 'Filled in']);

    // The term editor: a new name and fields, once.
    $fired->exchangeArray([]);
    app(SaveTermContent::class)->handle($term, ['title' => 'Trips', 'data' => ['blurb' => 'Short']]);
    expect(hookNames($fired))->toBe(['TermSaving', 'TermSaved'])
        ->and($fired[0]->data)->toMatchArray(['blurb' => 'Short'])
        ->and($fired[1]->created)->toBeFalse()
        ->and($term->title('en'))->toBe('Trips');

    // A rename alone: no fields to change.
    $fired->exchangeArray([]);
    app(SaveTerm::class)->handle($topics, ['title' => 'Journeys'], $term);
    expect(hookNames($fired))->toBe(['TermSaving', 'TermSaved'])
        ->and($fired[0]->data)->toBeNull();

    $fired->exchangeArray([]);
    expect(fn () => app(SaveTerm::class)->handle($topics, ['title' => 'Forbidden']))->toThrow(ValidationException::class, 'Not that name.')
        ->and(fn () => app(SaveTermContent::class)->handle($term, ['data' => ['blurb' => str_repeat('x', 30)]]))->toThrow(ValidationException::class);
    expect(Term::query()->count())->toBe(1)
        ->and($term->translations()->sole()->data['blurb'])->toBe('Short')
        ->and(hookNames($fired))->toBe(['TermSaving', 'TermSaving']);
});

it('fires TermDeleting and TermDeleted for the trash and for good', function () {
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $term = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
    $fired = hooked([Events\TermDeleting::class, Events\TermDeleted::class]);

    app(TrashTerm::class)->handle($term);
    app(DeleteTerm::class)->handle($term);

    expect(array_map(fn (object $event): string => class_basename($event).($event->permanent ? ':permanent' : ':trash'), $fired->getArrayCopy()))
        ->toBe(['TermDeleting:trash', 'TermDeleted:trash', 'TermDeleting:permanent', 'TermDeleted:permanent']);
});

it('fires MenuSaved and GlobalsSaved', function () {
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'phone', 'type' => 'text']]]);
    $set = GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    $fired = hooked([Events\MenuSaved::class, Events\GlobalsSaved::class]);

    app(SaveMenu::class)->handle($menu, [['url' => 'https://example.com', 'labels' => ['en' => 'One']], ['url' => 'https://example.com/two', 'labels' => ['en' => 'Two']]]);
    $ids = $menu->items()->orderBy('sort_order')->pluck('id')->all();
    app(ReorderMenuItems::class)->handle($menu, array_reverse($ids));
    app(SaveGlobals::class)->handle($set, ['phone' => '+62 21 555'], 'id');

    expect(hookNames($fired))->toBe(['MenuSaved', 'MenuSaved', 'GlobalsSaved'])
        ->and($fired[0]->menu->is($menu))->toBeTrue()
        ->and($fired[2]->locale)->toBe('id')
        ->and($fired[2]->changed)->toBe(['phone'])
        ->and($fired[2]->values->data)->toMatchArray(['phone' => '+62 21 555']);
});

it('lets FormSubmitting listeners add to a submission or refuse it on the form', function () {
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [
        ['handle' => 'email', 'type' => 'text', 'required' => true, 'validation' => ['email']],
        ['handle' => 'message', 'type' => 'textarea', 'required' => true],
        ['handle' => 'source', 'type' => 'text'],
    ]]);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact', 'message' => 'Thanks!']);
    auth()->logout();
    $fired = hooked([Events\FormSubmitted::class]);
    Event::listen(Events\FormSubmitting::class, function (Events\FormSubmitting $event): void {
        if (str_ends_with((string) ($event->data['email'] ?? ''), '@blocked.test')) {
            throw ValidationException::withMessages(['data.email' => 'Use your work email.']);
        }
        if (($event->data['message'] ?? null) === 'bad') {
            $event->data['email'] = 'not an email';
        }
        $event->data['source'] = 'website';
        $event->data['other'] = 'dropped';
    });

    from('/contact')->post('/forms/contact', ['email' => 'ana@blocked.test', 'message' => 'Hi'])
        ->assertRedirect('/contact#form-contact-errors')->assertSessionHasErrors(['email' => 'Use your work email.'], errorBag: 'contact');
    postJson('/forms/contact', ['email' => 'ana@example.com', 'message' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    expect(Submission::query()->count())->toBe(0)->and($fired->count())->toBe(0);

    postJson('/forms/contact', ['email' => 'ana@example.com', 'message' => 'Hi'])->assertCreated();
    $submission = Submission::query()->sole();
    expect($submission->data)->toEqual(['email' => 'ana@example.com', 'message' => 'Hi', 'source' => 'website'])
        ->and($fired->count())->toBe(1)
        ->and($fired[0]->submission->is($submission))->toBeTrue()
        ->and($fired[0]->source)->toBe('site')
        ->and($fired[0]->user)->toBeNull();
});

it('lets CommentPosting listeners mark a comment spam or refuse it, and tells about posts and moderation', function () {
    $post = app(PublishEntry::class)->handle(hookedPost());
    auth()->logout();
    $fired = hooked([Events\CommentPosted::class, Events\CommentStatusChanged::class]);
    Event::listen(Events\CommentPosting::class, function (Events\CommentPosting $event): void {
        if (str_contains($event->body, 'casino')) {
            $event->status = Comment::SPAM;
        }
        if (str_contains($event->body, 'rude')) {
            throw ValidationException::withMessages(['body' => 'Be kind.']);
        }
        if ($event->body === 'empty me') {
            $event->body = ' ';
        }
    });
    $guest = ['author_name' => 'Rina', 'author_email' => 'rina@example.com'];

    from('/blog/hello')->post("/comments/{$post->entry_id}", ['body' => 'You are rude', ...$guest])->assertSessionHasErrors(['body' => 'Be kind.'], errorBag: 'comments');
    expect(fn () => app(PostComment::class)->handle($post->entry, ['body' => 'empty me', ...$guest]))->toThrow(ValidationException::class);
    expect(Comment::query()->count())->toBe(0)->and($fired->count())->toBe(0);

    $spam = app(PostComment::class)->handle($post->entry, ['body' => 'Best casino', ...$guest], null, 'en', '10.0.0.1');
    postJson("/comments/{$post->entry_id}", ['body' => 'Nice post', ...$guest])->assertCreated();
    $nice = Comment::query()->where('body', 'Nice post')->sole();
    expect($spam->status)->toBe(Comment::SPAM)
        ->and(hookNames($fired))->toBe(['CommentPosted', 'CommentPosted'])
        ->and($fired[1]->comment->is($nice))->toBeTrue()
        ->and($fired[1]->source)->toBe('site');

    actingAs($this->editor);
    $fired->exchangeArray([]);
    app(SetCommentStatus::class)->handle($nice, Comment::APPROVED);
    app(ReplyToComment::class)->handle($nice, 'Thanks!', $this->editor);
    expect(hookNames($fired))->toBe(['CommentStatusChanged', 'CommentPosted'])
        ->and($fired[0]->previous)->toBe(Comment::PENDING)
        ->and($fired[0]->comment->status)->toBe(Comment::APPROVED)
        ->and($fired[1]->source)->toBe('admin')
        ->and($fired[1]->user?->getAuthIdentifier())->toBe($this->editor->id);
});

it('lets every event say who did it', function () {
    $fired = hooked([Events\EntrySaved::class]);
    auth()->logout();

    hookedPost();

    expect($fired[0]->user)->toBeNull()
        ->and($fired[0]->source)->toBe('system');
});
