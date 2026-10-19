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

Every request is checked, per record where there is one. **`query()`** limits what the resource can reach at all: records outside it are "not found" for every screen, action and export.

```php
public static function query(): Builder
{
    return parent::query()->where('team_id', auth()->user()->team_id);
}
```

## Forms

| Field | Stored as | Options |
| --- | --- | --- |
| `TextInput` | string | `->email()`, `->url()`, `->maxLength(…)` (255) |
| `Textarea` | string | `->rows(…)`, `->maxLength(…)` |
| `NumberInput` | number | `->integer()`, `->min()`, `->max()`, `->step()` |
| `Toggle` | boolean | |
| `Select` | one of the options | `->options([value => label])`: other values are refused |
| `DatePicker` | date | `->withTime()` |

Every field takes `->label()`, `->required()`, `->help()`, `->placeholder()`, `->default()` (the create form's value), `->rules([...])` (more Laravel rules), `->unique()` (except the record being edited), `->onlyOnCreate()` / `->onlyOnEdit()` and `->span(2)` (columns in a section's grid). `Section::make('Title')->columns(2)->schema([...])` groups fields in a card.

**Only fields in the form are written.** Anything else a request sends (an `id`, a timestamp, an attribute that isn't on the form) is ignored, so the model doesn't need `$fillable` for the panel.

## Lists

- `Column::make('name')`, or a relationship's attribute `Column::make('category.name')` (eager loaded). `->sortable()` (the model's own attributes), `->searchable()`, `->badge()`, `->boolean()`, `->date('d/m/Y')`, `->label()`.
- `Filter::make('status')->options([...])`, `Filter::make('active')->boolean()`, or any query: `->query(fn (Builder $q, string $value) => ...)`. Only the listed option values are applied.
- `->defaultSort('-created_at')`, `->perPage(50)`, `->exportable()` (CSV of the filtered, sorted list; values that spreadsheets would run as formulas are escaped).

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

`->ability()` is the resource ability the action needs (`update` by default), checked for each record. A handler returns the message to show, or throws `ActionFailed` with one to show as an error.
