<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\CmsServiceProvider;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Fieldset;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(fn () => usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make())));

function builderUser(): void
{
    actingAs(adminUser(Panel::ACCESS, 'cms.blueprints.manage'));
}

it('is behind cms.blueprints.manage', function () {
    $blueprint = Blueprint::query()->create(['handle' => 'page', 'title' => 'Page']);
    $fieldset = Fieldset::query()->create(['handle' => 'seo', 'title' => 'SEO']);
    actingAs(adminUser(Panel::ACCESS));

    get('/admin/cms/blueprints')->assertForbidden();
    get('/admin/cms/blueprints/create')->assertForbidden();
    post('/admin/cms/blueprints', ['handle' => 'x', 'title' => 'X', 'fields' => []])->assertForbidden();
    get("/admin/cms/blueprints/{$blueprint->id}/edit")->assertForbidden();
    put("/admin/cms/blueprints/{$blueprint->id}", ['title' => 'X', 'fields' => []])->assertForbidden();
    delete("/admin/cms/blueprints/{$blueprint->id}")->assertForbidden();
    get('/admin/cms/fieldsets')->assertForbidden();
    delete("/admin/cms/fieldsets/{$fieldset->id}")->assertForbidden();

    expect(Blueprint::query()->count())->toBe(1)->and(Fieldset::query()->count())->toBe(1);
});

it('adds the screens to the sidebar and loads the CMS bundle for them', function () {
    builderUser();

    get('/admin/cms/blueprints')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Blueprints/Index', shouldExist: false)
        ->where('sunrice.bundles.0.id', 'cms')
        ->where('sunrice.bundles.0.pages', ['Cms/'])
        ->where('sunrice.bundles.0.module', fn (string $url) => str_starts_with($url, asset('vendor/sunrice-cms/admin.js')))
        ->where('navigation', fn ($groups) => collect($groups)->flatten(3)->contains('Blueprints')));
});

it('lists blueprints with the collections that use them', function () {
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'body', 'type' => 'textarea']]]);
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    builderUser();

    get('/admin/cms/blueprints')->assertInertia(fn (Assert $page) => $page
        ->where('blueprints.0', ['id' => 1, 'handle' => 'page', 'title' => 'Page', 'fields' => 1, 'usedBy' => ['Pages']]));
});

it('gives the builder the field types, their settings, fieldsets and rules', function () {
    Fieldset::query()->create(['handle' => 'seo', 'title' => 'SEO']);
    builderUser();

    get('/admin/cms/blueprints/create')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Blueprints/Form', shouldExist: false)
        ->where('blueprint', null)
        ->where('fieldTypes.0.type', 'text')
        ->where('fieldTypes', fn ($types) => collect($types)->firstWhere('type', 'repeater')['hasChildren'] === true
            && collect(collect($types)->firstWhere('type', 'select')['settings'])->firstWhere('handle', 'options')['type'] === 'repeater')
        ->where('fieldsets', [['value' => 'seo', 'label' => 'SEO']])
        ->where('rules', fn ($rules) => collect($rules)->contains('email')));
});

it('creates and saves a blueprint', function () {
    builderUser();

    post('/admin/cms/blueprints', ['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea', 'label' => 'Intro', 'required' => true],
    ]])->assertRedirect('/admin/cms/blueprints/1/edit')->assertSessionHas('success');

    put('/admin/cms/blueprints/1', ['title' => 'News article', 'handle' => 'news_article', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea'],
        ['handle' => 'tags', 'type' => 'select', 'config' => ['multiple' => true, 'options' => [['value' => 'a', 'label' => 'A']]]],
    ]])->assertSessionHasNoErrors();

    $blueprint = Blueprint::query()->firstOrFail();
    expect($blueprint->only(['handle', 'title']))->toBe(['handle' => 'news_article', 'title' => 'News article'])
        ->and(array_column($blueprint->fields, 'handle'))->toBe(['intro', 'tags']);

    get('/admin/cms/blueprints/1/edit')->assertInertia(fn (Assert $page) => $page->where('blueprint.fields.1.config.multiple', true));
});

it('sends errors back by field path', function () {
    builderUser();

    post('/admin/cms/blueprints', ['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'body', 'type' => 'text'],
        ['handle' => 'x', 'type' => 'select', 'config' => []],
    ]])->assertSessionHasErrors(['fields.1.config.options']);
    // Handles are checked once the definitions are otherwise valid.
    post('/admin/cms/blueprints', ['handle' => 'article', 'title' => 'Article', 'fields' => [['handle' => 'slug', 'type' => 'text']]])
        ->assertSessionHasErrors(['fields.0.handle']);

    expect(Blueprint::query()->count())->toBe(0);
});

it('refuses to delete a blueprint a collection uses', function () {
    $blueprint = Blueprint::query()->create(['handle' => 'page', 'title' => 'Page']);
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    builderUser();

    delete("/admin/cms/blueprints/{$blueprint->id}")->assertSessionHasErrors('blueprint');
    Collection::query()->delete();
    delete("/admin/cms/blueprints/{$blueprint->id}")->assertRedirect('/admin/cms/blueprints');

    expect(Blueprint::query()->count())->toBe(0);
});

it('builds fieldsets with the same builder, saying where they are used', function () {
    builderUser();

    post('/admin/cms/fieldsets', ['handle' => 'seo', 'title' => 'SEO', 'fields' => [['handle' => 'meta_title', 'type' => 'text']]])
        ->assertRedirect('/admin/cms/fieldsets/1/edit');
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'seo', 'type' => 'fieldset', 'config' => ['fieldset' => 'seo']]]]);

    get('/admin/cms/fieldsets/1/edit')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Blueprints/Form', shouldExist: false)
        ->where('kind', 'fieldset')
        ->where('usedBy', ['the blueprint "Page"']));
    get('/admin/cms/fieldsets')->assertInertia(fn (Assert $page) => $page->where('fieldsets.0.title', 'SEO'));
    delete('/admin/cms/fieldsets/1')->assertSessionHasErrors('fieldset');
});

it('publishes its assets with the app\'s', function () {
    expect(ServiceProvider::pathsToPublish(CmsServiceProvider::class, 'laravel-assets'))
        ->toHaveKey(dirname(__DIR__, 2).'/packages/cms/dist');
});
