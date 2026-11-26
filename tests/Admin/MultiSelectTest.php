<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Actions\Settings\UpdateSettings;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Tests\Admin\Fixtures\Palette;
use Sunrice\Tests\Admin\Fixtures\PaletteResource;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ShopSettings;
use Sunrice\Tests\Admin\Fixtures\Tag;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

beforeEach(function () {
    Product::createTables(); // the tags table
    Palette::createTables();
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->resources(PaletteResource::class))));
});

/** Signed in with the panel and these palette permissions ("view", "create" …). */
function paletteUser(string ...$abilities): void
{
    actingAs(adminUser(Panel::ACCESS, ...array_map(fn ($a) => "shop.palettes.{$a}", $abilities)));
}

/** The rules a form would check for these fields, nested ones included. */
function multiRules(Select ...$fields): array
{
    $rules = [];
    foreach ($fields as $field) {
        $rules[$field->name] = $field->validationRules(null);
        $rules = [...$rules, ...$field->nestedRules()];
    }

    return $rules;
}

function colours(): Select
{
    return Select::make('colours')->label('Colours')->multiple()->options(['Warm' => ['red' => 'Red', 'orange' => 'Orange'], 'blue' => 'Blue', 'green' => 'Green']);
}

it('refuses a multiple select value that isn\'t a list of listed, distinct strings', function () {
    $rules = multiRules(colours());
    $errors = fn (mixed $value) => validator(['colours' => $value], $rules)->errors();

    expect($errors(['red', 'blue'])->isEmpty())->toBeTrue()
        ->and($errors(null)->isEmpty())->toBeTrue() // not required: may be left out
        ->and($errors(['purple'])->has('colours.0'))->toBeTrue()
        ->and($errors(['red', 'purple'])->has('colours.1'))->toBeTrue()
        ->and($errors(['Warm'])->has('colours.0'))->toBeTrue() // a group's heading isn't an option
        ->and($errors([['red']])->has('colours.0'))->toBeTrue()
        ->and($errors([['red' => 'red']])->has('colours.0'))->toBeTrue()
        ->and($errors([true])->has('colours.0'))->toBeTrue()
        ->and($errors(['red', 'red'])->has('colours.0'))->toBeTrue()
        ->and($errors('red,blue')->has('colours'))->toBeTrue()
        ->and($errors(['a' => 'red'])->isEmpty())->toBeTrue(); // keyed: still each value checked; dehydrate makes it a list

    expect(colours()->dehydrate(['a' => 'red', 'b' => 'blue']))->toBe(['red', 'blue']);
});

it('needs at least one choice when required, and takes count rules', function () {
    $field = colours()->required()->rules(['min:2', 'max:3']);
    $rules = multiRules($field);
    $passes = fn (mixed $value): bool => validator(['colours' => $value], $rules)->passes();

    expect($passes([]))->toBeFalse()
        ->and($passes(null))->toBeFalse()
        ->and($passes(['red']))->toBeFalse()
        ->and($passes(['red', 'blue']))->toBeTrue()
        ->and($passes(['red', 'blue', 'green']))->toBeTrue()
        ->and($passes(['red', 'blue', 'green', 'orange']))->toBeFalse()
        ->and($field->toArray()['required'])->toBeTrue();
});

it('checks the choices against options from a closure, given the record', function () {
    $saved = new class extends Model {};
    $saved->exists = true;
    $field = Select::make('colours')->multiple()->options(fn (?Model $record) => $record === null ? ['red' => 'Red'] : ['red' => 'Red', 'blue' => 'Blue']);

    $passes = function (?Model $record) use ($field): bool {
        $rules = ['colours' => $field->validationRules($record), ...$field->nestedRules()];

        return validator(['colours' => ['red', 'blue']], $rules)->passes();
    };

    expect($passes(null))->toBeFalse()
        ->and($field->dehydrate(['red', 'blue']))->toBe(['red'])
        ->and($passes($saved))->toBeTrue()
        ->and($field->dehydrate(['red', 'blue']))->toBe(['red', 'blue']);
});

it('shows a record\'s saved choices for its own options, even before the form is described', function () {
    $saved = new class extends Model {};
    $saved->exists = true;
    $saved->setAttribute('colours', ['red', 'blue']);
    $field = Select::make('colours')->multiple()->options(fn (?Model $record) => $record === null ? ['red' => 'Red'] : ['red' => 'Red', 'blue' => 'Blue']);

    // AI tools read the values (currentValues) without describing the field first.
    expect($field->formValue($saved))->toBe(['red', 'blue']);
})->todo('BUG: Select::formValue() resolves an options closure with the record of the last toArray()/validationRules() call, not the one passed in: with record-dependent options a saved choice is dropped (packages/admin/src/Resources/Forms/Select.php:135-143)');

it('reads the saved value as a list of listed options, as strings', function () {
    $record = fn (mixed $value) => tap(new class extends Model {}, fn (Model $m) => $m->setAttribute('colours', $value));

    expect(colours()->formValue($record(['blue', 'red'])))->toBe(['blue', 'red']) // in the order saved
        ->and(colours()->formValue($record(['blue', 'gone', 'blue'])))->toBe(['blue'])
        ->and(colours()->formValue($record(null)))->toBe([])
        ->and(colours()->formValue(null))->toBe([]) // no default: an empty list, not null
        ->and(colours()->default(['green', 'nope'])->formValue(null))->toBe(['green'])
        ->and(Select::make('sizes')->multiple()->options([1 => 'S', 2 => 'L'])->formValue(tap(new class extends Model {}, fn (Model $m) => $m->setAttribute('sizes', [2, 1]))))->toBe(['2', '1']);
});

it('keeps single-select behaviour without ->multiple(), and multiple(false) switches it back', function () {
    $single = Select::make('finish')->label('Finish')->options(['matte' => 'Matte', 'gloss' => 'Gloss']);
    $off = colours()->multiple(false);
    $rules = multiRules($single, $off);
    $record = tap(new class extends Model {}, fn (Model $m) => $m->setAttribute('finish', 'gloss'));

    expect(validator(['finish' => 'matte', 'colours' => 'red'], $rules)->passes())->toBeTrue()
        ->and(validator(['finish' => ['matte']], $rules)->passes())->toBeFalse()
        ->and(validator(['finish' => 'satin'], $rules)->passes())->toBeFalse()
        ->and(validator(['colours' => ['red']], $rules)->passes())->toBeFalse()
        ->and($single->formValue($record))->toBe('gloss')
        ->and($single->formValue(null))->toBeNull()
        ->and($single->dehydrate('matte'))->toBe('matte')
        ->and($off->toArray())->not->toHaveKey('multiple')
        ->and($off->nestedRules())->toBe([])
        ->and($single->validationRules(null))->not->toContain('array');
});

it('offers no "Other…" with ->multiple(), and refuses a value besides the options', function () {
    $field = colours()->other('Other…', ['regex:/^[a-z]+$/'])->multiple();

    expect($field->toArray())->not->toHaveKey('other')
        ->and(validator(['colours' => ['purple']], multiRules($field))->passes())->toBeFalse()
        ->and($field->dehydrate(['purple', 'red']))->toBe(['red']);
});

it('sends its options, grouped, with multiple and searchable to the form', function () {
    $field = colours()->searchable()->placeholder('Pick colours')->toArray();

    expect($field)->toMatchArray(['type' => 'select', 'multiple' => true, 'searchable' => true, 'placeholder' => 'Pick colours'])
        ->and($field['options'])->toBe([
            ['value' => 'red', 'label' => 'Red', 'group' => 'Warm'],
            ['value' => 'orange', 'label' => 'Orange', 'group' => 'Warm'],
            ['value' => 'blue', 'label' => 'Blue'],
            ['value' => 'green', 'label' => 'Green'],
        ]);
});

it('creates a record with several choices, kept in a JSON column in the order chosen', function () {
    paletteUser('view', 'create', 'update');

    get('/admin/shop/palettes/create')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sections.0.fields.1.name', 'colours')
        ->where('sections.0.fields.1.multiple', true)
        ->where('sections.0.fields.5.name', 'finish')
        ->missing('sections.0.fields.5.multiple')
        ->where('values.colours', [])
        ->where('values.tags', []));

    post('/admin/shop/palettes', ['name' => 'Sunset', 'colours' => ['orange', 'red'], 'sizes' => ['2'], 'finish' => 'gloss'])->assertSessionHasNoErrors();

    $palette = Palette::query()->where('name', 'Sunset')->firstOrFail();
    expect($palette->colours)->toBe(['orange', 'red'])
        ->and($palette->sizes)->toBe(['2'])
        ->and($palette->finish)->toBe('gloss')
        ->and(json_decode((string) $palette->getRawOriginal('colours'), true))->toBe(['orange', 'red']);
});

it('refuses bad choices on the resource form, saving nothing', function () {
    paletteUser('view', 'create');

    post('/admin/shop/palettes', ['name' => 'A', 'colours' => []])->assertSessionHasErrors('colours');
    post('/admin/shop/palettes', ['name' => 'B'])->assertSessionHasErrors('colours');
    post('/admin/shop/palettes', ['name' => 'C', 'colours' => ['red', 'purple']])->assertSessionHasErrors('colours.1');
    post('/admin/shop/palettes', ['name' => 'D', 'colours' => 'red'])->assertSessionHasErrors('colours');
    post('/admin/shop/palettes', ['name' => 'E', 'colours' => ['red', 'blue', 'green', 'orange']])->assertSessionHasErrors('colours'); // max:3
    post('/admin/shop/palettes', ['name' => 'F', 'colours' => [['red']]])->assertSessionHasErrors('colours.0');
    post('/admin/shop/palettes', ['name' => 'G', 'colours' => ['red'], 'finish' => 'satin'])->assertSessionHasErrors('finish');

    expect(Palette::query()->count())->toBe(0);
});

it('shows the saved choices on the edit form and saves a new list', function () {
    $palette = Palette::query()->create(['name' => 'Sea', 'colours' => ['blue', 'green'], 'sizes' => [1]]);
    paletteUser('view', 'update');

    get("/admin/shop/palettes/{$palette->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('values.colours', ['blue', 'green'])
        ->where('values.sizes', ['1']));

    put("/admin/shop/palettes/{$palette->id}", ['name' => 'Sea', 'colours' => ['green'], 'sizes' => []])->assertSessionHasNoErrors();

    expect($palette->fresh()?->colours)->toBe(['green'])
        ->and($palette->fresh()?->sizes)->toBe([]);
});

it('takes a multiple select\'s choices as JSON, as the panel sends them', function () {
    paletteUser('view', 'create');

    postJson('/admin/shop/palettes', ['name' => 'Json', 'colours' => ['blue', 'red']])->assertSessionHasNoErrors();
    expect(Palette::query()->where('name', 'Json')->firstOrFail()->getRawOriginal('colours'))->toBe('["blue","red"]');
});

it('refuses integer choices sent as JSON numbers, though a single select takes them', function () {
    // Integer-keyed options: the panel sends "1" (strings), but an AI agent
    // or a script may send 1; one select takes 1, several refuse it.
    $sizes = Select::make('sizes')->multiple()->options([1 => 'Small', 2 => 'Large']);
    $one = Select::make('size')->options([1 => 'Small', 2 => 'Large']);

    expect(validator(['size' => 1], multiRules($one))->passes())->toBeTrue()
        ->and(validator(['sizes' => ['1']], multiRules($sizes))->passes())->toBeTrue()
        ->and(validator(['sizes' => [1]], multiRules($sizes))->passes())->toBeTrue();
})->skip('Unconfirmed: Select::multiple() items must be strings (packages/admin/src/Resources/Forms/Select.php:153 \'string\'), so [1] is refused for integer-keyed options a single Select accepts as 1; same as CheckboxList — confirm whether intended');

it('syncs a relation from several choices, refusing records not offered', function () {
    $red = Tag::query()->create(['name' => 'Red']);
    $blue = Tag::query()->create(['name' => 'Blue']);
    $hidden = Tag::query()->create(['name' => 'Retired', 'active' => false]);
    paletteUser('view', 'create', 'update');

    get('/admin/shop/palettes/create')->assertInertia(fn (Assert $page) => $page
        ->where('sections.0.fields.3.options', [['value' => (string) $red->id, 'label' => 'Red'], ['value' => (string) $blue->id, 'label' => 'Blue']])
        ->where('sections.0.fields.3.searchable', true));

    post('/admin/shop/palettes', ['name' => 'Tagged', 'colours' => ['red'], 'tags' => [(string) $blue->id, (string) $hidden->id]])
        ->assertSessionHasErrors('tags.1');
    expect(Palette::query()->count())->toBe(0);

    post('/admin/shop/palettes', ['name' => 'Tagged', 'colours' => ['red'], 'tags' => [(string) $blue->id, (string) $red->id]])->assertSessionHasNoErrors();
    $palette = Palette::query()->where('name', 'Tagged')->firstOrFail();
    expect($palette->tags()->orderBy('tags.id')->pluck('tags.id')->all())->toBe([$red->id, $blue->id]);

    get("/admin/shop/palettes/{$palette->id}/edit")->assertInertia(fn (Assert $page) => $page->where('values.tags', [(string) $red->id, (string) $blue->id]));

    put("/admin/shop/palettes/{$palette->id}", ['name' => 'Tagged', 'colours' => ['red'], 'tags' => []])->assertSessionHasNoErrors();
    expect($palette->tags()->count())->toBe(0);
});

it('hides a related record that\'s no longer offered from the saved choices', function () {
    $red = Tag::query()->create(['name' => 'Red']);
    $retired = Tag::query()->create(['name' => 'Retired', 'active' => false]);
    $palette = Palette::query()->create(['name' => 'Old', 'colours' => ['red']]);
    $palette->tags()->sync([$red->id, $retired->id]);
    paletteUser('view', 'update');

    get("/admin/shop/palettes/{$palette->id}/edit")->assertInertia(fn (Assert $page) => $page->where('values.tags', [(string) $red->id]));
});

it('shows a value saved by a single select as no choice once the select is multiple', function () {
    // Switching a select to ->multiple() on a column that held one value:
    // the old scalar isn't read as a one-item list.
    $record = tap(new class extends Model {}, fn (Model $m) => $m->setAttribute('colours', 'red'));

    expect(colours()->formValue($record))->toBe([])
        ->and(colours()->dehydrate('red'))->toBe([]);
});

it('chooses several options in a settings form and an action\'s dialog', function () {
    $field = Select::make('countries')->multiple()->options(['ID' => 'Indonesia', 'SG' => 'Singapore', 'MY' => 'Malaysia']);
    $settings = app(ShopSettings::class);

    expect(UpdateSettings::values($settings, [$field], ['countries' => ['SG', 'ID']]))->toBe(['countries' => ['SG', 'ID']]);
    expect(fn () => UpdateSettings::values($settings, [$field], ['countries' => ['XX']]))->toThrow(ValidationException::class);

    $got = null;
    $action = Action::make('paint')->form(colours()->required())->handle(function (Model $record, array $input) use (&$got) {
        $got = $input['colours'];
    });
    $target = new class extends Model {};
    $action->run($target, ['colours' => ['green', 'red']]);

    expect($got)->toBe(['green', 'red'])
        ->and($action->toArray()['fields'][0])->toMatchArray(['multiple' => true, 'default' => []])
        ->and(fn () => $action->run($target, ['colours' => []]))->toThrow(ValidationException::class);
});

it('keeps choices from record-dependent options when an AI agent changes another field', function () {
    app(SyncPermissions::class)->handle();
    Role::findOrCreate('agent', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'shop.palettes.view', 'shop.palettes.update']);
    $agent = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'x'])->assignRole('agent');
    [, $token] = AiToken::issue((string) $agent->id, 'Claude');
    $palette = Palette::query()->create(['name' => 'Mine', 'colours' => ['red']]);
    $palette->update(['shades' => ['base', "own-{$palette->id}"]]);

    // A fresh request: the field hasn't been described for this record.
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'save_record', 'arguments' => ['resource' => 'palettes', 'id' => (string) $palette->id, 'values' => ['name' => 'Renamed']]]], ['Authorization' => "Bearer {$token}"]);

    expect($response->json('result.isError'))->not->toBeTrue()
        ->and($palette->fresh()?->name)->toBe('Renamed')
        ->and($palette->fresh()?->shades)->toBe(['base', "own-{$palette->id}"]);
})->todo('BUG: an AI agent\'s save_record reads current values (ResourceTool::currentValues) before the field is described for the record, so Select::multiple()::formValue() filters with the options for no record and the record-dependent choice is dropped and saved away (packages/admin/src/Resources/Forms/Select.php:135-143)');

it('shows a multiple select\'s column and CSV as its saved JSON', function () {
    Palette::query()->create(['name' => 'Dawn', 'colours' => ['red', 'blue']]);
    paletteUser('view', 'export');

    get('/admin/shop/palettes')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.cells.colours', '["red","blue"]'));
    $csv = get('/admin/shop/palettes/export')->assertOk()->streamedContent();

    expect($csv)->toContain('Dawn')->toContain('"[""red"",""blue""]"');
});
