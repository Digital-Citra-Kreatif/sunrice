<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SeedRoles;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
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

    expect(Permission::query()->pluck('name')->all())->toContain('cms.entries.blog.view', 'cms.entries.news.publish', 'cms.terms.tags.manage', 'cms.submissions.contact.view', 'cms.submissions.contact.delete')
        ->and(app(Panel::class)->getPermissions()['cms.entries.blog.update']->group)->toBe('Entries: Blog');

    $form->delete();
    $this->news->forceDelete();
    expect(Permission::query()->pluck('name')->all())->not->toContain('cms.submissions.contact.view')->not->toContain('cms.entries.news.view')
        ->toContain('cms.entries.blog.view');
});

it('lets someone work in one collection only', function () {
    $blog = scopedDraft($this->blog);
    $news = scopedDraft($this->news);
    actingAs($user = adminUser(Panel::ACCESS, 'cms.entries.blog.view', 'cms.entries.blog.update'));

    get('/admin/cms/entries')->assertRedirect('/admin/cms/collections/blog/entries');
    get('/admin/cms/collections/blog/entries')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('collections', [['handle' => 'blog', 'title' => 'Blog']])
        ->where('can', ['create' => false, 'update' => true, 'publish' => false, 'delete' => false]));
    get('/admin/cms/collections/news/entries')->assertForbidden();
    get("/admin/cms/entries/{$news->entry_id}/edit")->assertForbidden();
    put("/admin/cms/entries/{$blog->entry_id}", ['title' => 'Mine', 'version' => 1])->assertSessionHasNoErrors();
    put("/admin/cms/entries/{$blog->entry_id}", ['title' => 'Live', 'publish' => true, 'version' => 2])->assertForbidden();
    post("/admin/cms/entries/{$blog->entry_id}/publish")->assertForbidden();

    expect(Access::entries($user, 'view'))->toBeTrue()
        ->and(Access::entries($user, 'view', 'news'))->toBeFalse()
        ->and(collect(app(Panel::class)->navigationFor($user))->pluck('items')->flatten(1)->pluck('key')->all())->toBe(['cms-entries']);
});

it('lets authors edit and trash only their own entries', function () {
    $author = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update-own', 'cms.entries.delete-own');
    $theirs = scopedDraft($this->blog, $author);
    $other = scopedDraft($this->blog, adminUser());
    actingAs($author);

    get("/admin/cms/entries/{$theirs->entry_id}/edit")->assertInertia(fn (Assert $page) => $page->where('can.update', true)->where('can.publish', false));
    get("/admin/cms/entries/{$other->entry_id}/edit")->assertInertia(fn (Assert $page) => $page->where('can.update', false));
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
    get("/admin/cms/entries/{$post->entry_id}/edit?locale=id")->assertInertia(fn (Assert $page) => $page->where('can.update', true));
    get("/admin/cms/entries/{$post->entry_id}/edit")->assertInertia(fn (Assert $page) => $page->where('can.update', false));
});

it('scopes terms and submissions too', function () {
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    Taxonomy::query()->create(['handle' => 'categories', 'title' => 'Categories']);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    Form::query()->create(['handle' => 'jobs', 'title' => 'Jobs', 'blueprint' => 'post']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.tags.manage', 'cms.submissions.jobs.view'));

    get('/admin/cms/terms')->assertRedirect('/admin/cms/taxonomies/tags/terms');
    get('/admin/cms/taxonomies/tags/terms')->assertOk()->assertInertia(fn (Assert $page) => $page->where('taxonomies', [['handle' => 'tags', 'title' => 'Tags']]));
    get('/admin/cms/taxonomies/categories/terms')->assertForbidden();
    get('/admin/cms/submissions')->assertRedirect('/admin/cms/forms/jobs/submissions');
    get('/admin/cms/forms/contact/submissions')->assertForbidden();
    get('/admin/cms/forms/jobs/submissions')->assertInertia(fn (Assert $page) => $page->where('can.delete', false)->where('forms.0.handle', 'jobs')->has('forms', 1));
});

it('gives the default roles their content permissions', function () {
    app(SeedRoles::class)->handle();

    expect(Role::findByName('editor', 'web')->permissions->pluck('name'))->toContain('cms.entries.publish', 'cms.entries.blog.publish', 'cms.terms.manage')
        ->not->toContain('cms.collections.create')
        ->and(Role::findByName('author', 'web')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['cms.entries.create', 'cms.entries.delete-own', 'cms.entries.update-own', 'cms.entries.view', 'cms.entries.view-drafts', 'sunrice.access', 'sunrice.guide.view', 'sunrice.media.upload', 'sunrice.media.view'])
        ->and(Role::findByName('translator', 'web')->permissions->pluck('name'))->toContain('cms.entries.translate');
});
