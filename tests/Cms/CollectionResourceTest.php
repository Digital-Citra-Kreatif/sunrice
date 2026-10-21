<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Admin\HandleField;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\Locales;

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
        ->and(route('sunrice.admin.cms.collections.index', absolute: false))->toBe('/admin/cms/collections');
});

it('is guarded by the permissions', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    collectionsUser();

    get('/admin/cms/collections')->assertForbidden();
    post('/admin/cms/collections', ['title' => 'X', 'handle' => 'x'])->assertForbidden();
    put("/admin/cms/collections/{$pages->handle}", ['title' => 'X'])->assertForbidden();
    delete("/admin/cms/collections/{$pages->handle}")->assertForbidden();
});

it('lists collections', function () {
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    Collection::query()->create(['handle' => 'articles', 'title' => 'Articles', 'dated' => true]);
    Collection::query()->create(['handle' => 'old', 'title' => 'Old'])->delete();
    collectionsUser('view');

    get('/admin/cms/collections')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('rows.total', 2)
        ->where('rows.data.0.title', 'Articles')
        ->where('rows.data.0.cells.dated', true));
});

it('creates a collection, offering the blueprints', function () {
    Blueprint::query()->create(['handle' => 'article', 'title' => 'Article']);
    collectionsUser('view', 'create');

    get('/admin/cms/collections/create')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sections.0.fields.2.options', [['value' => 'article', 'label' => 'Article']]));

    post('/admin/cms/collections', ['title' => 'Articles', 'handle' => 'articles', 'blueprint' => 'article', 'dated' => '1'])->assertSessionHasNoErrors();

    expect(Collection::query()->firstOrFail()->only(['handle', 'title', 'blueprint', 'dated']))
        ->toBe(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article', 'dated' => true]);
});

it('refuses bad handles, taken ones (deleted collections included) and unknown blueprints', function () {
    Collection::query()->create(['handle' => 'news', 'title' => 'News'])->delete();
    collectionsUser('view', 'create');

    post('/admin/cms/collections', ['title' => 'News', 'handle' => 'news'])->assertSessionHasErrors('handle');
    post('/admin/cms/collections', ['title' => 'X', 'handle' => 'Bad Handle'])->assertSessionHasErrors('handle');
    post('/admin/cms/collections', ['title' => 'X', 'handle' => 'x', 'blueprint' => 'nope'])->assertSessionHasErrors('blueprint');
    expect(Collection::query()->count())->toBe(0);
});

it('changes the handle when editing, only to a free and valid one', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    Collection::query()->create(['handle' => 'posts', 'title' => 'Posts'])->delete();
    collectionsUser('view', 'update');

    put("/admin/cms/collections/{$pages->handle}", ['title' => 'Pages'])->assertSessionHasNoErrors(); // left out: kept
    expect($pages->fresh()?->handle)->toBe('pages');
    put("/admin/cms/collections/{$pages->handle}", ['title' => 'Site pages', 'handle' => 'posts'])->assertSessionHasErrors('handle'); // a deleted one's
    put("/admin/cms/collections/{$pages->handle}", ['title' => 'Site pages', 'handle' => 'other'])->assertSessionHasNoErrors();

    expect($pages->fresh()?->only(['handle', 'title']))->toBe(['handle' => 'other', 'title' => 'Site pages']);
});

it('warns on the edit form, not when creating, what a new handle breaks and what Sunrice updates', function () {
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact']);
    Collection::query()->create(['handle' => 'news', 'title' => 'News']);
    Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    Form::query()->create(['handle' => 'enquiry', 'title' => 'Enquiry', 'blueprint' => 'contact']);
    GlobalSet::query()->create(['handle' => 'company', 'title' => 'Company', 'blueprint' => 'contact']);
    Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    actingAs(adminUser(Panel::ACCESS, ...collect(['collections', 'taxonomies', 'forms', 'globals', 'menus'])->crossJoin(['view', 'create', 'update'])->map(fn ($p) => "cms.{$p[0]}.{$p[1]}")->all()));
    $handle = fn (Assert $page): array => collect($page->toArray()['props']['sections'])->flatMap(fn ($section) => $section['fields'])->firstWhere('name', 'handle');

    foreach (['collections/news' => ['cms/:old.blade.php', 'renames the collection\'s permissions'], 'taxonomies/topics' => ['whereTerm(\':old\'', 'renames the taxonomy\'s permissions'],
        'forms/enquiry' => ['<x-sunrice-cms::form handle=":old" />', 'keeps its submissions'], 'globals/company' => ['cms_global(\':old\')', 'keeps the set\'s values'],
        'menus/main' => ['cms_menu(\':old\')', 'keeps the menu\'s links']] as $path => $says) {
        get("/admin/cms/{$path}/edit")->assertOk()->assertInertia(fn (Assert $page) => expect($handle($page)['warnWhenChanged'])->toContain(...$says));
        $list = explode('/', $path)[0];
        get("/admin/cms/{$list}/create")->assertOk()->assertInertia(fn (Assert $page) => expect($handle($page)['warnWhenChanged'])->toBeNull());
    }

    app()->setLocale('id');
    expect(HandleField::warning('menu'))->toStartWith('Mengubah handle')->toContain('cms_menu(\':old\')');
});

it('deletes a collection but keeps its entries', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    $user = adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.delete');
    actingAs($user);
    $entry = app(CreateEntry::class)->handle($pages, ['title' => 'About']);

    delete("/admin/cms/collections/{$pages->handle}")->assertRedirect();

    expect(Collection::withTrashed()->find($pages->id)?->trashed())->toBeTrue()
        ->and($entry->fresh())->not->toBeNull()
        ->and(Activity::query()->latest('id')->first()?->only(['action', 'subject_type']))->toBe(['action' => 'deleted', 'subject_type' => 'collections']);
});

it('takes the title visitors read in each other language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    collectionsUser('view', 'create', 'update');

    post('/admin/cms/collections', ['title' => 'Properties', 'handle' => 'properties', 'title_id' => ' Properti ', 'per_page' => 10])->assertSessionHasNoErrors();
    $collection = Collection::query()->firstOrFail();
    expect($collection->translations)->toBe(['id' => ['title' => 'Properti']])->and($collection->titleIn('id'))->toBe('Properti');

    get('/admin/cms/collections/properties/edit')->assertInertia(fn (Assert $page) => $page
        ->where('values.title_id', 'Properti')->where('sections.0.fields', fn ($fields) => collect($fields)->firstWhere('name', 'title_id')['label'] === 'Title in '.Locales::name('id')));
    put('/admin/cms/collections/properties', ['title' => 'Listings', 'handle' => 'properties', 'title_id' => '', 'per_page' => 10])->assertSessionHasNoErrors();
    expect($collection->fresh()?->translations)->toBeNull()->and($collection->fresh()?->titleIn('id'))->toBe('Listings');
});

it('names collections by their handle in addresses, and sends an old id address on to it', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    collectionsUser('view', 'create', 'update');

    get('/admin/cms/collections')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.key', 'pages'));
    get('/admin/cms/collections/pages/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Resources/Edit', shouldExist: false)->where('record.key', 'pages'));
    get("/admin/cms/collections/{$pages->id}/edit")->assertRedirect('/admin/cms/collections/pages/edit');
    post('/admin/cms/collections', ['title' => 'New', 'handle' => 'create', 'per_page' => 10])->assertSessionHasErrors('handle');
    post('/admin/cms/collections', ['title' => 'News', 'handle' => 'news', 'per_page' => 10])->assertRedirect('/admin/cms/collections/news/edit');
});
