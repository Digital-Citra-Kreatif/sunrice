<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\RenamePermissions;
use Sunrice\Admin\Actions\Permissions\SeedRoles;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Admin\HandleField;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\Access;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => []]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post']);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'blueprint' => 'post']);
});

function scopedDraft(Collection $collection, ?Model $author = null): EntryTranslation
{
    if ($author !== null) {
        actingAs($author);
    }

    return app(CreateEntry::class)->handle($collection, ['title' => 'Post '.uniqid()]);
}

it('has permissions for each collection, taxonomy and form, kept in step with them', function () {
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);

    expect(Permission::query()->pluck('name')->all())->toContain('cms.entries.blog.view', 'cms.entries.news.publish', 'cms.terms.tags.view', 'cms.terms.tags.create', 'cms.terms.tags.update', 'cms.terms.tags.delete', 'cms.submissions.contact.view', 'cms.submissions.contact.delete', 'cms.entries.blog.export', 'cms.submissions.contact.export')
        ->and(app(Panel::class)->getPermissions()['cms.entries.blog.update']->group)->toBe('Entries: Blog');

    $form->delete();
    $this->news->forceDelete();
    expect(Permission::query()->pluck('name')->all())->not->toContain('cms.submissions.contact.view')->not->toContain('cms.entries.news.view')
        ->toContain('cms.entries.blog.view');
});

it('names the groups of scoped permissions in the panel\'s language', function () {
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    $permissions = app(Panel::class)->getPermissions();
    app()->setLocale('id');

    expect($permissions['cms.entries.blog.update']->saidGroup())->toBe('Entri: Blog')
        ->and($permissions['cms.terms.tags.view']->saidGroup())->toBe('Term: Tags')
        ->and($permissions['cms.submissions.contact.view']->saidGroup())->toBe('Kiriman: Contact')
        // In English, as before.
        ->and($permissions['cms.entries.blog.update']->group)->toBe('Entries: Blog');

    app(SyncPermissions::class)->handle();
    actingAs(adminUser()->assignRole(Role::findOrCreate('super-admin', 'web')));
    config(['sunrice-admin.locale' => 'id']);
    get('/admin/roles/create')->assertInertia(function (Assert $page) {
        $fields = collect($page->toArray()['props']['sections'] ?? [])->flatMap(fn ($section) => $section['fields']);
        expect(collect($fields->firstWhere('name', 'permissions')['options'] ?? [])->pluck('group')->all())->toContain('Entri: Blog');
    });
});

it('gives the content permissions without a column of their own short labels for the role editor', function () {
    $permissions = app(Panel::class)->getPermissions();

    expect(array_map(fn (string $name) => $permissions[$name]->short, ['cms.entries.translate', 'cms.entries.view-drafts', 'cms.homepage']))
        ->toBe(['Translate', 'See drafts on the site', 'Choose the homepage'])
        // Those in a column (and their own variants, under it) need none.
        ->and($permissions['cms.entries.update-own']->short)->toBeNull();
});

it('lets someone work in one collection only', function () {
    $blog = scopedDraft($this->blog);
    $news = scopedDraft($this->news);
    actingAs($user = adminUser(Panel::ACCESS, 'cms.entries.blog.view', 'cms.entries.blog.update'));

    get('/admin/cms/entries')->assertRedirect('/admin/cms/collections/blog/entries');
    get('/admin/cms/collections/blog/entries')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('collections', [['handle' => 'blog', 'title' => 'Blog']])
        ->where('can', ['create' => false, 'update' => true, 'publish' => false, 'delete' => false, 'export' => false]));
    get('/admin/cms/collections/news/entries')->assertForbidden();
    get(entryEditUrl($news->entry_id))->assertForbidden();
    put("/admin/cms/entries/{$blog->entry_id}", ['title' => 'Mine', 'version' => 1])->assertSessionHasNoErrors();
    put("/admin/cms/entries/{$blog->entry_id}", ['title' => 'Live', 'publish' => true, 'version' => 2])->assertForbidden();
    post("/admin/cms/entries/{$blog->entry_id}/publish")->assertForbidden();

    expect(Access::entries($user, 'view'))->toBeTrue()
        ->and(Access::entries($user, 'view', 'news'))->toBeFalse()
        ->and(collect(app(Panel::class)->navigationFor($user))->pluck('items')->flatten(1)->pluck('key')->all())->toBe(['cms-collection-blog']);
});

it('lets authors edit and trash only their own entries', function () {
    $author = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update-own', 'cms.entries.delete-own');
    $theirs = scopedDraft($this->blog, $author);
    $other = scopedDraft($this->blog, adminUser());
    actingAs($author);

    get(entryEditUrl($theirs->entry_id))->assertInertia(fn (Assert $page) => $page->where('can.update', true)->where('can.publish', false));
    get(entryEditUrl($other->entry_id))->assertInertia(fn (Assert $page) => $page->where('can.update', false));
    put("/admin/cms/entries/{$theirs->entry_id}", ['title' => 'Mine', 'version' => 1])->assertSessionHasNoErrors();
    put("/admin/cms/entries/{$other->entry_id}", ['title' => 'Mine', 'version' => 1])->assertForbidden();
    delete("/admin/cms/entries/{$other->entry_id}")->assertForbidden();
    delete("/admin/cms/entries/{$theirs->entry_id}")->assertRedirect();

    // Several at once: only theirs.
    $another = scopedDraft($this->blog, adminUser());
    actingAs($author);
    $mine = scopedDraft($this->blog, $author);
    post('/admin/cms/collections/blog/entries/bulk', ['action' => 'trash', 'ids' => [$mine->entry_id, $another->entry_id]]);
    expect($mine->entry->fresh()?->trashed())->toBeTrue()->and($another->entry->fresh()?->trashed())->toBeFalse();
});

it('lets translators edit the other languages only', function () {
    $post = scopedDraft($this->blog);
    $id = app(CreateTranslation::class)->handle($post->entry, 'id');
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate'));

    put("/admin/cms/entries/{$post->entry_id}", ['locale' => 'id', 'title' => 'Halo', 'version' => $id->version])->assertSessionHasNoErrors();
    put("/admin/cms/entries/{$post->entry_id}", ['locale' => 'en', 'title' => 'Hello', 'version' => $post->version])->assertForbidden();
    put("/admin/cms/entries/{$post->entry_id}/settings", ['template' => 'x'])->assertForbidden();
    get(entryEditUrl($post->entry_id, 'id'))->assertInertia(fn (Assert $page) => $page->where('can.update', true));
    get(entryEditUrl($post->entry_id))->assertInertia(fn (Assert $page) => $page->where('can.update', false));
});

it('lets translators add another language to an entry, in the collections they may view', function () {
    $post = scopedDraft($this->blog);
    $news = scopedDraft($this->news);
    actingAs($translator = adminUser(Panel::ACCESS, 'cms.entries.blog.view', 'cms.entries.translate'));

    get(entryEditUrl($post->entry_id))->assertInertia(fn (Assert $page) => $page->where('can.create', false)->where('can.translate', true));
    post("/admin/cms/entries/{$post->entry_id}/translations", ['locale' => 'en'])->assertForbidden(); // the main language is never theirs
    post("/admin/cms/entries/{$news->entry_id}/translations", ['locale' => 'id'])->assertForbidden(); // a collection they can't view
    post("/admin/cms/entries/{$post->entry_id}/translations", ['locale' => 'id'])->assertRedirect(entryEditUrl($post->entry_id, 'id'));
    expect($post->entry->translations()->where('locale', 'id')->value('status'))->toBe('draft')
        ->and(Access::entries($translator, 'add-translation', $this->blog, $post->entry, 'id'))->toBeTrue()
        ->and(Access::entries($translator, 'add-translation', $this->blog, $post->entry, 'en'))->toBeFalse();
    put("/admin/cms/entries/{$post->entry_id}", ['locale' => 'id', 'title' => 'Halo'])->assertSessionHasNoErrors();

    // Neither create nor translate: no adding.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get(entryEditUrl($post->entry_id))->assertInertia(fn (Assert $page) => $page->where('can.translate', false));
    post("/admin/cms/entries/{$news->entry_id}/translations", ['locale' => 'id'])->assertForbidden();
});

it('keeps translators out of what every language shares: parent, comments, template, blueprint and order', function () {
    $parent = scopedDraft($this->blog);
    $post = scopedDraft($this->blog);
    app(CreateTranslation::class)->handle($post->entry, 'id');
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate'));

    put("/admin/cms/entries/{$post->entry_id}/settings", ['locale' => 'id', 'comments_closed' => true])->assertForbidden();
    put("/admin/cms/entries/{$post->entry_id}/settings", ['locale' => 'xx', 'parent_id' => $parent->entry_id])->assertForbidden();
    post("/admin/cms/entries/{$post->entry_id}/move", ['locale' => 'id', 'direction' => 'up'])->assertForbidden();
    post('/admin/cms/entries/reorder', ['locale' => 'id', 'collection' => 'blog', 'ids' => [$post->entry_id, $parent->entry_id]])->assertForbidden();
    expect($post->entry->fresh())->comments_closed->toBeFalsy()->parent_id->toBeNull();
    // Saving their draft carries no settings: any sent are left as they are.
    put("/admin/cms/entries/{$post->entry_id}", ['locale' => 'id', 'title' => 'Judul', 'data' => [], 'comments_closed' => true])->assertSessionHasNoErrors()->assertRedirect();
    expect($post->entry->fresh())->comments_closed->toBeFalsy()->parent_id->toBeNull();
    // Page settings aren't offered to them.
    get(entryEditUrl($post->entry_id, 'id'))->assertInertia(fn (Assert $page) => $page->where('settings', null)->where('can.update', true));

    // Authors of their own entries still may.
    $author = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update-own');
    $mine = scopedDraft($this->blog, $author);
    put("/admin/cms/entries/{$mine->entry_id}/settings", ['comments_closed' => true])->assertSessionHasNoErrors()->assertRedirect();
    expect($mine->entry->fresh()->comments_closed)->toBeTruthy();
});

it('makes an author for one collection: its view and create, and their own entries in it', function () {
    $author = adminUser(Panel::ACCESS, 'cms.entries.blog.view', 'cms.entries.blog.create', 'cms.entries.update-own', 'cms.entries.delete-own');
    $mine = scopedDraft($this->blog, $author);
    // An entry of theirs in another collection (made before their role changed).
    $elsewhere = app(CreateEntry::class)->handle($this->news, ['title' => 'Old news']);

    expect(Access::entries($author, 'update', $this->blog, $mine->entry))->toBeTrue()
        ->and(Access::entries($author, 'delete', $this->blog, $mine->entry))->toBeTrue()
        ->and(Access::entries($author, 'update', $this->news, $elsewhere->entry))->toBeFalse()
        ->and(Access::entries($author, 'create', $this->news))->toBeFalse();
});

it('scopes terms and submissions too', function () {
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    Taxonomy::query()->create(['handle' => 'categories', 'title' => 'Categories']);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    Form::query()->create(['handle' => 'jobs', 'title' => 'Jobs', 'blueprint' => 'post']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.tags.view', 'cms.terms.tags.create', 'cms.terms.tags.update', 'cms.terms.tags.delete', 'cms.submissions.jobs.view'));

    get('/admin/cms/terms')->assertRedirect('/admin/cms/taxonomies/tags/terms');
    get('/admin/cms/taxonomies/tags/terms')->assertOk()->assertInertia(fn (Assert $page) => $page->where('taxonomies', [['handle' => 'tags', 'title' => 'Tags']]));
    get('/admin/cms/taxonomies/categories/terms')->assertForbidden();
    get('/admin/cms/submissions')->assertRedirect('/admin/cms/forms/jobs/submissions');
    get('/admin/cms/forms/contact/submissions')->assertForbidden();
    get('/admin/cms/forms/jobs/submissions')->assertInertia(fn (Assert $page) => $page->where('can.delete', false)->where('forms.0.handle', 'jobs')->has('forms', 1));
});

it('gives the default roles their content permissions', function () {
    app(SeedRoles::class)->handle();

    expect(Role::findByName('editor', 'web')->permissions->pluck('name'))->toContain('cms.entries.publish', 'cms.entries.blog.publish', 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete')
        ->not->toContain('cms.collections.create')
        // Not the submissions: visitors' personal details go to those an admin picks (F-019).
        ->not->toContain('cms.submissions.view')->not->toContain('cms.submissions.export')
        // Edits menus' links, but doesn't create or delete the menus templates rely on.
        ->and(Role::findByName('editor', 'web')->permissions->pluck('name'))->toContain('cms.menus.view', 'cms.menus.update')->not->toContain('cms.menus.create', 'cms.menus.delete')
        ->and(Role::findByName('author', 'web')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['cms.entries.create', 'cms.entries.delete-own', 'cms.entries.update-own', 'cms.entries.view', 'cms.entries.view-drafts', 'sunrice.access', 'sunrice.guide.view', 'sunrice.media.upload', 'sunrice.media.view'])
        ->and(Role::findByName('translator', 'web')->permissions->pluck('name'))->toContain('cms.entries.translate');
});

it('renames a collection\'s, taxonomy\'s and form\'s permissions with its handle, so roles keep them', function () {
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    $role = Role::findOrCreate('blogger', 'web')->givePermissionTo([Panel::ACCESS, 'cms.entries.blog.view', 'cms.entries.blog.update', 'cms.terms.tags.view', 'cms.terms.tags.create', 'cms.terms.tags.update', 'cms.terms.tags.delete', 'cms.submissions.contact.view']);
    $blogger = adminUser()->assignRole($role);
    $entry = scopedDraft($this->blog);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.update', 'cms.taxonomies.view', 'cms.taxonomies.update', 'cms.forms.view', 'cms.forms.update'));

    get("/admin/cms/collections/{$this->blog->handle}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('sections.0.fields.1.name', 'handle')->where('sections.0.fields.1.warnWhenChanged', HandleField::warning('collection')));
    put("/admin/cms/collections/{$this->blog->handle}", ['title' => 'Blog', 'handle' => 'news', 'per_page' => 10])->assertSessionHasErrors('handle'); // taken
    put("/admin/cms/collections/{$this->blog->handle}", ['title' => 'Blog', 'handle' => 'Bad Handle', 'per_page' => 10])->assertSessionHasErrors('handle');
    put("/admin/cms/collections/{$this->blog->handle}", ['title' => 'Journal', 'handle' => 'journal', 'blueprint' => 'post', 'per_page' => 10])->assertSessionHasNoErrors();
    put("/admin/cms/taxonomies/{$tags->handle}", ['title' => 'Topics', 'handle' => 'topics'])->assertSessionHasNoErrors();
    put("/admin/cms/forms/{$form->handle}", ['title' => 'Contact', 'handle' => 'enquiry', 'blueprint' => 'post'])->assertSessionHasNoErrors();

    expect($this->blog->fresh()?->handle)->toBe('journal')
        ->and($role->fresh()?->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['cms.entries.journal.update', 'cms.entries.journal.view', 'cms.submissions.enquiry.view', 'cms.terms.topics.create', 'cms.terms.topics.delete', 'cms.terms.topics.update', 'cms.terms.topics.view', Panel::ACCESS])
        ->and(Permission::query()->where('name', 'like', 'cms.entries.blog.%')->exists())->toBeFalse()
        ->and(Permission::query()->pluck('name')->all())->toContain('cms.entries.journal.publish', 'cms.submissions.enquiry.delete');

    // The grants still work, and nothing is left behind when permissions are synced again.
    app(SyncPermissions::class)->handle();
    $blogger = $blogger->fresh();
    expect(Access::entries($blogger, 'update', 'journal'))->toBeTrue()
        ->and(Access::terms($blogger, 'view', 'topics'))->toBeTrue()
        ->and(Access::submissions($blogger, 'view', 'enquiry'))->toBeTrue();
    actingAs($blogger);
    get(entryEditUrl($entry->entry_id))->assertOk();
});

it('leaves a permission alone when its new name is already stored', function () {
    Permission::findOrCreate('cms.entries.journal.view', 'web');

    expect(app(RenamePermissions::class)->handle(['cms.entries.blog.view' => 'cms.entries.journal.view', 'cms.entries.blog.create' => 'cms.entries.journal.create']))
        ->toBe(['cms.entries.blog.create'])
        ->and(Permission::query()->where('name', 'cms.entries.blog.view')->exists())->toBeTrue();
});
