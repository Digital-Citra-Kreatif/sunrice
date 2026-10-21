<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Pairs;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Resources\Tables\Action;

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
    ])->and(Pairs::make('x')->validationRules(null))->toContain('string')
        ->and(Pairs::make('x')->toArray()['fixed'])->toBeNull()
        ->and(Pairs::make('x')->fixed(['id' => 'Bahasa Indonesia'])->toArray()['fixed'])->toBe([['key' => 'id', 'label' => 'Bahasa Indonesia']]);
});

it('sends a field\'s change warning to edit forms only, in the panel\'s language', function () {
    $field = TextInput::make('handle')->warnWhenChanged('Users keep this role and its permissions. Code that checks it by name (hasRole(\':old\'), @role(\':old\')) needs the new one, and if ":old" is one of the panel\'s default roles, sunrice:roles creates it again.');
    $saved = new class extends Model {};
    $saved->exists = true;

    expect($field->toArray($saved)['warnWhenChanged'])->toContain('hasRole(\':old\')')
        ->and($field->toArray(new class extends Model {})['warnWhenChanged'])->toBeNull()
        ->and($field->toArray()['warnWhenChanged'])->toBeNull()
        ->and(TextInput::make('title')->toArray($saved)['warnWhenChanged'])->toBeNull();

    app()->setLocale('id');
    expect($field->toArray($saved)['warnWhenChanged'])->toStartWith('Pengguna tetap');
});

it('marks a select whose options leave out an action\'s own records, and adds what an action says about one record', function () {
    expect(Select::make('heir')->options(['1' => 'A'])->toArray())->not->toHaveKey('withoutTargets')
        ->and(Select::make('heir')->options(['1' => 'A'])->withoutTargets()->toArray()['withoutTargets'])->toBeTrue();

    $action = Action::make('delete')->confirm('Delete?')->confirmFor(fn (Model $record): ?string => $record->getAttribute('name') === 'Rina' ? 'She owns 2 pages.' : null);
    $rina = new class extends Model {};
    $rina->setAttribute('name', 'Rina');
    expect($action->toArray()['confirm'])->toBe('Delete?')
        ->and($action->toArray($rina)['confirm'])->toBe('Delete? She owns 2 pages.')
        ->and($action->toArray(new class extends Model {})['confirm'])->toBe('Delete?');
});

it('groups a select\'s options under headings, and accepts only listed values', function () {
    $field = Select::make('kind')->options(['Shops' => ['Store' => 'Store', 'Bakery' => 'Bakery'], 'Other' => 'Something else']);

    expect($field->toArray()['options'])->toBe([
        ['value' => 'Store', 'label' => 'Store', 'group' => 'Shops'],
        ['value' => 'Bakery', 'label' => 'Bakery', 'group' => 'Shops'],
        ['value' => 'Other', 'label' => 'Something else'],
    ])->and($field->toArray())->not->toHaveKey('other')
        ->and(validator(['kind' => 'Bakery'], ['kind' => $field->validationRules(null)])->passes())->toBeTrue()
        ->and(validator(['kind' => 'Shops'], ['kind' => $field->validationRules(null)])->passes())->toBeFalse();
});

it('takes a value besides the options with ->other(), checked by its rules', function () {
    $field = Select::make('type')->label('Type')->options(['Store' => 'Store'])->searchable()->other('Other…', ['regex:/^[A-Z][A-Za-z]+$/'], 'Brewery');
    $passes = fn (mixed $value): bool => validator(['type' => $value], ['type' => $field->validationRules(null)])->passes();

    expect($field->toArray())->toMatchArray(['searchable' => true, 'other' => ['label' => 'Other…', 'placeholder' => 'Brewery']])
        ->and($passes('Store'))->toBeTrue()
        ->and($passes('Brewery'))->toBeTrue()
        ->and($passes('not a type'))->toBeFalse()
        ->and(validator(['type' => 'no'], ['type' => $field->validationRules(null)])->errors()->first('type'))->toContain('Type');
});
