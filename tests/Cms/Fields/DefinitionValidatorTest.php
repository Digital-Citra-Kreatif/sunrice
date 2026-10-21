<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Sunrice\Cms\Fields\DefinitionValidator;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Fieldset;

/** The validation errors for definitions, by path ([] when valid). */
function definitionErrors(mixed $fields, array $reserved = [], ?string $fieldset = null): array
{
    try {
        app(DefinitionValidator::class)->validate($fields, reserved: $reserved, fieldset: $fieldset);

        return [];
    } catch (ValidationException $e) {
        return $e->errors();
    }
}

it('returns the definitions as stored', function () {
    $fields = app(DefinitionValidator::class)->validate([
        ['handle' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'config' => ['max' => '80', 'unknown' => 'x']],
        ['handle' => 'body', 'type' => 'textarea', 'fields' => [['handle' => 'dropped', 'type' => 'text']]],
    ]);

    expect($fields[0])->toEqual(new Field('title', 'text', 'Title', required: true, config: ['max' => 80]))
        ->and($fields[1]->fields)->toBe([]);
});

it('stores a select\'s options without content row metadata', function () {
    $fields = app(DefinitionValidator::class)->validate([
        ['handle' => 'tone', 'type' => 'select', 'config' => ['options' => [['value' => 'calm', 'label' => ''], ['value' => 'loud', 'label' => 'Loud', '_hidden' => true]]]],
    ]);

    expect($fields[0]->config['options'])->toBe([['value' => 'calm'], ['value' => 'loud', 'label' => 'Loud']]);
});

it('checks handles and types', function () {
    expect(definitionErrors([['handle' => 'Bad Handle', 'type' => 'text']]))->toHaveKey('fields.0.handle')
        ->and(definitionErrors([['handle' => 'map', 'type' => 'map']]))->toHaveKey('fields.0.type')
        ->and(definitionErrors([['handle' => 'a', 'type' => 'text'], ['handle' => 'a', 'type' => 'number']]))->toHaveKey('fields.1.handle')
        ->and(definitionErrors('nope'))->toHaveKey('fields')
        ->and(definitionErrors([['handle' => 'a', 'type' => 'text', 'required' => 'yes']]))->toHaveKey('fields.0.required');
});

it('keeps handles the content uses for itself', function () {
    expect(definitionErrors([['handle' => 'slug', 'type' => 'text']], Blueprint::RESERVED))->toHaveKey('fields.0.handle')
        // Inside a group they are fine.
        ->and(definitionErrors([['handle' => 'hero', 'type' => 'group', 'fields' => [['handle' => 'title', 'type' => 'text']]]], Blueprint::RESERVED))->toBe([]);
});

it('checks each type\'s settings', function () {
    expect(definitionErrors([['handle' => 'a', 'type' => 'text', 'config' => ['max' => 1000]]]))->toHaveKey('fields.0.config.max')
        ->and(definitionErrors([['handle' => 'a', 'type' => 'select', 'config' => []]]))->toHaveKey('fields.0.config.options')
        ->and(definitionErrors([['handle' => 'a', 'type' => 'select', 'config' => ['options' => [['value' => 's']]]]]))->toBe([]);
});

it('allows only safe extra rules', function () {
    expect(definitionErrors([['handle' => 'a', 'type' => 'text', 'validation' => ['exists:users,password']]]))->toHaveKey('fields.0.validation.0')
        ->and(definitionErrors([['handle' => 'a', 'type' => 'text', 'validation' => ['min:3', 'regex:/^[a-z]+$/']]]))->toBe([]);
});

it('checks children of groups and repeaters, up to five levels', function () {
    $nest = fn (array $inner) => [['handle' => 'g', 'type' => 'group', 'fields' => $inner]];

    expect(definitionErrors($nest([['handle' => 'x', 'type' => 'text'], ['handle' => 'x', 'type' => 'text']])))->toHaveKey('fields.0.fields.1.handle')
        ->and(definitionErrors($nest($nest($nest($nest([['handle' => 'x', 'type' => 'text']]))))))->toBe([])
        ->and(definitionErrors($nest($nest($nest($nest($nest([['handle' => 'x', 'type' => 'text']])))))))->not->toBe([]);
});

it('counts included fields as the blueprint\'s own', function () {
    Fieldset::query()->create(['handle' => 'seo', 'title' => 'SEO', 'fields' => [['handle' => 'meta_title', 'type' => 'text']]]);

    expect(definitionErrors([['handle' => 'meta_title', 'type' => 'text'], ['handle' => 'seo', 'type' => 'fieldset', 'config' => ['fieldset' => 'seo']]]))
        ->toHaveKey('fields.1.config.fieldset'); // shown under the include's fieldset choice (its handle box is hidden)
});

it('refuses a fieldset that would include itself', function () {
    Fieldset::query()->create(['handle' => 'a', 'title' => 'A', 'fields' => [['handle' => 'b', 'type' => 'fieldset', 'config' => ['fieldset' => 'b']]]]);
    Fieldset::query()->create(['handle' => 'b', 'title' => 'B', 'fields' => []]);

    expect(definitionErrors([['handle' => 'me', 'type' => 'fieldset', 'config' => ['fieldset' => 'b']]], fieldset: 'b'))->toHaveKey('fields.0.config.fieldset')
        ->and(definitionErrors([['handle' => 'a', 'type' => 'fieldset', 'config' => ['fieldset' => 'a']]], fieldset: 'b'))->toHaveKey('fields.0.config.fieldset')
        // A fieldset may use itself as a kind of block: blocks nest only as deep as the content does.
        ->and(definitionErrors([['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['b']]]], fieldset: 'b'))->toBe([]);
});

it('only offers existing fieldsets', function () {
    expect(definitionErrors([['handle' => 'x', 'type' => 'fieldset', 'config' => ['fieldset' => 'nope']]]))->toHaveKey('fields.0.config.fieldset')
        ->and(definitionErrors([['handle' => 'x', 'type' => 'flexible', 'config' => ['blocks' => ['nope']]]]))->toHaveKey('fields.0.config.blocks.0');
});
