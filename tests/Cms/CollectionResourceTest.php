<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(fn () => usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make())));

/** Signed in with the panel and these collection permissions ("view", "create" …). */
function collectionsUser(string ...$abilities): void
{
    actingAs(adminUser(Panel::ACCESS, ...array_map(fn ($a) => "cms.collections.{$a}", $abilities)));
}

it('registers the Collections screens and their permissions', function () {
    expect(array_keys(app(Panel::class)->getPermissions()))->toContain('cms.collections.view', 'cms.collections.create', 'cms.collections.update', 'cms.collections.delete')
        ->and(route('sunrice.admin.resources.collections.index', absolute: false))->toBe('/admin/collections');
});

it('is guarded by the permissions', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    collectionsUser();

    get('/admin/collections')->assertForbidden();
    post('/admin/collections', ['title' => 'X', 'handle' => 'x'])->assertForbidden();
    put("/admin/collections/{$pages->id}", ['title' => 'X'])->assertForbidden();
    delete("/admin/collections/{$pages->id}")->assertForbidden();
});

it('lists collections', function () {
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    Collection::query()->create(['handle' => 'articles', 'title' => 'Articles', 'dated' => true]);
    Collection::query()->create(['handle' => 'old', 'title' => 'Old'])->delete();
    collectionsUser('view');

    get('/admin/collections')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('rows.total', 2)
        ->where('rows.data.0.title', 'Articles')
        ->where('rows.data.0.cells.dated', true));
});

it('creates a collection, offering the blueprints', function () {
    Blueprint::query()->create(['handle' => 'article', 'title' => 'Article']);
    collectionsUser('view', 'create');

    get('/admin/collections/create')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sections.0.fields.2.options', [['value' => 'article', 'label' => 'Article']]));

    post('/admin/collections', ['title' => 'Articles', 'handle' => 'articles', 'blueprint' => 'article', 'dated' => '1'])->assertSessionHasNoErrors();

    expect(Collection::query()->firstOrFail()->only(['handle', 'title', 'blueprint', 'dated']))
        ->toBe(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article', 'dated' => true]);
});

it('refuses bad handles, taken ones (deleted collections included) and unknown blueprints', function () {
    Collection::query()->create(['handle' => 'news', 'title' => 'News'])->delete();
    collectionsUser('view', 'create');

    post('/admin/collections', ['title' => 'News', 'handle' => 'news'])->assertSessionHasErrors('handle');
    post('/admin/collections', ['title' => 'X', 'handle' => 'Bad Handle'])->assertSessionHasErrors('handle');
    post('/admin/collections', ['title' => 'X', 'handle' => 'x', 'blueprint' => 'nope'])->assertSessionHasErrors('blueprint');
    expect(Collection::query()->count())->toBe(0);
});

it('changes the handle when editing, only to a free and valid one', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    Collection::query()->create(['handle' => 'posts', 'title' => 'Posts'])->delete();
    collectionsUser('view', 'update');

    put("/admin/collections/{$pages->id}", ['title' => 'Pages'])->assertSessionHasNoErrors(); // left out: kept
    expect($pages->fresh()?->handle)->toBe('pages');
    put("/admin/collections/{$pages->id}", ['title' => 'Site pages', 'handle' => 'posts'])->assertSessionHasErrors('handle'); // a deleted one's
    put("/admin/collections/{$pages->id}", ['title' => 'Site pages', 'handle' => 'other'])->assertSessionHasNoErrors();

    expect($pages->fresh()?->only(['handle', 'title']))->toBe(['handle' => 'other', 'title' => 'Site pages']);
});

it('deletes a collection but keeps its entries', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    $user = adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.delete');
    actingAs($user);
    $entry = app(CreateEntry::class)->handle($pages, ['title' => 'About']);

    delete("/admin/collections/{$pages->id}")->assertRedirect();

    expect(Collection::withTrashed()->find($pages->id)?->trashed())->toBeTrue()
        ->and($entry->fresh())->not->toBeNull()
        ->and(Activity::query()->latest('id')->first()?->only(['action', 'subject_type']))->toBe(['action' => 'deleted', 'subject_type' => 'collections']);
});
