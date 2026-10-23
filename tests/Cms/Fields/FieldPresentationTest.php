<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\SaveBlueprint;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Admin\BuilderData;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\DefinitionValidator;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Fieldset;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make())));

/**
 * @param  array<string, mixed>  $field
 * @return array<string, list<string>>
 */
function presentationErrors(array $field): array
{
    try {
        app(DefinitionValidator::class)->validate([['handle' => 'price', 'type' => 'number'] + $field]);

        return [];
    } catch (ValidationException $e) {
        return $e->errors();
    }
}

it('validates the hint and its tooltip: text, up to 100 and 500 characters', function () {
    expect(presentationErrors(['hint' => 'Before tax', 'hint_tooltip' => 'The price buyers see, before VAT.']))->toBe([])
        ->and(presentationErrors(['hint' => null, 'hint_tooltip' => null]))->toBe([])
        ->and(presentationErrors(['hint' => str_repeat('a', 100), 'hint_tooltip' => str_repeat('a', 500)]))->toBe([])
        ->and(presentationErrors(['hint' => str_repeat('a', 101)]))->toBe(['fields.0.hint' => ['The hint field must not be greater than 100 characters.']])
        ->and(presentationErrors(['hint_tooltip' => str_repeat('a', 501)]))->toBe(['fields.0.hint_tooltip' => ['The hint tooltip field must not be greater than 500 characters.']])
        ->and(presentationErrors(['hint' => 5]))->toHaveKey('fields.0.hint')
        ->and(presentationErrors(['hint_tooltip' => ['no']]))->toHaveKey('fields.0.hint_tooltip');
});

it('validates placeholders, prefixes and suffixes as the types\' settings', function () {
    expect(presentationErrors(['config' => ['placeholder' => '150000', 'prefix' => 'Rp', 'suffix' => 'm²']]))->toBe([])
        ->and(presentationErrors(['config' => ['prefix' => str_repeat('a', 21)]]))->toBe(['fields.0.config.prefix' => ['The "Prefix" field must not be greater than 20 characters.']])
        ->and(presentationErrors(['config' => ['suffix' => str_repeat('a', 21)]]))->toHaveKey('fields.0.config.suffix')
        ->and(presentationErrors(['config' => ['placeholder' => str_repeat('a', 256)]]))->toHaveKey('fields.0.config.placeholder')
        ->and(presentationErrors(['config' => ['prefix' => ['Rp']]]))->toHaveKey('fields.0.config.prefix');

    $fields = app(DefinitionValidator::class)->validate([
        ['handle' => 'site', 'type' => 'text', 'config' => ['prefix' => 'https://', 'placeholder' => 'example.com', 'suffix' => '']],
        ['handle' => 'about', 'type' => 'textarea', 'config' => ['placeholder' => 'A few words', 'prefix' => 'not offered']],
        ['handle' => 'map', 'type' => 'link', 'config' => ['placeholder' => 'https://maps.example.com/…']],
    ]);
    // Kept only where the type offers them, and not when empty.
    expect($fields[0]->config)->toEqual(['placeholder' => 'example.com', 'prefix' => 'https://'])
        ->and($fields[1]->config)->toBe(['placeholder' => 'A few words'])
        ->and($fields[2]->config)->toBe(['placeholder' => 'https://maps.example.com/…']);
});

it('stores the hint and tooltip only when not empty, at every level, and reads them back', function () {
    $fields = app(DefinitionValidator::class)->validate([
        ['handle' => 'price', 'type' => 'number', 'hint' => 'Before tax', 'hint_tooltip' => 'Without VAT.'],
        ['handle' => 'title_line', 'type' => 'text', 'hint' => '  ', 'hint_tooltip' => ''],
        ['handle' => 'agent', 'type' => 'group', 'hint' => 'Who to call', 'fields' => [['handle' => 'name', 'type' => 'text', 'hint_tooltip' => 'As on their card.']]],
    ]);
    $stored = Field::listToArray($fields);

    expect($fields[0]->hint)->toBe('Before tax')
        ->and($fields[0]->hintTooltip)->toBe('Without VAT.')
        ->and($stored[0]['hint'])->toBe('Before tax')
        ->and($stored[0]['hint_tooltip'])->toBe('Without VAT.')
        ->and($stored[1])->not->toHaveKey('hint')
        ->and($stored[1])->not->toHaveKey('hint_tooltip')
        ->and($stored[2]['hint'])->toBe('Who to call')
        ->and($stored[2]['fields'][0])->not->toHaveKey('hint')
        ->and($stored[2]['fields'][0]['hint_tooltip'])->toBe('As on their card.')
        ->and(Field::fromArray($stored[0])->hint)->toBe('Before tax')
        ->and(Field::fromArray(['handle' => 'a', 'type' => 'text', 'hint' => 7])->hint)->toBe('')
        // Kept through the field's other copies.
        ->and($fields[0]->withConfig(['min' => 1])->hintTooltip)->toBe('Without VAT.')
        ->and($fields[2]->withFields([])->hint)->toBe('Who to call');
});

it('keeps no hint on a fieldset include: its fields have their own', function () {
    Fieldset::query()->create(['handle' => 'address', 'title' => 'Address', 'fields' => [['handle' => 'city', 'type' => 'text', 'hint' => 'Town or city']]]);
    $fields = app(DefinitionValidator::class)->validate([['handle' => 'address', 'type' => 'fieldset', 'hint' => 'x', 'hint_tooltip' => 'y', 'config' => ['fieldset' => 'address']]]);

    expect($fields[0]->toArray())->not->toHaveKey('hint')->not->toHaveKey('hint_tooltip');
});

it('saves a blueprint written in PHP with its presentation and sends it to the entry editor', function () {
    Fieldset::query()->create(['handle' => 'address', 'title' => 'Address', 'fields' => [['handle' => 'city', 'type' => 'text', 'hint' => 'Town or city']]]);
    $blueprint = app(SaveBlueprint::class)->handle(['handle' => 'listing', 'title' => 'Listing', 'fields' => [
        ['handle' => 'price', 'type' => 'number', 'hint' => 'Before tax', 'hint_tooltip' => 'What buyers pay, without VAT.',
            'instructions' => 'See the [price guide](https://example.com/prices) — **whole rupiah**.', 'config' => ['prefix' => 'Rp', 'placeholder' => '150000']],
        ['handle' => 'area', 'type' => 'number', 'width' => 50, 'config' => ['suffix' => 'm²']],
        ['handle' => 'website', 'type' => 'text', 'config' => ['prefix' => 'https://', 'placeholder' => 'example.com']],
        ['handle' => 'summary', 'type' => 'textarea', 'config' => ['placeholder' => 'Two or three sentences']],
        ['handle' => 'address', 'type' => 'fieldset', 'config' => ['fieldset' => 'address']],
    ]]);
    expect($blueprint->fields[0])->toMatchArray(['hint' => 'Before tax', 'hint_tooltip' => 'What buyers pay, without VAT.', 'config' => ['placeholder' => '150000', 'prefix' => 'Rp']]);

    $collection = Collection::query()->create(['handle' => 'listings', 'title' => 'Listings', 'blueprint' => 'listing']);
    $entry = app(CreateEntry::class)->handle($collection, ['title' => 'Villa']);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    get(entryEditUrl($entry->entry_id))->assertOk()->assertInertia(function (Assert $page) {
        $fields = collect($page->toArray()['props']['fields'])->keyBy('handle');
        expect($fields['price'])->toMatchArray(['hint' => 'Before tax', 'hint_tooltip' => 'What buyers pay, without VAT.', 'instructions' => 'See the [price guide](https://example.com/prices) — **whole rupiah**.'])
            ->and($fields['price']['config'])->toEqual(['placeholder' => '150000', 'prefix' => 'Rp'])
            ->and($fields['area']['config'])->toBe(['suffix' => 'm²'])
            ->and($fields['area'])->not->toHaveKey('hint')
            ->and($fields['website']['config'])->toEqual(['placeholder' => 'example.com', 'prefix' => 'https://'])
            ->and($fields['summary']['config'])->toBe(['placeholder' => 'Two or three sentences'])
            ->and($fields['city']['hint'])->toBe('Town or city');
    });
});

it('offers the placeholder, prefix and suffix as the types\' settings in the builder', function () {
    $settings = collect(app(BuilderData::class)->toArray()['fieldTypes'])->mapWithKeys(fn (array $type) => [$type['type'] => array_column($type['settings'], 'handle')]);

    expect($settings['text'])->toContain('placeholder', 'prefix', 'suffix')
        ->and($settings['number'])->toContain('placeholder', 'prefix', 'suffix')
        ->and($settings['textarea'])->toContain('placeholder')->not->toContain('prefix')
        ->and($settings['link'])->toContain('placeholder')->not->toContain('prefix')
        ->and($settings['select'])->not->toContain('placeholder');
});
