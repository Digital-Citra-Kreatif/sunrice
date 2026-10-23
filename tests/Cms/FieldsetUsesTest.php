<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Fieldsets\DeleteFieldset;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\DefinitionValidator;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Fieldset;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

/*
 * A fieldset's page says who uses it, through which field and how: a
 * fieldset field includes it, or a flexible field offers it as a block.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.blueprints.view', 'cms.blueprints.update', 'cms.fieldsets.view', 'cms.fieldsets.update', 'cms.fieldsets.delete'));
    $this->quote = Fieldset::query()->create(['handle' => 'quote', 'title' => 'Quote', 'fields' => [['handle' => 'text', 'type' => 'textarea']]]);
});

it('says a flexible field offers it as a block', function () {
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'title_note', 'type' => 'text'],
        ['handle' => 'sections', 'type' => 'flexible', 'label' => 'More', 'config' => ['blocks' => ['quote']]],
    ]]);

    expect(app(DeleteFieldset::class)->uses($this->quote))->toBe([[
        'kind' => 'blueprint', 'handle' => 'post', 'title' => 'Post',
        'uses' => [['field' => 'More', 'how' => 'block']],
    ]]);
    get(route('sunrice.admin.cms.fieldsets.edit', 'quote'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('usedBy.0.title', 'Post')->where('usedBy.0.url', route('sunrice.admin.cms.blueprints.edit', 'post'))
        ->where('usedBy.0.uses', [['field' => 'More', 'how' => 'block']]));
});

it('says a fieldset field includes it, naming the field by its label or handle', function () {
    Fieldset::query()->create(['handle' => 'hero', 'title' => 'Hero', 'fields' => [
        ['handle' => 'intro', 'type' => 'fieldset', 'label' => 'Intro', 'config' => ['fieldset' => 'quote']],
        ['handle' => 'outro', 'type' => 'fieldset', 'config' => ['fieldset' => 'quote']],
    ]]);

    expect(app(DeleteFieldset::class)->uses($this->quote))->toBe([[
        'kind' => 'fieldset', 'handle' => 'hero', 'title' => 'Hero',
        'uses' => [['field' => 'Intro', 'how' => 'include'], ['field' => 'outro', 'how' => 'include']],
    ]]);
    get(route('sunrice.admin.cms.fieldsets.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('fieldsets.0.title', 'Hero')->where('fieldsets.0.usedBy', [])
        ->where('fieldsets.1.title', 'Quote')->where('fieldsets.1.usedBy', ['Hero']));
});

it('gives the path to a nested field', function () {
    $fields = [
        new Field('sections', 'group', 'Sections', fields: [
            new Field('columns', 'flexible', 'Columns', config: ['blocks' => ['quote', 'hero']]),
            new Field('aside', 'repeater', fields: [new Field('seo', 'fieldset', config: ['fieldset' => 'seo'])]),
        ]),
    ];

    expect(DefinitionValidator::fieldsetUses($fields))->toBe([
        ['fieldset' => 'quote', 'path' => ['Sections', 'Columns'], 'how' => 'block'],
        ['fieldset' => 'hero', 'path' => ['Sections', 'Columns'], 'how' => 'block'],
        ['fieldset' => 'seo', 'path' => ['Sections', 'aside', 'seo'], 'how' => 'include'],
    ]);

    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'sections', 'type' => 'group', 'label' => 'Sections', 'fields' => [
            ['handle' => 'columns', 'type' => 'flexible', 'label' => 'Columns', 'config' => ['blocks' => ['quote']]],
        ]],
    ]]);
    expect(app(DeleteFieldset::class)->usedBy($this->quote))->toBe(['the blueprint "Page" (a block in its "Sections › Columns" field)']);
});

it('ignores a fieldset offering itself as a block', function () {
    $this->quote->update(['fields' => [['handle' => 'more', 'type' => 'flexible', 'config' => ['blocks' => ['quote']]]]]);

    expect(app(DeleteFieldset::class)->uses($this->quote->refresh()))->toBe([]);
});

it('refuses to delete it, saying where it is used', function () {
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'sections', 'type' => 'flexible', 'label' => 'More', 'config' => ['blocks' => ['quote']]],
    ]]);
    Fieldset::query()->create(['handle' => 'hero', 'title' => 'Hero', 'fields' => [
        ['handle' => 'intro', 'type' => 'fieldset', 'label' => 'Intro', 'config' => ['fieldset' => 'quote']],
    ]]);

    expect(fn () => app(DeleteFieldset::class)->handle($this->quote))->toThrow(ValidationException::class,
        '"Quote" is used by the blueprint "Post" (a block in its "More" field), the fieldset "Hero" (included as "Intro"). Remove it from there first.');
    delete(route('sunrice.admin.cms.fieldsets.destroy', 'quote'))->assertSessionHasErrors('fieldset');
    expect(Fieldset::query()->where('handle', 'quote')->exists())->toBeTrue();
});
