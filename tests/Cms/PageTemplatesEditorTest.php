<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Site;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

// The listing page's and index pages' Template, picked in their own editors (the collection's and taxonomy's setting).

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $this->books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'route' => '/books/{slug}', 'listing_route' => '/books']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'index_route' => '/tags']);
    $this->tags->collections()->attach($this->books->id);
    app()->forgetInstance(Site::class);
});

it('shows and saves a collection\'s listing page template on its listing page editor', function () {
    $dir = resource_path('views/cms');
    File::ensureDirectoryExists("{$dir}/collections");
    try {
        File::put("{$dir}/collections/index.blade.php", 'Any listing');
        File::put("{$dir}/books-home.blade.php", 'Books home');
        actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.collections.update'));

        get('/admin/cms/collections/books/listing/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('template', null)->where('can.template', true)
            ->where('templates.own', null)
            ->where('templates.fallbacks', [['view' => 'cms.collections.books.index', 'exists' => false], ['view' => 'cms.collections.index', 'exists' => true]])
            ->missing('templateChoices'));
        get('/admin/cms/collections/books/listing/edit')->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('templateChoices', fn (Assert $reload) => $reload->where('templateChoices', fn ($views) => collect($views)->contains('cms.books-home'))));

        put('/admin/cms/collections/books/listing', ['locale' => 'en', 'data' => [], 'template' => 'cms.books-home'])->assertSessionHasNoErrors();
        expect($this->books->refresh()->listing_template)->toBe('cms.books-home');
        get('/books')->assertOk()->assertSee('Books home');

        put('/admin/cms/collections/books/listing', ['locale' => 'en', 'data' => [], 'template' => 'layouts.app'])->assertSessionHasErrors('template');
        put('/admin/cms/collections/books/listing', ['locale' => 'en', 'data' => [], 'template' => ''])->assertSessionHasNoErrors();
        expect($this->books->refresh()->listing_template)->toBeNull();
        get('/books')->assertOk()->assertSee('Any listing');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('saves an index page template for all of a taxonomy\'s index pages from either one\'s editor', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update', 'cms.taxonomies.update'));

    get('/admin/cms/taxonomies/tags/index-page/edit?collection=books')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('can.template', true)
        ->where('templates.fallbacks.0.view', 'cms.collections.books.tags.index'));
    get('/admin/cms/taxonomies/tags/index-page/edit')->assertInertia(fn (Assert $page) => $page
        ->where('templates.fallbacks.0.view', 'cms.taxonomies.tags.index'));

    put('/admin/cms/taxonomies/tags/index-page', ['collection' => 'books', 'locale' => 'en', 'data' => [], 'template' => 'cms.tag-cloud'])->assertSessionHasNoErrors();
    expect($this->tags->refresh()->index_template)->toBe('cms.tag-cloud');
    get('/admin/cms/taxonomies/tags/index-page/edit')->assertInertia(fn (Assert $page) => $page
        ->where('template', 'cms.tag-cloud')->where('templates.own', ['view' => 'cms.tag-cloud', 'exists' => false]));

    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'en', 'data' => [], 'template' => 'cms.partials.card'])->assertSessionHasErrors('template');
    expect($this->tags->refresh()->index_template)->toBe('cms.tag-cloud');
});

it('lets only those who may change the collection or taxonomy change its template', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.terms.view', 'cms.terms.update'));

    get('/admin/cms/collections/books/listing/edit')->assertInertia(fn (Assert $page) => $page->where('can.update', true)->where('can.template', false));
    get('/admin/cms/taxonomies/tags/index-page/edit')->assertInertia(fn (Assert $page) => $page->where('can.update', true)->where('can.template', false));
    // Their fields still save, without the template.
    put('/admin/cms/collections/books/listing', ['locale' => 'en', 'data' => []])->assertSessionHasNoErrors();
    put('/admin/cms/collections/books/listing', ['locale' => 'en', 'data' => [], 'template' => 'cms.books-home'])->assertForbidden();
    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'en', 'data' => [], 'template' => 'cms.tag-cloud'])->assertForbidden();

    expect($this->books->refresh()->listing_template)->toBeNull()
        ->and($this->tags->refresh()->index_template)->toBeNull();
});

it('offers the views that exist on the collection and taxonomy forms, and says which one a page uses', function () {
    $dir = resource_path('views/cms');
    File::ensureDirectoryExists("{$dir}/collections");
    File::ensureDirectoryExists("{$dir}/partials");
    try {
        File::put("{$dir}/collections/index.blade.php", 'Any listing');
        File::put("{$dir}/blog-home.blade.php", 'Blog home');
        File::put("{$dir}/partials/card.blade.php", 'A card');
        actingAs(adminUser(Panel::ACCESS, 'cms.collections.update', 'cms.taxonomies.update'));
        $field = fn (Assert $page, string $name): array => collect($page->toArray()['props']['sections'])->pluck('fields')->flatten(1)->firstWhere('name', $name);

        get('/admin/cms/collections/books/edit')->assertInertia(function (Assert $page) use ($field) {
            $template = $field($page, 'listing_template');
            $views = collect($template['options'])->flatMap(fn (array $group) => collect($group['options'] ?? [$group])->pluck('value'))->all();
            expect($template['help'])->toBe('Automatic: the first template that exists. Uses cms/collections/index.blade.php.')
                ->and($views)->toContain('cms.blog-home', 'cms.collections.index')
                ->and($views)->not->toContain('cms.partials.card');
            expect($field($page, 'template')['type'])->toBe('select');
        });
        get('/admin/cms/taxonomies/tags/edit')->assertInertia(fn (Assert $page) => expect($field($page, 'index_template')['help'])->toEndWith('Uses the built-in template.')
            ->and($field($page, 'template')['type'])->toBe('select'));
    } finally {
        File::deleteDirectory($dir);
    }
});
