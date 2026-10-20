<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Comments\PostComment;
use Sunrice\Cms\Actions\Comments\SetCommentStatus;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Mail\CommentPosted;
use Sunrice\Cms\Mail\CommentReplied;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Comment;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Settings\CommentSettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;
use function Pest\Laravel\travel;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['honeypot.enabled' => false]);
    Mail::fake();
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => []]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'comments' => true]);
    $this->post = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello']));
});

function guest(array $input = []): array
{
    return [...['body' => 'Nice post', 'author_name' => 'Rina', 'author_email' => 'rina@example.com'], ...$input];
}

function commentOn(EntryTranslation $post, array $input = [], string $ip = '10.0.0.1'): Comment
{
    return app(PostComment::class)->handle($post->entry, guest($input), null, 'en', $ip);
}

function commentSettings(array $values): void
{
    app(CommentSettings::class)->fill($values)->save();
}

it('holds a commenter\'s first comment, then trusts them', function () {
    $first = commentOn($this->post);
    expect($first->status)->toBe(Comment::PENDING)
        ->and($first->ip_hash)->toHaveLength(64)->not->toContain('10.0.0.1')
        ->and(commentOn($this->post)->status)->toBe(Comment::PENDING);

    app(SetCommentStatus::class)->handle($first, Comment::APPROVED);
    expect(commentOn($this->post, ['author_email' => 'RINA@example.com'])->status)->toBe(Comment::APPROVED)
        ->and(commentOn($this->post, ['author_email' => 'other@example.com'])->status)->toBe(Comment::PENDING)
        // An email alone proves nothing: the same name and address (its hash) too, else held again.
        ->and(commentOn($this->post, [], '10.0.0.2')->status)->toBe(Comment::PENDING)
        ->and(commentOn($this->post, ['author_name' => 'Rina S.'])->status)->toBe(Comment::PENDING)
        ->and(app(PostComment::class)->handle($this->post->entry, guest(), null, 'en', null)->status)->toBe(Comment::PENDING);

    commentSettings(['moderation' => CommentSettings::HOLD_ALL]);
    expect(commentOn($this->post)->status)->toBe(Comment::PENDING);
    commentSettings(['moderation' => CommentSettings::HOLD_NONE]);
    expect(commentOn($this->post, ['author_email' => 'new@example.com'])->status)->toBe(Comment::APPROVED);

    // People who may moderate are published at once, as themselves.
    commentSettings(['moderation' => CommentSettings::HOLD_ALL]);
    $editor = adminUser(Panel::ACCESS, 'cms.comments.moderate');
    $own = app(PostComment::class)->handle($this->post->entry, ['body' => 'Thanks!'], $editor, 'en');
    expect($own->status)->toBe(Comment::APPROVED)->and($own->authorName())->toBe('Rina')->and($own->author_email)->toBeNull();
});

it('takes comments only where they\'re open', function () {
    $closed = fn () => commentOn($this->post);

    $this->post->entry->update(['comments_closed' => true]);
    expect($closed)->toThrow(ValidationException::class, 'Comments are closed.');
    $this->post->entry->update(['comments_closed' => false]);

    commentSettings(['closeAfterDays' => 10]);
    travel(11)->days();
    expect($this->post->entry->fresh()->acceptsComments())->toBeFalse();
    commentSettings(['closeAfterDays' => null]);

    $this->blog->update(['comments' => false]);
    expect($this->post->entry->fresh()->acceptsComments())->toBeFalse();
    $this->blog->update(['comments' => true]);

    $draft = app(CreateEntry::class)->handle($this->blog, ['title' => 'Draft']);
    expect($draft->entry->acceptsComments())->toBeFalse();

    commentSettings(['signedInOnly' => true]);
    expect(fn () => commentOn($this->post->fresh()))->toThrow(ValidationException::class, 'Sign in to comment.');
});

it('checks what visitors send', function () {
    $errors = function (array $input): array {
        try {
            app(PostComment::class)->handle($this->post->entry, $input, null, 'en');
        } catch (ValidationException $e) {
            return array_keys($e->errors());
        }

        return [];
    };
    $pending = commentOn($this->post);

    expect($errors(['body' => '']))->toBe(['body', 'author_name', 'author_email'])
        ->and($errors(guest(['author_email' => 'nope'])))->toBe(['author_email'])
        ->and($errors(guest(['body' => str_repeat('a', 5001)])))->toBe(['body'])
        ->and($errors(guest(['parent_id' => $pending->id])))->toBe(['parent_id']) // not approved
        ->and($errors(guest(['parent_id' => 9999])))->toBe(['parent_id']);
});

it('shows approved comments with nested replies, safely', function () {
    commentSettings(['moderation' => CommentSettings::HOLD_NONE, 'depth' => 2]);
    $a = commentOn($this->post, ['body' => "First <script>alert(1)</script>\nsee https://example.com/x?a=1&b=2."]);
    $b = commentOn($this->post, ['body' => 'A reply', 'parent_id' => $a->id, 'author_name' => 'Budi']);
    commentOn($this->post, ['body' => 'Deeper', 'parent_id' => $b->id]);
    $held = commentOn($this->post, ['body' => 'Held']);
    app(SetCommentStatus::class)->handle($held, Comment::PENDING);

    $html = (string) get('/blog/hello')->assertOk()->getContent();
    expect($html)->toContain('3 comments')
        ->toContain('First &lt;script&gt;alert(1)&lt;/script&gt;<br>')
        ->toContain('<a href="https://example.com/x?a=1&amp;b=2" rel="nofollow ugc noopener" target="_blank">https://example.com/x?a=1&amp;b=2</a>.')
        ->not->toContain('Held')
        ->toContain('id="comment-form"')
        ->toContain('?reply_to='.$a->id);
    // Two levels: the deeper reply lines up beside its parent.
    expect(substr_count($html, 'class="sunrice-comment-replies"'))->toBe(1);

    $reply = (string) get('/blog/hello?reply_to='.$a->id)->getContent();
    expect($reply)->toContain('name="parent_id" value="'.$a->id.'"')->toContain('Replying to Rina');
});

it('posts from the site, back to the thread, with errors and the captcha', function () {
    commentSettings(['perMinute' => 10]);
    from('/blog/hello')->post("/comments/{$this->post->entry_id}", guest())
        ->assertRedirect('/blog/hello#comments')->assertSessionHas('sunrice-cms.comment', 'Thank you! Your comment will appear once it is approved.');
    from('/blog/hello')->post("/comments/{$this->post->entry_id}", ['body' => ''])
        ->assertRedirect('/blog/hello#comment-form')->assertSessionHasErrors(['body', 'author_name'], null, 'comments');
    postJson("/comments/{$this->post->entry_id}", guest(['author_email' => 'x@example.com']))->assertCreated()->assertJson(['status' => 'pending']);

    config(['sunrice-admin.captcha' => ['provider' => 'turnstile', 'site_key' => 'k', 'secret_key' => 's']]);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    commentSettings(['captcha' => true]);
    from('/blog/hello')->post("/comments/{$this->post->entry_id}", guest())->assertSessionHasErrors(['_captcha'], null, 'comments');
    expect(get('/blog/hello')->getContent())->toContain('class="cf-turnstile"');
    expect(Comment::query()->count())->toBe(2);
});

it('limits comments per visitor', function () {
    commentSettings(['perMinute' => 2]);
    postJson("/comments/{$this->post->entry_id}", guest())->assertCreated();
    postJson("/comments/{$this->post->entry_id}", guest())->assertCreated();
    postJson("/comments/{$this->post->entry_id}", guest())->assertStatus(429);
});

it('emails moderators, and commenters about replies once, with a way to stop', function () {
    app(SyncPermissions::class)->handle();
    $moderator = adminUser(Panel::ACCESS, 'cms.comments.moderate');
    $moderator->update(['email' => 'mod@example.com']);
    commentSettings(['notifyEmails' => "editor@example.com\nnot-an-address"]);

    $question = commentOn($this->post, ['notify_replies' => '1']);
    // One email each: moderators don't see each other's addresses.
    Mail::assertQueued(CommentPosted::class, 2);
    Mail::assertQueued(CommentPosted::class, fn (CommentPosted $mail) => $mail->hasTo('mod@example.com') && ! $mail->hasTo('editor@example.com'));
    Mail::assertQueued(CommentPosted::class, fn (CommentPosted $mail) => $mail->hasTo('editor@example.com') && ! $mail->hasTo('mod@example.com'));
    app(SetCommentStatus::class)->handle($question, Comment::APPROVED);

    $answer = commentOn($this->post, ['body' => 'Answer', 'parent_id' => $question->id, 'author_email' => 'budi@example.com']);
    Mail::assertNotQueued(CommentReplied::class); // held
    app(SetCommentStatus::class)->handle($answer, Comment::APPROVED);
    app(SetCommentStatus::class)->handle($answer, Comment::PENDING);
    app(SetCommentStatus::class)->handle($answer, Comment::APPROVED);
    Mail::assertQueued(CommentReplied::class, 1);
    Mail::assertQueued(CommentReplied::class, fn (CommentReplied $mail) => $mail->hasTo('rina@example.com'));

    // Replying to yourself sends nothing.
    commentOn($this->post, ['body' => 'Me again', 'parent_id' => $question->id]);
    Mail::assertQueued(CommentReplied::class, 1);

    $unsubscribe = URL::signedRoute('sunrice.cms.comments.unsubscribe', ['comment' => $question->id]);
    get(str_replace('signature=', 'signature=x', $unsubscribe))->assertForbidden();
    get($unsubscribe)->assertOk();
    expect($question->fresh()?->notify_replies)->toBeFalse();

    // Guests can't sign anyone up when nothing is held.
    commentSettings(['moderation' => CommentSettings::HOLD_NONE]);
    expect(commentOn($this->post, ['notify_replies' => '1', 'author_email' => 'z@example.com'])->notify_replies)->toBeFalse();
});

it('signs a guest up for reply emails only when a moderator saw them ask', function () {
    $first = commentOn($this->post);
    app(SetCommentStatus::class)->handle($first, Comment::APPROVED);
    // Trusted, so published at once: no moderator sees the address, and the comment that earned the trust didn't ask.
    expect(commentOn($this->post, ['notify_replies' => '1'])->notify_replies)->toBeFalse();

    $asked = commentOn($this->post, ['notify_replies' => '1', 'author_name' => 'Rina S.']);
    expect($asked->status)->toBe(Comment::PENDING)->and($asked->notify_replies)->toBeTrue();
    app(SetCommentStatus::class)->handle($asked, Comment::APPROVED);
    expect(commentOn($this->post, ['notify_replies' => '1', 'author_name' => 'Rina S.'])->notify_replies)->toBeTrue()
        ->and(commentOn($this->post, ['author_name' => 'Rina S.'])->notify_replies)->toBeFalse();
});

it('moderates in the admin, behind its permissions', function () {
    $pending = commentOn($this->post, ['body' => 'Is this on?']);
    $spam = commentOn($this->post, ['body' => 'Buy pills', 'author_email' => 'bot@example.com']);
    app(SetCommentStatus::class)->handle($spam, Comment::SPAM);

    actingAs(adminUser(Panel::ACCESS));
    get('/admin/cms/comments')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.comments.view'));
    get('/admin/cms/comments')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Comments/Index', shouldExist: false)
        ->where('counts', ['all' => 1, 'pending' => 1, 'approved' => 0, 'spam' => 1, 'trash' => 0])
        ->where('comments.data.0.body', 'Is this on?')
        ->where('comments.data.0.entry.title', 'Hello')
        ->where('can', ['moderate' => false, 'delete' => false]));
    get('/admin/cms/comments?status=spam&search=PILLS')->assertInertia(fn (Assert $page) => $page->where('comments.total', 1));
    post("/admin/cms/comments/{$pending->id}/status", ['status' => 'approved'])->assertForbidden();
    post('/admin/cms/comments/bulk', ['action' => 'approve', 'ids' => [$pending->id]])->assertForbidden();

    actingAs($mod = adminUser(Panel::ACCESS, 'cms.comments.view', 'cms.comments.moderate', 'cms.comments.delete'));
    post("/admin/cms/comments/{$pending->id}/reply", ['body' => 'Yes, it is.'])->assertSessionHasNoErrors();
    expect($pending->fresh()?->status)->toBe(Comment::APPROVED)
        ->and(Comment::query()->where('parent_id', $pending->id)->sole()->only(['status', 'user_id']))->toBe(['status' => Comment::APPROVED, 'user_id' => $mod->id]);
    put("/admin/cms/comments/{$pending->id}", ['body' => 'Is this thing on?'])->assertSessionHasNoErrors();
    expect($pending->fresh()?->body)->toBe('Is this thing on?');

    delete("/admin/cms/comments/{$pending->id}")->assertSessionHasNoErrors();
    expect(Comment::onlyTrashed()->count())->toBe(1);
    post("/admin/cms/comments/{$pending->id}/restore");
    post('/admin/cms/comments/bulk', ['action' => 'trash', 'ids' => [$pending->id, $spam->id]]);
    post('/admin/cms/comments/bulk', ['action' => 'delete', 'ids' => [$pending->id]]);
    expect(Comment::withTrashed()->find($pending->id))->toBeNull()
        ->and(Comment::query()->sole()->parent_id)->toBeNull(); // the reply moved up
});

it('prunes old spam and deletes an entry\'s comments with it', function () {
    $spam = commentOn($this->post);
    app(SetCommentStatus::class)->handle($spam, Comment::SPAM);
    commentOn($this->post, ['author_email' => 'other@example.com']);
    travel(31)->days();

    artisan('model:prune', ['--model' => [Comment::class]])->assertSuccessful();
    expect(Comment::query()->count())->toBe(1);

    app(TrashEntry::class)->handle($this->post->entry);
    app(ForceDeleteEntry::class)->handle($this->post->entry);
    expect(Comment::withTrashed()->count())->toBe(0);
});
