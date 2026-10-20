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

That adds a sidebar item, the routes (`/admin/products`, named `sunrice.admin.resources.products.*`) and four permissions, five when the table is `->exportable()`.

The names come from the model: `label()` ("Products", the page title and the permissions' group), `singularLabel()` ("Product") and `navigationLabel()` (the sidebar item, `label()` unless you override it). Override them as static methods, like `navigationGroup()`, `navigationIcon()` (a lucide name) and `navigationSort()`.

## Who may do what

- If the model has a **policy**, it decides: `viewAny` and `view`, `create`, `update`, `delete` (and `updateAny`/`deleteAny`, if present, to offer bulk actions).
- Otherwise the resource's **permissions** decide: `<prefix>.view`, `.create`, `.update`, `.delete`.
- **Export** needs `view` and `<prefix>.export`, registered automatically for a resource whose table is `->exportable()` (with or without a policy; a policy with an `export($user)` method decides instead). The prefix is the registering plugin's id and the resource's key (`shop.products.view`), or just the key when your app registers it (`products.view`). `sunrice:sync-permissions` creates them.

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
| `BelongsToSelect` | the relation's foreign key | named after a `BelongsTo` relation; searchable (see below) |
| `BelongsToManySelect` | the relation, synced after saving | named after a `BelongsToMany` relation; searchable, in the order chosen; `->orderColumn('sort_order')` (a pivot column for the order), `->max(…)` |
| `RichText` | sanitised HTML | `->maxLength(…)` (500,000), `->images(false)`; see below |

Every field takes `->label()`, `->required()`, `->help()`, `->placeholder()`, `->default()` (the create form's value), `->rules([...])` (more Laravel rules), `->unique()` (except the record being edited), `->onlyOnCreate()` / `->onlyOnEdit()` and `->span(2)` (columns in a section's grid). `Section::make('Title')->columns(2)->schema([...])` groups fields in a card.

`->disabled()` shows a field without letting anyone change it (it's never validated or written); give it a closure to decide per record: `->disabled(fn (?Model $record) => $record?->locked)` (null when creating).

A field that isn't a column reads and writes with closures; `saveUsing` runs after the record is saved, in the same transaction:

```php
CheckboxList::make('tags')
    ->options(fn () => Tag::pluck('name', 'id')->all())
    ->loadUsing(fn (Product $product) => $product->tags->pluck('id')->map(fn ($id) => (string) $id)->all())
    ->saveUsing(fn (Product $product, array $ids) => $product->tags()->sync($ids));
```

### Relation fields

`BelongsToSelect` and `BelongsToManySelect` are named after a relation of the model, and search the related records as the person types:

```php
BelongsToSelect::make('category')->title('name')->required(),
BelongsToSelect::make('author')
    ->title(fn (User $user) => "{$user->name} ({$user->email})")->searchColumns('name', 'email'),
BelongsToManySelect::make('tags')->orderColumn('sort_order')
    ->query(fn (Builder $query) => $query->where('active', true)),
```

- `->title()` names a related record: a column (searched and sorted by; `name` by default), or a closure (then `->searchColumns()` says where to search).
- `->query()` narrows the records that can be found **and** chosen: anything else is refused when the form is saved.
- The form searches `GET /admin/resources/{key}/options/{field}?q=…` (route `sunrice.admin.resources.{key}.options`), which needs the resource's `view` ability and returns at most 50 options. Anyone who may view the list may see the related records' titles, so narrow `->query()` when that matters.
- They work in resource forms (not in action dialogs or settings).

### Rich text

`RichText::make('description')` is the panel's rich text editor: headings, lists, quotes, code, links and images from the media library (`->images(false)` to leave them out). The HTML is **sanitised on the server when it's saved**, with `symfony/html-sanitizer`: only that formatting is kept, links may be `http(s)`, `mailto:`, `tel:` or relative and get `rel="noopener noreferrer"`, and no script, style or event handler is stored. Empty text is stored as null. `RichText::sanitize($html)` cleans HTML the same way elsewhere. The editor's code (TipTap) loads when an editor first shows, never with the panel's main script; plugins use the same editor as `RichTextEditor` from `@sunrice/admin-sdk/ui` (see [Frontend](frontend.md)).

Fields may turn the validated value into what they store with `dehydrate()` (that's where `RichText` sanitises); it runs for resource forms, action dialogs and settings alike.

Override `newRecord()` to prepare the record the create form fills (`$record->team_id = …`).

**Only fields in the form are written.** Anything else a request sends (an `id`, a timestamp, an attribute that isn't on the form) is ignored, so the model doesn't need `$fillable` for the panel.

## Cards beside the form

`aside(Model $record, Authorizable $user)` returns read-only cards shown beside a record's edit form (not on the create form), for whoever may update it: its status, history, related records. Nothing by default. It runs once per edit page, so load what the cards need there, without a query per item. The Users screen shows its Security card this way.

```php
use Sunrice\Admin\Support\Card;

public static function aside(Model $record, Authorizable $user): array
{
    return [
        Card::make('Orders')
            ->description('What this customer bought.')
            ->notice('This customer is blocked.', Card::DANGER, 'Since the chargeback in May.') // or Card::INFO, Card::WARNING
            ->rows(['Orders' => $record->orders()->count(), 'Last order' => $record->last_order_at]) // null leaves a row out
            ->list('Latest', $record->orders()->latest()->limit(5)->get()->map(fn (Order $o) => [
                'label' => '#'.$o->number, 'meta' => $o->status, 'at' => $o->created_at,
            ]), empty: 'No orders yet.')
            ->link('All orders', route('shop.orders.index', ['customer' => $record->id]))
            ->button('Send a reminder', "/admin/customers/{$record->id}/actions/remind", 'Send them a reminder?'),
    ];
}
```

Text is escaped, and times (in rows and list items) are shown in the viewer's own date format. A button POSTs to its URL (after the question, when given): point it at a row action (`…/actions/{key}`) or a route of your own, which checks its own permission.

## Lists

- `Column::make('name')`, or a relationship's attribute `Column::make('category.name')` (eager loaded; for a to-many relationship, `Column::make('tags.name')` lists the values). `->sortable()` (the model's own attributes), `->searchable()`, `->badge()`, `->boolean()`, `->date('d/m/Y')`, `->label()`.
- `Filter::make('status')->options([...])` (or a closure returning them), `Filter::make('active')->boolean()`, or any query: `->query(fn (Builder $q, string $value) => ...)`. Only the listed option values are applied.
- `Filter::dateRange('created_at')->label('Created')`: two dates, either end open, both days included (`whereDate`). Its value is `from,to` (`2026-01-01,2026-01-31`, `2026-01-01,`); anything else is ignored. With `->query()` the closure gets that value, and `Filter::range($value)` reads it as `[$from, $to]`.
- `Column::make('overdue')->state(fn (Invoice $i) => $i->due_on->isPast())->boolean()` shows a value worked out from the record (not sortable or searchable; keep it free of queries).
- `->defaultSort('-created_at')`, `->perPage(50)` (each user may pick another page size and hide columns from the list's **Columns** menu; both are kept per user, see [preferences](preferences.md)), `->exportable()` (CSV of the filtered, sorted list, for people with `<prefix>.export`; values that spreadsheets would run as formulas are escaped). For CSV of your own, `Sunrice\Admin\Support\Csv::write($stream, $cells)` escapes the same way (`Csv::cell()` for one value).

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

`->visible(fn (Product $p) => ! $p->isLive())` offers a row action only for some records (and refuses it for the others). `->ability()` is the resource ability the action needs (`update` by default), checked for each record. `->authorize(fn (User $user, Product $p) => $user->can('shop.products.feature'))` adds a check of your own, per record, after the ability: the action is offered and run (row, bulk and AI) only when it returns true. A handler returns the message to show, or throws `ActionFailed` with one to show as an error.

A row action can open an address instead of running anything: `Action::make('invoice')->url(fn (Order $order) => route('invoices.pdf', $order), newTab: true)` (or a fixed string). It's a link in the row's menu; only `http(s)` and relative addresses are offered, and bulk actions can't have one.

An action can ask for something in its dialog with `->form(Field ...)`: the fields (a `Select`, a `TextInput` …) are shown under the question, validated with their rules, and given to the handler as its second argument. The dialog stays open, showing the errors, until the action is accepted:

```php
Action::make('reject')->ability('update')
    ->form(Select::make('reason')->options(['stock' => 'Out of stock', 'fraud' => 'Suspected fraud'])->required())
    ->handle(fn (Order $order, array $input) => $order->reject($input['reason'])),
```

For a model with a trash (`SoftDeletes`), `->trashed()` makes an action for records in the trash only, such as a Restore: it finds them though the resource's query leaves them out, and isn't offered (or run) for anything else. List them with a filter:

```php
->filters([Filter::make('trashed')->label('Deleted')->options(['only' => 'Deleted'])->query(fn (Builder $q) => $q->onlyTrashed())])
->actions([Action::make('restore')->trashed()->ability('delete')->handle(fn (Note $note) => $note->restore() ? 'Restored.' : null)])
```

A row action with the key `delete` replaces the built-in Delete in the row's menu (the Users screen does this to ask what happens to the user's content). Deleting itself, from the row, the edit page or in bulk, goes through the resource's `deleteRecord($record)`: override it to delete through an action of your own, or throw `ActionFailed` to refuse with a message (the Roles screen refuses while users still have the role).
