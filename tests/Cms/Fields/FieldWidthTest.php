<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\SaveBlueprint;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\DefinitionValidator;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Fieldset;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make())));

/** @return array<string, list<string>> */
function widthErrors(mixed $width): array
{
    try {
        app(DefinitionValidator::class)->validate([['handle' => 'price', 'type' => 'number', 'width' => $width]]);

        return [];
    } catch (ValidationException $e) {
        return $e->errors();
    }
}

it('accepts the widths on offer and refuses others with a clear message', function () {
    foreach (Field::WIDTHS as $width) {
        expect(widthErrors($width))->toBe([]);
    }
    expect(widthErrors(null))->toBe([]);
    foreach ([40, 0, 120, '50', 50.0, 'half', true, [50]] as $width) {
        expect(widthErrors($width))->toBe(['fields.0.width' => ['The width must be one of 25, 33, 50, 66, 75, 100 (percent of the row).']]);
    }
});

it('stores a width only when it isn\'t 100, at every level', function () {
    $fields = app(DefinitionValidator::class)->validate([
        ['handle' => 'price', 'type' => 'number', 'width' => 50],
        ['handle' => 'title_line', 'type' => 'text', 'width' => 100],
        ['handle' => 'body', 'type' => 'textarea'],
        ['handle' => 'hero', 'type' => 'group', 'width' => 66, 'fields' => [['handle' => 'heading', 'type' => 'text', 'width' => 25]]],
    ]);
    $stored = Field::listToArray($fields);

    expect($fields[0]->width)->toBe(50)
        ->and($stored[0]['width'])->toBe(50)
        ->and($fields[1]->width)->toBe(100)
        ->and($stored[1])->not->toHaveKey('width')
        ->and($stored[2])->not->toHaveKey('width')
        ->and($stored[3]['width'])->toBe(66)
        ->and($stored[3]['fields'][0]['width'])->toBe(25)
        // Read back the same, unknown widths as the default.
        ->and(Field::fromArray($stored[0])->width)->toBe(50)
        ->and(Field::fromArray(['handle' => 'a', 'type' => 'text', 'width' => 40])->width)->toBe(100)
        ->and(Field::fromArray(['handle' => 'a', 'type' => 'text'])->width)->toBe(100)
        // Kept through the field's other copies.
        ->and($fields[0]->withConfig(['min' => 1])->width)->toBe(50)
        ->and($fields[3]->withFields([])->width)->toBe(66);
});

it('keeps no width on a fieldset include: its fields have their own', function () {
    Fieldset::query()->create(['handle' => 'address', 'title' => 'Address', 'fields' => [['handle' => 'city', 'type' => 'text', 'width' => 50]]]);
    $fields = app(DefinitionValidator::class)->validate([['handle' => 'address', 'type' => 'fieldset', 'width' => 50, 'config' => ['fieldset' => 'address']]]);

    expect($fields[0]->width)->toBe(100)
        ->and($fields[0]->toArray())->not->toHaveKey('width');
});

it('saves widths in a blueprint written in PHP and sends them to the entry editor', function () {
    Fieldset::query()->create(['handle' => 'address', 'title' => 'Address', 'fields' => [
        ['handle' => 'city', 'type' => 'text', 'width' => 66],
        ['handle' => 'postcode', 'type' => 'text', 'width' => 33],
    ]]);
    $blueprint = app(SaveBlueprint::class)->handle(['handle' => 'listing', 'title' => 'Listing', 'fields' => [
        ['handle' => 'price', 'type' => 'number', 'width' => 50],
        ['handle' => 'area', 'type' => 'number', 'width' => 50],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'address', 'type' => 'fieldset', 'config' => ['fieldset' => 'address']],
        ['handle' => 'agent', 'type' => 'group', 'fields' => [
            ['handle' => 'name', 'type' => 'text', 'width' => 50],
            ['handle' => 'phone', 'type' => 'text', 'width' => 50],
        ]],
        ['handle' => 'rooms', 'type' => 'repeater', 'width' => 75, 'fields' => [['handle' => 'room', 'type' => 'text', 'width' => 25]]],
    ]]);
    expect($blueprint->fields[0]['width'])->toBe(50)
        ->and($blueprint->fields[2])->not->toHaveKey('width');

    $collection = Collection::query()->create(['handle' => 'listings', 'title' => 'Listings', 'blueprint' => 'listing']);
    $entry = app(CreateEntry::class)->handle($collection, ['title' => 'Villa']);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    get(entryEditUrl($entry->entry_id))->assertOk()->assertInertia(function (Assert $page) {
        $fields = collect($page->toArray()['props']['fields'])->keyBy('handle');
        expect($fields['price']['width'])->toBe(50)
            ->and($fields['area']['width'])->toBe(50)
            ->and($fields['body'])->not->toHaveKey('width')
            // The fieldset's fields, at this level, with their own widths.
            ->and($fields['city']['width'])->toBe(66)
            ->and($fields['postcode']['width'])->toBe(33)
            ->and(array_column($fields['agent']['fields'], 'width'))->toBe([50, 50])
            ->and($fields['rooms']['width'])->toBe(75)
            ->and($fields['rooms']['fields'][0]['width'])->toBe(25);
    });
});

it('offers the widths to the blueprint builder', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.blueprints.view', 'cms.blueprints.create', 'cms.blueprints.update'));
    $blueprint = Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'body', 'type' => 'text', 'width' => 33]]]);

    get('/admin/cms/blueprints/create')->assertInertia(fn (Assert $page) => $page->where('widths', [25, 33, 50, 66, 75, 100]));
    get("/admin/cms/blueprints/{$blueprint->handle}/edit")->assertInertia(fn (Assert $page) => $page->where('blueprint.fields.0.width', 33));
});
