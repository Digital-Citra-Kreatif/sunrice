<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Permissions\Permission;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\ListInput;
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
    $field = Pairs::make('languages')->labels('Code', 'Name')->suggest(['en' => 'English'])->firstBadge('Main')->extra('Address', '/{key}', '/')
        ->hints('en, id…', 'As visitors read it', null)->toArray();

    expect($field)->toMatchArray([
        'type' => 'pairs',
        'keyLabel' => 'Code',
        'valueLabel' => 'Name',
        'suggestions' => [['key' => 'en', 'value' => 'English']],
        'firstBadge' => 'Main',
        'extra' => ['label' => 'Address', 'placeholder' => '/{key}', 'firstPlaceholder' => '/'],
        'hints' => ['key' => 'en, id…', 'value' => 'As visitors read it', 'extra' => null],
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

it('gives a field\'s help the record being edited when it is a closure', function () {
    $field = TextInput::make('slug')->help(fn (?Model $record): string => $record === null ? 'Like /{handle}.' : 'Like /'.$record->getAttribute('handle').'.');
    $saved = new class extends Model {};
    $saved->setAttribute('handle', 'news');

    expect($field->toArray()['help'])->toBe('Like /{handle}.')
        ->and($field->toArray($saved)['help'])->toBe('Like /news.')
        ->and(TextInput::make('title')->help('Plain.')->toArray($saved)['help'])->toBe('Plain.');
});

it('puts a field in a titled group only when given one, the title as given', function () {
    expect(Toggle::make('single_1')->group('Posts')->toArray()['group'])->toBe('Posts')
        ->and(Toggle::make('single_1')->toArray())->not->toHaveKey('group')
        ->and(Toggle::make('single_1')->group('Posts')->group(null)->toArray())->not->toHaveKey('group');
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

it('edits a list of addresses as rows, kept as one per line', function () {
    $profiles = ListInput::make('sameAs')->label('Profiles')->urls(httpsOnly: true)->networks()->addLabel('Add profile')->max(3);
    $rules = ['sameAs' => $profiles->validationRules(null)];
    $error = fn (string $value): string => (string) validator(['sameAs' => $value], $rules)->errors()->first('sameAs');

    expect($profiles->toArray())->toMatchArray(['kind' => 'field', 'type' => 'list', 'accepts' => 'url', 'networks' => true, 'addLabel' => 'Add profile', 'max' => 3])
        ->and($error("https://instagram.com/acme\n\n https://www.linkedin.com/company/acme "))->toBe('')
        ->and($error("https://instagram.com/acme\nhttp://example.com"))->toBe('"http://example.com" isn\'t a web address starting with https://.')
        ->and($error("https://a.example\nhttps://b.example\nhttps://c.example\nhttps://d.example"))->toBe('Use at most 3.')
        ->and($profiles->dehydrate("https://instagram.com/acme\n\n https://x.com/acme \n"))->toBe("https://instagram.com/acme\nhttps://x.com/acme");

    $emails = ListInput::make('to')->emails();
    expect(validator(['to' => 'a@example.com, b@example.com'], ['to' => $emails->validationRules(null)])->passes())->toBeTrue()
        ->and((string) validator(['to' => "a@example.com\nnope"], ['to' => $emails->validationRules(null)])->errors()->first('to'))->toBe('"nope" isn\'t an email address.')
        ->and($emails->dehydrate('a@example.com, b@example.com'))->toBe("a@example.com\nb@example.com")
        ->and(ListInput::items("one\n\n two "))->toBe(['one', 'two']);
});

it('folds a developer note with code to copy into a disclosure, said in the panel\'s language', function () {
    $field = Toggle::make('captcha')->help('Not set up yet.')->developerHelp('Set these in .env:', code: "KEY=…\nSECRET=…")->toArray();

    expect($field)->toMatchArray(['help' => 'Not set up yet.', 'developerHelp' => ['text' => 'Set these in .env:', 'example' => null, 'code' => "KEY=…\nSECRET=…"]])
        ->and(TextInput::make('x')->developerHelp('In a template: `:value`', 'x')->toArray()['developerHelp'])->toBe(['text' => 'In a template: `:value`', 'example' => 'x', 'code' => null])
        ->and(TextInput::make('x')->toArray())->not->toHaveKey('developerHelp');
});

it('shows a checkbox list as a matrix only when asked', function () {
    expect(CheckboxList::make('permissions')->options(['a.view' => 'View'])->matrix()->toArray()['matrix'])->toBeTrue()
        ->and(CheckboxList::make('tags')->options(['new' => 'New'])->toArray())->not->toHaveKey('matrix');
});

it('gives a checkbox list\'s options their short labels, in the panel\'s language', function () {
    app('translator')->addLines(['*.Translate' => 'Terjemahkan'], 'id');
    app()->setLocale('id');
    $list = CheckboxList::make('permissions')->matrix()
        ->options(['Content' => ['cms.entries.view' => 'View entries', 'cms.entries.translate' => 'Translate entries, forms and menus']])
        ->shortLabels(fn () => ['cms.entries.translate' => 'Translate', 'cms.gone.view' => 'Gone']);

    expect($list->toArray()['options'])->toBe([
        ['value' => 'cms.entries.view', 'label' => 'View entries', 'group' => 'Content'],
        ['value' => 'cms.entries.translate', 'label' => 'Translate entries, forms and menus', 'group' => 'Content', 'short' => 'Terjemahkan'],
    ])
        // Only how it looks: the values are the same.
        ->and($list->values())->toBe(['cms.entries.view', 'cms.entries.translate'])
        ->and(CheckboxList::make('tags')->options(['new' => 'New'])->toArray()['options'][0])->not->toHaveKey('short');
});

it('gives a permission a short label, keeping the rest', function () {
    $permission = Permission::make('shop.orders.refund', 'Refund orders paid by card or bank transfer', 'Shop');
    $short = $permission->short('Refund');

    expect($permission->short)->toBeNull()
        ->and([$short->name, $short->label, $short->group, $short->short])->toBe(['shop.orders.refund', 'Refund orders paid by card or bank transfer', 'Shop', 'Refund'])
        ->and($short->saidLabel())->toBe('Refund orders paid by card or bank transfer')
        ->and(fn () => $permission->short(' '))->toThrow(InvalidArgumentException::class);
});
