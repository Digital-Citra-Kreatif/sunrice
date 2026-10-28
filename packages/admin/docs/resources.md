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

Register it in a plugin's `register()` or in your panel provider (see [Customising the panel in your app](panel.md#customising-the-panel-in-your-app) for what else an app can add):

```php
$panel->resources(ProductResource::class);
```

That adds a sidebar item, the routes and four permissions, five when the table is `->exportable()`. A plugin's resources live under the plugin's id (`/admin/shop/products`, the CMS's `/admin/cms/collections`), and their route names follow the address: `sunrice.admin.shop.products.*` (your app's: `sunrice.admin.products.*`). Override `path()` to give one another address than its key (the permissions keep the key).

| Route | Address | |
| --- | --- | --- |
| `.index` | `GET /admin/shop/products` | the list; `?status=trashed` its trash (models with `SoftDeletes`) |
| `.create`, `.store` | `GET …/create`, `POST …` | |
| `.edit`, `.update`, `.destroy` | `GET …/{key}/edit`, `PUT …/{key}`, `DELETE …/{key}` | |
| `.restore` | `POST …/{key}/restore` | back from the trash (models with `SoftDeletes`) |
| `.force-delete` | `DELETE …/{key}/force` | deleted for good, from the trash (models with `SoftDeletes`) |
| `.bulk` | `POST …/bulk` | `action` (`delete`, `restore` or a bulk action's key) and `ids` |
| `.action` | `POST …/{key}/actions/{action}` | a row action |
| `.export` | `GET …/export` | CSV, for an `->exportable()` table |
| `.options` | `GET …/options/{field}?q=` | what a relation field may choose |

**Addresses name records by `recordKey()`**: the primary key unless you return an attribute that's unique and rarely changes (the CMS's collections, taxonomies, forms, menus and globals use their handle: `/admin/cms/collections/blog/edit`). An address with the numeric id still works for the edit page: it redirects to the record's own. **`recordUrl($record)`** sends the list's title link elsewhere than the edit page (a menu's links, say); the row's menu then has **Open** and **Settings**.

**A model with `SoftDeletes` gets a trash**: Delete becomes **Move to trash**, the list has a **Trash** button (`?status=trashed`, for those who may delete) shows it plainly (a red **Trash** badge by the title, a banner saying its records are deleted and show nowhere until restored, rows dimmed with a **Deleted** column saying when), and its rows have **Restore** and **Permanently delete** (asked first), one or selected, for those who may delete (bulk: `action` `restore` or `force-delete`). Both are recorded in the activity log. Override **`restoreRecord($record)`** to restore through an action of your own, and **`forceDeleteRecord($record)`** to remove what belongs to the record too (the CMS's collections take their entries with them); throw `ActionFailed` in either to refuse with a message, as `deleteRecord()` does for deleting.

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
| Any field | | `->showWhen('route')` shows it only while another field is filled in (switched on, chosen, typed in), `->showWhen('kind', 'link')` while it holds that value; it's only hidden on screen, what it holds is still sent and checked; `->showWhenChanged('tags')` shows it on an edit form once that field differs from its saved value ("Also remove …" when a list changes) |
| `TextInput` | string | `->email()`, `->url()`, `->color()` (`#rrggbb`, with a colour picker), `->maxLength(…)` (255), `->slugFrom('title')` (while creating, filled from that field as it's typed, `main_menu`, or with `'-'` `main-menu`, until someone types in it; what's typed in it is kept to lowercase letters, digits and the separator; check it on the server with a rule too) |
| `Textarea` | string | `->rows(…)`, `->maxLength(…)` |
| `Code` | string, as typed | The panel's code editor (line numbers, highlighting, bracket matching, indentation; Tab indents, Esc then Tab moves on): `->language('html')` (`html`, `css`, `javascript`, `json`, `plain`, the default), `->lines(4, 20)` (the editor grows from the first to the second, then scrolls), `->maxLength(…)` (65,535). The value isn't cleaned: escape it where you show it; see below |
| `ListInput` | string, one value per line | Edited as rows of one input each, with **Add** (`->addLabel('Add profile')`) and remove buttons; saved trimmed, without empty lines, so it replaces a "one per line" textarea without changing what's stored (`ListInput::items($value)` reads the values). `->urls()` (http or https; `->urls(httpsOnly: true)`) or `->emails()` (a list typed with commas is split too) check each value, the error naming the one that isn't; `->networks()` shows which site each address is on (Instagram, LinkedIn, Facebook, X, YouTube, TikTok, Wikipedia…, else a globe); `->max(…)` values, `->maxLength(…)` characters in all |
| `Pairs` | string, one `key: value` per line | Edited as rows of two inputs: `->labels('Code', 'Name')`, `->hints('en, id…', 'As visitors read it')` (a short line under each column's heading), `->suggest(['en' => 'English'])` (the key becomes a searchable list of them, by key or value, where any other key can still be typed; picking one fills the value when it's empty or still the old key's suggestion), `->distinctKeys()` (a key in one row only: each row's list leaves out the keys other rows use, compared ignoring case, and typing a used one shows it as already added; keep a server-side rule as well), `->firstBadge('Main')`, `->addLabel(…)`, `->maxLength(…)`; `->fixed(['id' => 'Bahasa Indonesia'])` gives a labelled input per key instead (nothing to add or remove; other keys' lines are kept) |
| `NumberInput` | number | `->integer()`, `->min()`, `->max()`, `->step()` |
| `Toggle` | boolean | |
| `Select` | one of the options | `->options([value => label])`, or a closure that gets the record being edited (null when creating): `->options(fn (?Model $record) => …)`; other values are refused; `->searchable()` adds a search box over them, for long lists. An array as a value is a group of options under a heading: `->options(['Shops' => ['Store' => 'Store', 'Bakery' => 'Bakery'], …])`. `->other($label = 'Other…', $rules, $placeholder)` ends the list with an item that shows a text input for a value not listed, accepted when it passes `$rules` (a saved value that isn't an option shows there) |
| `DatePicker` | date | `->withTime()` |
| `Password` | a hash | never sent to the browser; empty keeps the current one when editing, so `->required()` applies when creating; the panel's [password rules](auth.md#strong-passwords) (`PasswordPolicy`), shown as a checklist under it, refusing the edited account's own email and name; `->confirmed()` asks for it twice (`{name}_confirmation`); AI agents can't set it |
| `Secret` | a string | never sent to the browser; empty keeps the current value; store it encrypted (`encrypted` cast) |
| `MediaPicker` | a media id, or a list with `->multiple()` | `->images()`, `->types(['image', 'document'])`, `->multiple(max)` (cast the attribute to `array`); see [Media](media.md#picking-media-in-forms) |
| `CheckboxList` | an array of values | `->options([value => label])`, or groups `[group => [value => label]]`, or a closure returning them; `->matrix()` shows each group of dotted names ending in an action (permissions: `shop.orders.view`) as a table of things by action (View, Create, Edit, Publish, Moderate, Delete, Export; `-own` variants on an "Own … only" line under their row, in their action's column, other actions as small choices on an "Also" line under it, or the row itself when it has nothing else; `->shortLabels([value => label])` or a closure gives those choices short labels, the full label being their tooltip and description), with select all per group and row, folding groups with a count, and a plain list where the area is too narrow for the table; only the look changes (the Roles screen uses it) |
| `BelongsToSelect` | the relation's foreign key | named after a `BelongsTo` relation; searchable (see below) |
| `BelongsToManySelect` | the relation, synced after saving | named after a `BelongsToMany` relation; searchable, in the order chosen; `->orderColumn('sort_order')` (a pivot column for the order), `->max(…)` |
| `RichText` | sanitised HTML | `->maxLength(…)` (500,000), `->images(false)`; see below |

Every field takes `->label()`, `->required()`, `->help()` (text, or a closure given the record being edited, null when creating: `->help(fn (?Model $post) => …)`), `->placeholder()`, `->default()` (the create form's value), `->rules([...])` (more Laravel rules), `->unique()` (except the record being edited), `->onlyOnCreate()` / `->onlyOnEdit()` and `->span(2)` (columns in a section's grid). `Section::make('Title')->columns(2)->schema([...])` groups fields in a card. Within a card, `->group('Posts')` sets fields apart under a small heading with a divider: fields next to each other with the same group title make one group, hidden with them when all are hidden (`showWhen()`), so labels inside needn't repeat what the heading says. The title is shown as given (it's usually a record's name): wrap words of your own in `__()`.


`->warnWhenChanged('…')` warns under a field on the edit form once its value differs from the saved one (never when creating), for values code relies on, such as a handle templates name or a role's name. `:old` and `:new` in the text become the saved and the typed value; like labels and help, the text is said in the panel's language (give it an Indonesian line). The panel's Roles screen warns this way when a role is renamed: code that checks it by name needs the new one.

```php
TextInput::make('code')->warnWhenChanged('Invoices printed before keep ":old"; reports that filter by it need ":new".'),
```

Under its help, a field can say more, following the form as it's edited:

- `->placeholderFrom('route', '/{handle}/{slug}')` makes the placeholder follow another field as it's edited: that field's value, or while it's empty the fallback (with `{name}` as above), for an input that defaults to another's when left empty.
- `->helpValues(['url' => ['listing_route', '/{handle}']])` fills `:url` in the help with that field's value, or while it's empty with the fallback (`{name}` there is the field `name`'s value).
- `->developerHelp('In a template: `<x-shop::product sku=":value" />`', 'ABC-1')` adds a line "For developers: …": `backticks` show as code, `:value` is the field's value as it's typed (the example while it's empty). Keep the help itself in plain words for editors.
- `->helpDetails('Which file?', "1. shop/product.blade.php\n2. …")` adds a disclosure that opens to more text.
- `->links([...])` puts links under the field, and `Section::make('…')->links([...])` in its card's header. A `Link::make('Edit', url('/admin/blueprints/{blueprint}/edit'))` takes the form's current value of a field for `{name}` in its address; `->showWhen('blueprint')` / `->showUnless('blueprint')` show it only while that field is (or isn't) filled in, and `->newTab()` opens it in a new tab (marked ↗). Either takes a closure that gets the record (null when creating), for links that need a saved one:

When a developer needs a block of code to copy (an `.env` snippet, several commands), give `developerHelp()` a third argument, `$code`: the text and the code fold away in a **For developers** disclosure under the help, the code shown as code with a copy button. The CMS's captcha switches do, with a `links()` link to the guide:

```php
Toggle::make('captcha')->label('Require a captcha')->disabled()
    ->help('Not set up yet: ask your developer to connect a captcha service.')
    ->links([Link::make('How', route('sunrice.admin.guide.show', 'website/settings', false).'#captcha')])
    ->developerHelp("Set these in the app's .env:", code: Captcha::ENV_EXAMPLE),
```

React pages get the same disclosure as `DeveloperHelp` from `@imajiner/admin-sdk/ui`.

```php
Section::make('Listing page')->links(fn (?Model $record) => $record ? [Link::make('View on site', $record->listingUrl())->newTab()] : []),
Select::make('blueprint')->links([
    Link::make('Create blueprint', url('/admin/blueprints/create'))->showUnless('blueprint'),
    Link::make('Edit', url('/admin/blueprints/{blueprint}/edit'))->showWhen('blueprint'),
]),
```

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
- The form searches `GET /admin/{path}/options/{field}?q=…` (route `sunrice.admin.{plugin}.{key}.options`), which needs the resource's `view` ability and returns at most 50 options. Anyone who may view the list may see the related records' titles, so narrow `->query()` when that matters.
- They work in resource forms (not in action dialogs or settings).

### Rich text

`RichText::make('description')` is the panel's rich text editor: headings, lists, quotes, code, links and images from the media library (`->images(false)` to leave them out). The HTML is **sanitised on the server when it's saved**, with `symfony/html-sanitizer`: only that formatting is kept, links may be `http(s)`, `mailto:`, `tel:` or relative and get `rel="noopener noreferrer"`, and no script, style or event handler is stored. Empty text is stored as null. `RichText::sanitize($html)` cleans HTML the same way elsewhere.

A selected image gets a small toolbar of its own: width presets (25, 50, 75 or 100%), alignment (left or right with the text wrapping, centred, full width), its alt text and removing it. The presets are stored as classes on the `<img>`, never inline styles, `class="sunrice-img sunrice-img-w-50 sunrice-img-left"`, and `Sunrice\Admin\Resources\Forms\RichTextImageClasses` keeps exactly those (any other class on an image is dropped; add it to your own `HtmlSanitizerConfig` with `->withAttributeSanitizer(new RichTextImageClasses)`). Style the classes where the HTML is shown; the CMS's [site guide](../../cms/docs/site.md) lists them.

Image styles of your own (rounded corners, a shadow, a frame) are listed in config, `class => label`:

```php
// config/sunrice-admin.php
'rich_text' => [
    'image_classes' => [
        'rounded' => 'Rounded',
        'shadow' => 'Shadow',
    ],
],
```

The image's toolbar then has a **Style** group with a button for each (none without any): editors turn on as many as they like, since styles are meant to combine (rounded *and* with a shadow; a developer who wants them exclusive lists the combination as a style of its own). Their classes are written after the presets', `class="sunrice-img sunrice-img-w-50 rounded shadow"`, and `RichTextImageClasses` keeps them along with the presets' (`RichTextImageClasses::styles()` gives the valid ones, class => label). Keys that aren't CSS class names (`/^[A-Za-z_][A-Za-z0-9_-]*$/`) or start with `sunrice-img` are ignored; a label goes through `__()`. The editor gets them as the field's `imageStyles` prop (`[{class, label}]`; a plugin's `RichTextEditor` takes the same `imageStyles`, from `RichTextImageClasses::options()`). The config is read whenever text is sanitised, so a style taken out of it is dropped from an image the next time its text is saved; text saved before shows as it was until then. The panel doesn't know your styles' CSS, so in the editor the image carries the class and the style's button shows pressed, without the look.

The editor's code (TipTap) loads when an editor first shows, never with the panel's main script; plugins use the same editor as `RichTextEditor` from `@imajiner/admin-sdk/ui` (see [Frontend](frontend.md)).

`Code::make('snippet')->language('html')` edits code in CodeMirror, loaded in its own chunk only when a code field shows (a plain textarea until then): an embed, a JSON setting, a script for every page. It's stored as typed and never cleaned, so print it escaped (`{{ $snippet }}`); printing it as is runs it, which is only for code its editors are trusted to run there (the CMS's Settings → Site code). Plugins use the same editor as `CodeEditor` from `@imajiner/admin-sdk/ui` (see [Frontend](frontend.md)).

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
- `Column::make('status')->sortable()->format(fn (?string $status) => Str::headline($status ?? ''))` shows the value differently and keeps sorting and search on the stored one (the Roles list shows `super-admin` as "Super admin" this way, `RoleResource::displayName()`).
- `->defaultSort('-created_at')`, `->perPage(50)` (each user may pick another page size and hide columns from the list's **Columns** menu; both are kept per user, see [preferences](preferences.md)), `->exportable()` (CSV of the filtered, sorted list, for people with `<prefix>.export`; values that spreadsheets would run as formulas are escaped). For CSV of your own, `Sunrice\Admin\Support\Csv::write($stream, $cells)` escapes the same way (`Csv::cell()` for one value).

Sorting, filtering and searching only ever use what the table declares; anything else in the URL is ignored.

The list's header has the panel's one pattern: **New …** as its button, and a "⋮" menu with **Export CSV** (with the export permission) and the resource's own links from `links(Authorizable $user)`: related pages and settings, `[['label' => 'Submissions', 'url' => 'cms/submissions', 'group' => 'Forms']]` (a `url` under the panel's path or a full address; `external` opens it in a new tab). The search, filters, **Trash** and **Columns** are in the table's toolbar. The CMS's forms list links to their submissions this way.

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

A `Select` in an action's dialog may leave out the records the action runs on with `->withoutTargets()` (its option values are compared with their keys: a Delete dialog's "Give it to" doesn't offer the users being deleted); refuse one sent anyway in the handler with a `ValidationException` under the field's name, so the dialog stays open with the choices made. `->confirmFor(fn (Model $record) => …)` adds a sentence about the record after the question when the dialog is for one record known up front (the edit page's Delete); rows and bulk actions ask only the question.

For a model with a trash (`SoftDeletes`), `->trashed()` makes an action for records in the trash only, such as a Restore: it finds them though the resource's query leaves them out, and isn't offered (or run) for anything else. List them with a filter:

```php
->filters([Filter::make('trashed')->label('Deleted')->options(['only' => 'Deleted'])->query(fn (Builder $q) => $q->onlyTrashed())])
->actions([Action::make('restore')->trashed()->ability('delete')->handle(fn (Note $note) => $note->restore() ? 'Restored.' : null)])
```

A row action with the key `delete` replaces the built-in Delete in the row's menu and on the edit page (after it, the edit page goes back to the list), and a bulk action with the key `delete` replaces the built-in bulk Delete; the Users screen does both to ask what happens to the user's content. Otherwise deleting, from the row, the edit page or in bulk, goes through the resource's `deleteRecord($record)`: override it to delete through an action of your own, or throw `ActionFailed` to refuse with a message (the Roles screen refuses while users still have the role).
