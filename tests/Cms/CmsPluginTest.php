<?php

declare(strict_types=1);

use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Actions\Users\DeleteUser;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Comment;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Revision;
use Workbench\App\Models\User;

it('is a panel plugin', function () {
    $panel = Panel::make()->plugin(CmsPlugin::make());
    $panel->boot();

    expect($panel->getPlugin('cms'))->toBeInstanceOf(CmsPlugin::class)
        ->and($panel->isPrivilegedPermission('cms.settings.code'))->toBeTrue()
        ->and($panel->isPrivilegedPermission('cms.blueprints.update'))->toBeTrue()
        ->and($panel->isPrivilegedPermission('cms.fieldsets.create'))->toBeTrue()
        ->and($panel->isPrivilegedPermission('cms.blueprints.view'))->toBeFalse()
        ->and($panel->isPrivilegedPermission('cms.entries.publish'))->toBeFalse()
        ->and(array_keys($panel->getUserContent()))->toBe(['media', 'cms.entries', 'cms.comments']);
});

it('registers its developer docs and its user guide for the Help pages', function () {
    $panel = Panel::make()->plugin(CmsPlugin::make());
    $panel->boot();
    $root = dirname(__DIR__, 2);

    expect($panel->getDocs()['cms']['directory'])->toBe($root.'/packages/cms/docs')
        ->and($panel->getGuides()['website'])->toBe(['title' => 'Website', 'directory' => $root.'/packages/cms/guide']);
});

it('reassigns, keeps or trashes a deleted user\'s entries and comments', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SyncPermissions::class)->handle();
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => []]);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post']);
    $ana = User::query()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x']);
    $budi = User::query()->create(['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'x']);
    $rina = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'x']);

    $entry = function (User $author, string $title) use ($blog): Entry {
        $entry = Entry::query()->create(['collection_id' => $blog->id, 'author_id' => $author->id]);
        $translation = EntryTranslation::query()->create(['entry_id' => $entry->id, 'collection_id' => $blog->id, 'locale' => 'en', 'draft_title' => $title, 'draft_slug' => strtolower($title), 'draft_data' => [], 'status' => 'draft']);
        Revision::query()->create(['entry_translation_id' => $translation->id, 'user_id' => $author->id, 'title' => $title, 'slug' => strtolower($title), 'data' => []]);

        return $entry;
    };
    $comment = fn (User $author, Entry $on) => Comment::query()->create(['entry_id' => $on->id, 'user_id' => $author->id, 'body' => 'Hi', 'status' => 'approved', 'locale' => 'en']);

    $one = $entry($ana, 'One');
    $two = $entry($ana, 'Two');
    $trashed = $entry($ana, 'Old');
    $trashed->delete();
    $three = $entry($budi, 'Three');
    $comment($ana, $three);
    $comment($budi, $one);

    $delete = app(DeleteUser::class);
    expect($delete->labels())->toBe(['uploads', 'entries', 'comments'])
        ->and($delete->counts($ana))->toBe(['entries' => 2, 'comments' => 1]);

    // Reassign: entries (trashed ones too) and comments move; revisions never name the deleted user.
    expect($delete->handle($ana, DeleteUser::REASSIGN, $rina))->toBe(['entries' => 2, 'comments' => 1]);
    expect(Entry::withTrashed()->where('author_id', $rina->id)->pluck('id')->all())->toEqualCanonicalizing([$one->id, $two->id, $trashed->id])
        ->and(Comment::query()->where('user_id', $rina->id)->count())->toBe(1)
        ->and(Revision::query()->whereNull('user_id')->count())->toBe(3)
        ->and(Revision::query()->where('user_id', $ana->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($ana->id)->exists())->toBeFalse();

    // Delete: entries and comments go to the trash, without an author; comments keep the name and email they were signed with.
    $delete->handle($budi, DeleteUser::DELETE);
    expect($three->fresh()->trashed())->toBeTrue()
        ->and($three->fresh()->author_id)->toBeNull()
        ->and(Comment::withTrashed()->where('entry_id', $one->id)->sole())->trashed()->toBeTrue()->user_id->toBeNull()->author_name->toBe('Budi')->author_email->toBe('budi@example.com');

    // Keep: everything stays, without an owner (the comment Rina inherited is now signed with her name).
    $delete->handle($rina, DeleteUser::KEEP);
    expect(Entry::withTrashed()->whereNotNull('author_id')->count())->toBe(0)
        ->and($one->fresh()->trashed())->toBeFalse()
        ->and(Comment::query()->where('entry_id', $three->id)->sole())->user_id->toBeNull()->author_name->toBe('Rina');
});
