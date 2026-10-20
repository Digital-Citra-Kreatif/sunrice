<?php

declare(strict_types=1);

use Sunrice\Admin\Resources\Forms\Pairs;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;

it('sends a field\'s condition and slug source to the form', function () {
    expect(TextInput::make('template')->showWhen('route')->toArray()['showWhen'])->toBe(['field' => 'route', 'value' => null])
        ->and(Toggle::make('x')->showWhen('kind', 'link')->toArray()['showWhen'])->toBe(['field' => 'kind', 'value' => 'link'])
        ->and(TextInput::make('title')->toArray()['showWhen'])->toBeNull()
        ->and(TextInput::make('handle')->slugFrom('title')->toArray())->toMatchArray(['slugFrom' => 'title', 'slugSeparator' => '_'])
        ->and(TextInput::make('path')->slugFrom('title', '-')->toArray()['slugSeparator'])->toBe('-');
});

it('describes pairs: their labels, suggestions, first badge and third column', function () {
    $field = Pairs::make('languages')->labels('Code', 'Name')->suggest(['en' => 'English'])->firstBadge('Main')->extra('Address', '/{key}', '/')->toArray();

    expect($field)->toMatchArray([
        'type' => 'pairs',
        'keyLabel' => 'Code',
        'valueLabel' => 'Name',
        'suggestions' => [['key' => 'en', 'value' => 'English']],
        'firstBadge' => 'Main',
        'extra' => ['label' => 'Address', 'placeholder' => '/{key}', 'firstPlaceholder' => '/'],
    ])->and(Pairs::make('x')->validationRules(null))->toContain('string');
});
