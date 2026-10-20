# Resources

A resource gives an Eloquent model admin screens, described in PHP: a list (search, filters, sorting, pages, row and bulk actions, CSV export) and create and edit forms.

```php
use Sunrice\Admin\Resources\Forms\{Form, NumberInput, Section, Select, TextInput, Toggle};
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\{Action, Column, Filter, Table};

final class ProductResource extends Resource
{
    protected static string $model = Product::class;

    public static function navigationGroup(): ?string { return 'Shop'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            Section::make('Details')->columns(2)->schema([
                TextInput::make('sku')->label('SKU')->required()->unique(),
                NumberInput::make('price')->min(0)->step(0.01)->required(),
                Select::make('status')->options(['draft' => 'Draft', 'live' => 'Live'])->required(),
                Toggle::make('active'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable(),
                Column::make('category.name')->label('Category'),
                Column::make('status')->badge(),
                Column::make('active')->boolean(),
            ])
            ->filters([Filter::make('status')->options(['draft' => 'Draft', 'live' => 'Live'])])
            ->defaultSort('name')
            ->exportable();
    }
}
```

Register it in a plugin's `register()` or in your panel provider:

```php
$panel->resources(ProductResource::class);
```

That adds a sidebar item, the routes (`/admin/products`, named `sunrice.admin.resources.products.*`) and four permissions.

## Who may do what

- If the model has a **policy**, it decides: `viewAny` and `view`, `create`, `update`, `delete` (and `updateAny`/`deleteAny`, if present, to offer bulk actions).
- Otherwise the resource's **permissions** decide: `<prefix>.view`, `.create`, `.update`, `.delete`. The prefix is the registering plugin's id and the resource's key (`shop.products.view`), or just the key when your app registers it (`products.view`). `sunrice:sync-permissions` creates them.

Every request is checked, per record where there is one. **`can()`** may refuse more, after the policy or permissions allowed it; `$record` is null for the list, the create form and whether to offer bulk actions:

```php
public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
{
    // Products with orders can't be deleted.
    if ($ability === 'delete' && $record !== null) {
        return $record->orders()->doesntExist();
    }

    return true;
}
```
 **`query()`** limits what the resource can reach at all: records outside it are "not found" for every screen, action and export.

```php
public static function query(): Builder
{
    return parent::query()->where('team_id', auth()->user()->team_id);
}
```

## Forms

| Field | Stored as | Options |
| --- | --- | --- |
| `TextInput` | string | `->email()`, `->url()`, `->color()` (`#rrggbb`, with a colour picker), `->maxLength(…)` (255) |
| `Textarea` | string | `->rows(…)`, `->maxLength(…)` |
| `NumberInput` | number | `->integer()`, `->min()`, `->max()`, `->step()` |
| `Toggle` | boolean | |
| `Select` | one of the options | `->options([value => label])`: other values are refused |
| `DatePicker` | date | `->withTime()` |
| `Password` | a hash | never sent to the browser; empty keeps the current one when editing, so `->required()` applies when creating; Laravel's `Password::defaults()` rules |
| `Secret` | a string | never sent to the browser; empty keeps the current value; store it encrypted (`encrypted` cast) |
| `MediaPicker` | a media id, or a list with `->multiple()` | `->images()`, `->types(['image', 'document'])`, `->multiple(max)` (cast the attribute to `array`); see [Media](media.md#picking-media-in-forms) |
| `CheckboxList` | an array of values | `->options([value => label])`, or groups `[group => [value => label]]`, or a closure returning them |

Every field takes `->label()`, `->required()`, `->help()`, `->placeholder()`, `->default()` (the create form's value), `->rules([...])` (more Laravel rules), `->unique()` (except the record being edited), `->onlyOnCreate()` / `->onlyOnEdit()` and `->span(2)` (columns in a section's grid). `Section::make('Title')->columns(2)->schema([...])` groups fields in a card.

`->disabled()` shows a field without letting anyone change it (it's never validated or written); give it a closure to decide per record: `->disabled(fn (?Model $record) => $record?->locked)` (null when creating).

A field that isn't a column reads and writes with closures; `saveUsing` runs after the record is saved, in the same transaction:

```php
CheckboxList::make('tags')
    ->options(fn () => Tag::pluck('name', 'id')->all())
    ->loadUsing(fn (Product $product) => $product->tags->pluck('id')->map(fn ($id) => (string) $id)->all())
    ->saveUsing(fn (Product $product, array $ids) => $product->tags()->sync($ids));
```

Override `newRecord()` to prepare the record the create form fills (`$record->team_id = …`).

**Only fields in the form are written.** Anything else a request sends (an `id`, a timestamp, an attribute that isn't on the form) is ignored, so the model doesn't need `$fillable` for the panel.

## Lists

- `Column::make('name')`, or a relationship's attribute `Column::make('category.name')` (eager loaded; for a to-many relationship, `Column::make('tags.name')` lists the values). `->sortable()` (the model's own attributes), `->searchable()`, `->badge()`, `->boolean()`, `->date('d/m/Y')`, `->label()`.
- `Filter::make('status')->options([...])` (or a closure returning them), `Filter::make('active')->boolean()`, or any query: `->query(fn (Builder $q, string $value) => ...)`. Only the listed option values are applied.
- `Column::make('overdue')->state(fn (Invoice $i) => $i->due_on->isPast())->boolean()` shows a value worked out from the record (not sortable or searchable; keep it free of queries).
- `->defaultSort('-created_at')`, `->perPage(50)` (each user may pick another page size and hide columns from the list's **Columns** menu; both are kept per user, see [preferences](preferences.md)), `->exportable()` (CSV of the filtered, sorted list; values that spreadsheets would run as formulas are escaped). For CSV of your own, `Sunrice\Admin\Support\Csv::write($stream, $cells)` escapes the same way (`Csv::cell()` for one value).

Sorting, filtering and searching only ever use what the table declares; anything else in the URL is ignored.

## Actions

Edit and delete are built in. Add your own, per row or for the selected rows:

```php
->actions([
    Action::make('publish')->confirm('Publish it?')->handle(function (Product $product) {
        if ($product->status === 'live') {
            throw new ActionFailed('It is already live.');
        }
        $product->publish();

        return 'Published.';
    }),
    Action::bulk('archive')->ability('delete')->destructive()
        ->handle(fn (Collection $products) => $products->each->archive()),
])
```

`->visible(fn (Product $p) => ! $p->isLive())` offers a row action only for some records (and refuses it for the others). `->ability()` is the resource ability the action needs (`update` by default), checked for each record. A handler returns the message to show, or throws `ActionFailed` with one to show as an error.

An action can ask for something in its dialog with `->form(Field ...)`: the fields (a `Select`, a `TextInput` …) are shown under the question, validated with their rules, and given to the handler as its second argument. The dialog stays open, showing the errors, until the action is accepted:

```php
Action::make('reject')->ability('update')
    ->form(Select::make('reason')->options(['stock' => 'Out of stock', 'fraud' => 'Suspected fraud'])->required())
    ->handle(fn (Order $order, array $input) => $order->reject($input['reason'])),
```

A row action with the key `delete` replaces the built-in Delete in the row's menu (the Users screen does this to ask what happens to the user's content). Deleting itself, from the row, the edit page or in bulk, goes through the resource's `deleteRecord($record)`: override it to delete through an action of your own, or throw `ActionFailed` to refuse with a message (the Roles screen refuses while users still have the role).
