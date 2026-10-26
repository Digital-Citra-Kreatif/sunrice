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
use Sunrice\Cms\Models\Entry;
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
    actingAs(adminUser(Panel::ACCESS, ...collect(['collections', 'taxonomies', 'forms', 'globals', 'menus'])->crossJoin(['view', 'create', 'update'])->map(fn ($p) => "cms.{$p[0]}.{$p[1]}")->push('cms.globals.configure', 'cms.menus.configure')->all()));
    $handle = fn (Assert $page): array => collect($page->toArray()['props']['sections'])->flatMap(fn ($section) => $section['fields'])->firstWhere('name', 'handle');

    foreach (['collections/news' => ['cms/collections/:old/', 'renames the collection\'s permissions'], 'taxonomies/topics' => ['whereTerm(\':old\'', 'renames the taxonomy\'s permissions'],
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

it('says a deleted collection can be restored, and its trash offers Restore (NEW-admin-2)', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.delete'));

    // The row's Delete says "Move … to the trash? You can restore it." when can.trash.
    get('/admin/cms/collections')->assertInertia(fn (Assert $page) => $page->where('can.trash', true)
        ->where('bulkActions.0.label', 'Move to trash')->where('bulkActions.0.confirm', 'Move the selected records to the trash? You can restore them.'));
    delete('/admin/cms/collections/pages')->assertRedirect();
    get('/admin/cms/collections?status=trashed')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.key', 'pages')->where('rows.data.0.can.delete', true));
    post('/admin/cms/collections/pages/restore')->assertRedirect()->assertSessionHas('success', 'Pages restored.');
    expect($pages->fresh()?->trashed())->toBeFalse();
});

it('deletes a collection in the trash permanently with its entries, freeing its handle', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.delete', 'cms.taxonomies.view', 'cms.taxonomies.delete'));
    $entry = app(CreateEntry::class)->handle($pages, ['title' => 'About']);
    delete('/admin/cms/collections/pages')->assertRedirect();
    delete('/admin/cms/taxonomies/topics')->assertRedirect();

    delete('/admin/cms/collections/pages/force')->assertSessionHas('success', 'Pages permanently deleted.');
    post('/admin/cms/taxonomies/bulk', ['action' => 'force-delete', 'ids' => [$topics->id]])->assertSessionHas('success', '1 permanently deleted.');

    expect(Collection::withTrashed()->find($pages->id))->toBeNull()
        ->and(Taxonomy::withTrashed()->find($topics->id))->toBeNull()
        ->and(Entry::withTrashed()->find($entry->id))->toBeNull();
    collectionsUser('view', 'create');
    post('/admin/cms/collections', ['title' => 'Pages', 'handle' => 'pages', 'per_page' => 10])->assertSessionHasNoErrors();
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

it('says in plain words what a handle is, then what developers write with it as it is typed', function () {
    actingAs(adminUser(Panel::ACCESS, ...collect(['collections', 'taxonomies', 'forms', 'globals', 'menus'])->map(fn ($area) => "cms.{$area}.create")->all()));
    $handle = fn (Assert $page): array => collect($page->toArray()['props']['sections'])->flatMap(fn ($section) => $section['fields'])->firstWhere('name', 'handle');

    foreach (['collections' => ['collection', 'Cms::entries(\':value\')', 'articles'], 'taxonomies' => ['taxonomy', 'taxonomy=":value"', 'categories'],
        'forms' => ['form', '<x-sunrice-cms::form handle=":value" />', 'newsletter'], 'globals' => ['global set', 'Cms::global(\':value\')', 'contact'],
        'menus' => ['menu', 'Cms::menu(\':value\')', 'footer']] as $list => [$name, $code, $example]) {
        get("/admin/cms/{$list}/create")->assertOk()->assertInertia(function (Assert $page) use ($handle, $name, $code, $example) {
            expect($handle($page)['help'])->toBe("A short name for this {$name}, used by the site's templates.")
                ->and($handle($page)['developerHelp']['text'])->toContain($code)
                ->and($handle($page)['developerHelp']['example'])->toBe($example);
        });
    }
});

it('offers the listing page a blueprint of its own, saying where editors fill it in, with a way to create or edit it', function () {
    Collection::query()->create(['handle' => 'news', 'title' => 'News', 'listing_route' => '/news']);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.update'));

    get('/admin/cms/collections/news/edit')->assertOk()->assertInertia(function (Assert $page) {
        $field = collect($page->toArray()['props']['sections'])->flatMap(fn ($section) => $section['fields'])->firstWhere('name', 'listing_blueprint');
        expect($field)->toMatchArray([
            'label' => 'Listing page blueprint',
            'placeholder' => 'None (no editable fields)',
            'showWhen' => ['field' => 'listing', 'value' => null],
            'helpValues' => ['url' => ['field' => 'listing_route', 'fallback' => '/{handle}'], 'collection' => ['field' => 'title', 'fallback' => null]],
        ])->and($field['help'])->toContain('(:url)')->toContain('Entries → :collection → Listing page')
            ->and(array_column($field['links'], 'label'))->toBe(['Create blueprint', 'Edit'])
            ->and($field['links'][0]['showWhen'])->toBe(['field' => 'listing_blueprint', 'value' => null, 'not' => true])
            ->and($field['links'][1]['url'])->toBe('/admin/cms/blueprints/{listing_blueprint}/edit');
    });
});

it('links the collection form\'s cards to the site and the entries, once saved', function () {
    Collection::query()->create(['handle' => 'news', 'title' => 'News', 'route' => '/news/{slug}', 'listing_route' => '/news']);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.create', 'cms.collections.update'));
    $section = fn (Assert $page, string $title): array => collect($page->toArray()['props']['sections'])->firstWhere('title', $title);

    get('/admin/cms/collections/news/edit')->assertInertia(function (Assert $page) use ($section) {
        expect($section($page, 'Entry pages')['links'])->toBe([['label' => 'View entries', 'url' => '/admin/cms/collections/news/entries', 'newTab' => false, 'showWhen' => null]])
            ->and($section($page, 'Listing page')['links'])->toBe([
                ['label' => 'View on site', 'url' => url('/news'), 'newTab' => true, 'showWhen' => ['field' => 'listing', 'value' => null, 'not' => false]],
                // With or without a listing blueprint: the editor has its SEO and template too.
                ['label' => 'Edit listing page', 'url' => '/admin/cms/collections/news/listing/edit', 'newTab' => false, 'showWhen' => ['field' => 'listing', 'value' => null, 'not' => false]],
            ]);
        // The listing template: a choice of the views, Automatic first; its help says which file is used, the files in a "Which file?" disclosure.
        $template = collect($section($page, 'Listing page')['fields'])->firstWhere('name', 'listing_template');
        expect($template['label'])->toBe('Listing page template')
            ->and($template['type'])->toBe('select')
            ->and($template['placeholder'])->toBe('Automatic')
            ->and($template['searchable'])->toBeTrue()
            ->and($template['other']['label'])->toBe('Other view…')
            ->and($template['help'])->toBe('Automatic: the first template that exists. Uses the built-in template.')
            ->and($template['helpDetails']['summary'])->toBe('Which file?')
            ->and($template['helpDetails']['text'])->toContain("1. `cms/collections/{handle}/index.blade.php`\n2. `cms/collections/index.blade.php`");
    });
    get('/admin/cms/collections/create')->assertInertia(fn (Assert $page) => expect($section($page, 'Entry pages'))->not->toHaveKey('links')
        ->and($section($page, 'Listing page'))->not->toHaveKey('links'));
});

it('saves a collection\'s listing page template and a taxonomy\'s index page template, as view names', function () {
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'listing_route' => '/blog']);
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.update', 'cms.taxonomies.update'));

    put('/admin/cms/collections/blog', ['title' => 'Blog', 'listing_template' => '../evil'])->assertSessionHasErrors('listing_template');
    put('/admin/cms/collections/blog', ['title' => 'Blog', 'listing_template' => 'cms.blog-home'])->assertSessionHasNoErrors();
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'index_template' => 'cms tags'])->assertSessionHasErrors('index_template');
    // Only views under the site's template prefixes: never another view of the app.
    put('/admin/cms/collections/blog', ['title' => 'Blog', 'listing_template' => 'layouts.app'])->assertSessionHasErrors('listing_template');
    put('/admin/cms/collections/blog', ['title' => 'Blog', 'pages' => true, 'template' => 'layouts.app'])->assertSessionHasErrors('template');
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'index_template' => 'layouts.app'])->assertSessionHasErrors('index_template');
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'template' => 'layouts.app'])->assertSessionHasErrors('template');
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'index_template' => 'cms.tag-cloud'])->assertSessionHasNoErrors();

    expect($blog->fresh()?->listing_template)->toBe('cms.blog-home')
        ->and($tags->fresh()?->index_template)->toBe('cms.tag-cloud');
    get('/admin/cms/taxonomies/tags/edit')->assertInertia(fn (Assert $page) => expect(collect($page->toArray()['props']['sections'])->pluck('fields')->flatten(1)->firstWhere('name', 'index_template'))
        ->toMatchArray(['label' => 'Index page template'])
        ->and(collect($page->toArray()['props']['sections'])->pluck('fields')->flatten(1)->firstWhere('name', 'index_template')['helpDetails']['text'])
        ->toContain('1. `cms/taxonomies/{handle}/index.blade.php`')->toContain('`cms/collections/{collection}/{handle}/index.blade.php`'));
});
