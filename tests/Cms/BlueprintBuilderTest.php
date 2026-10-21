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
    actingAs(adminUser(Panel::ACCESS, 'cms.blueprints.view', 'cms.blueprints.create', 'cms.blueprints.update', 'cms.blueprints.delete', 'cms.fieldsets.view', 'cms.fieldsets.create', 'cms.fieldsets.update', 'cms.fieldsets.delete'));
}

it('is behind the blueprint and fieldset permissions', function () {
    $blueprint = Blueprint::query()->create(['handle' => 'page', 'title' => 'Page']);
    $fieldset = Fieldset::query()->create(['handle' => 'seo', 'title' => 'SEO']);
    actingAs(adminUser(Panel::ACCESS));

    get('/admin/cms/blueprints')->assertForbidden();
    get('/admin/cms/blueprints/create')->assertForbidden();
    post('/admin/cms/blueprints', ['handle' => 'x', 'title' => 'X', 'fields' => []])->assertForbidden();
    get("/admin/cms/blueprints/{$blueprint->handle}/edit")->assertForbidden();
    put("/admin/cms/blueprints/{$blueprint->handle}", ['title' => 'X', 'fields' => []])->assertForbidden();
    delete("/admin/cms/blueprints/{$blueprint->handle}")->assertForbidden();
    get('/admin/cms/fieldsets')->assertForbidden();
    delete("/admin/cms/fieldsets/{$fieldset->handle}")->assertForbidden();

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
        ->component('Cms/Blueprints/Edit', shouldExist: false)
        ->where('blueprint', null)
        ->where('fieldTypes.0.type', 'text')
        // Each type's default for "Translatable": text is translated, numbers are shared.
        ->where('fieldTypes.0.translatable', true)
        ->where('fieldTypes', fn ($types) => collect($types)->firstWhere('type', 'number')['translatable'] === false)
        ->where('fieldTypes', fn ($types) => collect($types)->firstWhere('type', 'repeater')['hasChildren'] === true
            && collect(collect($types)->firstWhere('type', 'select')['settings'])->firstWhere('handle', 'options')['type'] === 'repeater')
        ->where('fieldsets', [['value' => 'seo', 'label' => 'SEO']])
        ->where('rules', fn ($rules) => collect($rules)->contains('email')));
});

it('creates and saves a blueprint', function () {
    builderUser();

    post('/admin/cms/blueprints', ['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea', 'label' => 'Intro', 'required' => true],
    ]])->assertRedirect('/admin/cms/blueprints/article/edit')->assertSessionHas('success');

    put('/admin/cms/blueprints/article', ['title' => 'News article', 'handle' => 'news_article', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea'],
        ['handle' => 'tags', 'type' => 'select', 'config' => ['multiple' => true, 'options' => [['value' => 'a', 'label' => 'A']]]],
    ]])->assertSessionHasNoErrors();

    $blueprint = Blueprint::query()->firstOrFail();
    expect($blueprint->only(['handle', 'title']))->toBe(['handle' => 'news_article', 'title' => 'News article'])
        ->and(array_column($blueprint->fields, 'handle'))->toBe(['intro', 'tags']);

    get('/admin/cms/blueprints/news_article/edit')->assertInertia(fn (Assert $page) => $page->where('blueprint.fields.1.config.multiple', true));
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

    delete("/admin/cms/blueprints/{$blueprint->handle}")->assertSessionHasErrors('blueprint');
    Collection::query()->delete();
    delete("/admin/cms/blueprints/{$blueprint->handle}")->assertRedirect('/admin/cms/blueprints');

    expect(Blueprint::query()->count())->toBe(0);
});

it('builds fieldsets with the same builder, saying where they are used', function () {
    builderUser();

    post('/admin/cms/fieldsets', ['handle' => 'seo', 'title' => 'SEO', 'fields' => [['handle' => 'meta_title', 'type' => 'text']]])
        ->assertRedirect('/admin/cms/fieldsets/seo/edit');
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'seo', 'type' => 'fieldset', 'config' => ['fieldset' => 'seo']]]]);

    get('/admin/cms/fieldsets/seo/edit')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Fieldsets/Edit', shouldExist: false)
        ->where('kind', 'fieldset')
        ->where('usedBy', ['the blueprint "Page"']));
    get('/admin/cms/fieldsets')->assertInertia(fn (Assert $page) => $page->where('fieldsets.0.title', 'SEO'));
    delete('/admin/cms/fieldsets/seo')->assertSessionHasErrors('fieldset');
});

it('publishes its assets with the app\'s', function () {
    expect(ServiceProvider::pathsToPublish(CmsServiceProvider::class, 'laravel-assets'))
        ->toHaveKey(dirname(__DIR__, 2).'/packages/cms/dist');
});

it('gives the builder the handles content keeps for itself, to warn as they are typed', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.blueprints.view', 'cms.blueprints.create'));

    get('/admin/cms/blueprints/create')->assertInertia(fn (Assert $page) => $page->where('reserved', Blueprint::RESERVED));
    expect(Blueprint::RESERVED)->toContain('status', 'author');
});

it('keeps what visitors read of a field in other languages', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    builderUser();

    get('/admin/cms/blueprints/create')->assertInertia(fn (Assert $page) => $page->where('locales.0.code', 'en')->where('locales.1.code', 'id'));
    post('/admin/cms/blueprints', ['handle' => 'lead', 'title' => 'Lead', 'fields' => [
        ['handle' => 'kind', 'type' => 'select', 'label' => 'Property type', 'config' => ['options' => [['value' => 'house', 'label' => 'House']]],
            'translations' => ['id' => ['label' => 'Jenis properti', 'instructions' => '', 'options' => ['house' => 'Rumah']]],
            'condition' => null],
        ['handle' => 'group', 'type' => 'group', 'fields' => [['handle' => 'name', 'type' => 'text', 'translations' => ['id' => ['label' => 'Nama']]]],
            'condition' => ['field' => 'kind', 'operator' => 'filled', 'value' => null]],
    ]])->assertSessionHasNoErrors();

    $blueprint = Blueprint::query()->firstOrFail();
    $schema = $blueprint->schema();
    expect($blueprint->fields[0]['translations'])->toBe(['id' => ['label' => 'Jenis properti', 'options' => ['house' => 'Rumah']]])
        ->and($schema->field('kind')?->labelIn('id'))->toBe('Jenis properti')
        ->and($schema->field('kind')?->optionLabel('house', 'id'))->toBe('Rumah')
        // A group's children and condition survive the schema too.
        ->and($schema->field('group')?->fields[0]->labelIn('id'))->toBe('Nama')
        ->and($schema->field('group')?->condition['operator'] ?? null)->toBe('filled');

    post('/admin/cms/blueprints', ['handle' => 'bad', 'title' => 'Bad', 'fields' => [['handle' => 'x', 'type' => 'text', 'translations' => ['Not a code!' => ['label' => 'X']]]]])
        ->assertSessionHasErrors(['fields.0.translations']);
});
