<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\RichText;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Tests\Admin\Fixtures\CatalogResource;
use Sunrice\Tests\Admin\Fixtures\Category;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ProductPolicy;
use Sunrice\Tests\Admin\Fixtures\ProductResource;
use Sunrice\Tests\Admin\Fixtures\Tag;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    Product::createTables();
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->resources(ProductResource::class, CatalogResource::class))));
});

/** Signed in with the panel and these product permissions ("view", "create" …). */
function shopUser(string ...$abilities): void
{
    actingAs(adminUser(Panel::ACCESS, ...array_map(fn ($a) => "shop.products.{$a}", $abilities)));
}

function product(array $attributes = []): Product
{
    static $n = 0;
    $n++;

    return Product::query()->create([...['name' => "Product {$n}", 'sku' => "SKU-{$n}", 'price' => 10], ...$attributes]);
}

it('registers permissions, a sidebar item and routes', function () {
    $panel = app(Panel::class);

    expect(array_keys($panel->getPermissions()))->toContain('shop.products.view', 'shop.products.create', 'shop.products.update', 'shop.products.delete')
        ->and(route('sunrice.admin.shop.products.index', absolute: false))->toBe('/admin/shop/products');

    shopUser('view');
    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('navigation.0.group', 'Shop')
        ->where('navigation.0.items.0.url', 'shop/products')
        ->where('navigation.0.items.0.label', 'Products'));

    actingAs(adminUser(Panel::ACCESS));
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('navigation', []));
});

it('refuses users without the resource\'s permissions', function () {
    $product = product();
    shopUser();

    get('/admin/shop/products')->assertForbidden();
    get('/admin/shop/products/create')->assertForbidden();
    post('/admin/shop/products', ['name' => 'X'])->assertForbidden();
    get("/admin/shop/products/{$product->id}/edit")->assertForbidden();
    put("/admin/shop/products/{$product->id}", ['name' => 'X'])->assertForbidden();
    delete("/admin/shop/products/{$product->id}")->assertForbidden();

    expect($product->fresh()?->name)->toBe($product->name);
});

it('lists records with search, filters, sorting and pages', function () {
    $tools = Category::query()->create(['name' => 'Tools']);
    product(['name' => 'Hammer', 'sku' => 'H-1', 'price' => 25, 'status' => 'live', 'active' => true, 'category_id' => $tools->id, 'released_on' => '2026-01-02']);
    product(['name' => 'Anvil', 'sku' => 'A-1', 'price' => 5]);
    product(['name' => 'Chisel', 'sku' => 'C-1', 'price' => 8, 'status' => 'archived']);
    shopUser('view', 'update');

    get('/admin/shop/products')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Resources/Index', shouldExist: false)
        ->where('resource.label', 'Products')
        ->where('rows.total', 2) // the archived one is outside the resource's query
        ->where('rows.data.0.title', 'Anvil') // default sort: name
        ->where('rows.data.1.cells', fn ($cells) => $cells['category.name'] === 'Tools')
        ->where('rows.data.1.cells.released_on', '02/01/2026')
        ->where('rows.data.1.cells.active', true)
        ->where('rows.data.1.can.update', true)
        ->where('rows.data.1.can.delete', false)
        ->where('rows.data.1.actions', ['publish'])
        ->where('can.create', false)
        ->has('bulkActions', 1)); // "deactivate" only: no delete permission

    get('/admin/shop/products?search=HAMM')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    get('/admin/shop/products?search=TOOLS')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1)); // a relation's column
    get('/admin/shop/products?search=%25')->assertInertia(fn (Assert $page) => $page->where('rows.total', 0)); // % is literal
    get('/admin/shop/products?filters[status]=live')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    get('/admin/shop/products?filters[active]=0')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.title', 'Anvil')->where('rows.total', 1));
    get('/admin/shop/products?filters[price]=cheap')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    get('/admin/shop/products?filters[status]=bogus&filters[name]=x')->assertInertia(fn (Assert $page) => $page->where('rows.total', 2)->where('query.filters', []));
    get('/admin/shop/products?sort=-price')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.title', 'Hammer'));
    get('/admin/shop/products?sort=sku')->assertInertia(fn (Assert $page) => $page->where('query.sort', 'name')); // not sortable
    get('/admin/shop/products?per_page=10&page=2')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 10)->where('rows.data', []));
    get('/admin/shop/products?per_page=7')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 25));
});

it('creates a record from the declared fields only', function () {
    shopUser('view', 'create', 'update');

    get('/admin/shop/products/create')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Resources/Edit', shouldExist: false)
        ->where('record', null)
        ->where('sections.0.title', null)
        ->where('sections.0.fields.0.name', 'name')
        ->where('sections.1.title', 'Details')
        ->where('sections.1.columns', 2)
        ->has('sections.1.fields', 5) // notes is only on edit
        ->where('values.status', 'draft')
        ->where('values.active', false));

    post('/admin/shop/products', ['name' => 'Saw', 'sku' => 'S-1', 'price' => '12.50', 'status' => 'live', 'active' => '1', 'released_on' => '2026-03-01', 'notes' => 'sneaky', 'id' => 999, 'created_at' => '2000-01-01'])
        ->assertSessionHasNoErrors();

    $saw = Product::query()->where('sku', 'S-1')->firstOrFail();
    expect($saw->id)->not->toBe(999)
        ->and($saw->price)->toBe(12.5)
        ->and($saw->active)->toBeTrue()
        ->and($saw->notes)->toBeNull()
        ->and($saw->created_at?->year)->toBe(now()->year);
});

it('validates input from the fields', function () {
    product(['sku' => 'TAKEN']);
    shopUser('view', 'create');

    post('/admin/shop/products', ['name' => '', 'sku' => 'TAKEN', 'price' => -1, 'status' => 'deleted', 'released_on' => 'soon'])
        ->assertSessionHasErrors(['name', 'sku', 'price', 'status', 'released_on']);
    expect(Product::query()->count())->toBe(1);
});

it('edits a record, ignoring its own value for unique fields', function () {
    $product = product(['sku' => 'KEEP', 'released_on' => '2026-05-06']);
    shopUser('view', 'update');

    get("/admin/shop/products/{$product->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('record.id', $product->id)
        ->where('values.released_on', '2026-05-06')
        ->where('values.notes', null)
        ->where('can.delete', false));

    put("/admin/shop/products/{$product->id}", ['name' => 'Renamed', 'sku' => 'KEEP', 'price' => 3, 'status' => 'draft', 'notes' => 'Hello'])
        ->assertSessionHasNoErrors();

    expect($product->fresh()?->only('name', 'notes', 'active'))->toBe(['name' => 'Renamed', 'notes' => 'Hello', 'active' => false]);
});

it('deletes records, one or many, only when allowed', function () {
    [$a, $b, $c] = [product(), product(), product()];
    shopUser('view', 'delete');

    delete("/admin/shop/products/{$a->id}")->assertRedirect('/admin/shop/products');
    post('/admin/shop/products/bulk', ['action' => 'delete', 'ids' => [$b->id]])->assertSessionHas('success', '1 deleted.');
    expect(Product::query()->pluck('id')->all())->toBe([$c->id]);

    shopUser('view');
    post('/admin/shop/products/bulk', ['action' => 'delete', 'ids' => [$c->id]])->assertForbidden();
    expect(Product::query()->count())->toBe(1);
});

it('cannot reach records outside the resource\'s query', function () {
    $archived = product(['status' => 'archived']);
    shopUser('view', 'update', 'delete');

    get("/admin/shop/products/{$archived->id}/edit")->assertNotFound();
    delete("/admin/shop/products/{$archived->id}")->assertNotFound();
    post('/admin/shop/products/bulk', ['action' => 'delete', 'ids' => [$archived->id]]);
    expect($archived->fresh())->not->toBeNull();
});

it('runs row and bulk actions', function () {
    $product = product(['active' => true]);
    shopUser('view', 'update');

    post("/admin/shop/products/{$product->id}/actions/publish")->assertSessionHas('success', 'Published.');
    expect($product->fresh()?->status)->toBe('live');
    post("/admin/shop/products/{$product->id}/actions/publish")->assertSessionHas('error', 'It is already live.');
    post("/admin/shop/products/{$product->id}/actions/nope")->assertNotFound();

    post('/admin/shop/products/bulk', ['action' => 'deactivate', 'ids' => [$product->id]])->assertSessionHas('success');
    expect($product->fresh()?->active)->toBeFalse();

    shopUser('view');
    post("/admin/shop/products/{$product->id}/actions/publish")->assertForbidden();
});

it('exports the list as CSV, safe for spreadsheets', function () {
    product(['name' => '=HYPERLINK("http://evil")', 'sku' => 'E-1']);
    product(['name' => 'Plain', 'sku' => 'P-1', 'status' => 'live']);
    shopUser('view', 'export');

    $csv = get('/admin/shop/products/export?filters[status]=draft')->assertOk()->streamedContent();

    expect($csv)->toContain('Name,Sku,Price,Status,Active,Category,"Released On"')
        ->toContain("\"'=HYPERLINK(\"\"http://evil\"\")\"")
        ->not->toContain('Plain');
});

it('rejects broken resources', function () {
    expect(fn () => app(Panel::class)->resources(ProductResource::class))->toThrow(PanelMisconfigured::class);
    expect(fn () => Panel::make()->resources(ProductResource::class, ProductResource::class))->toThrow(PanelMisconfigured::class, 'Two resources');
    expect(fn () => Panel::make()->resources(Product::class))->toThrow(PanelMisconfigured::class, "isn't a resource");
});

it('uses the model\'s policy when it has one', function () {
    Gate::policy(Product::class, ProductPolicy::class);
    $open = product(['name' => 'Open']);
    $locked = product(['name' => 'Locked']);
    shopUser(); // no product permissions: the policy decides

    get('/admin/shop/products')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('rows.data.0.title', 'Locked')->where('rows.data.0.can.update', false)
        ->where('rows.data.1.title', 'Open')->where('rows.data.1.can.update', true)
        ->where('can.create', false));
    get("/admin/shop/products/{$open->id}/edit")->assertOk();
    get("/admin/shop/products/{$locked->id}/edit")->assertForbidden();
    delete("/admin/shop/products/{$open->id}")->assertForbidden();
});

/** Signed in with these catalog permissions ("view", "update" …). */
function catalogUser(string ...$abilities): void
{
    actingAs(adminUser(Panel::ACCESS, ...array_map(fn ($a) => "shop.catalog.{$a}", $abilities)));
}

it('searches a relation field\'s options behind the resource\'s view permission', function () {
    foreach (['Tools', 'Toys', 'Garden'] as $name) {
        Category::query()->create(['name' => $name]);
    }
    Tag::query()->create(['name' => 'Retired', 'active' => false]);
    Tag::query()->create(['name' => 'Sale']);

    catalogUser();
    get('/admin/shop/catalog/options/category')->assertForbidden();

    catalogUser('view');
    expect(get('/admin/shop/catalog/options/category?q=to')->assertOk()->json('options'))
        ->toBe([['value' => 1, 'label' => 'Tools'], ['value' => 2, 'label' => 'Toys']])
        ->and(get('/admin/shop/catalog/options/category?q=%25')->json('options'))->toBe([]) // what was typed, not a pattern
        ->and(get('/admin/shop/catalog/options/tags')->json('options'))->toBe([['value' => 2, 'label' => 'Sale']]) // ->query() narrows
        ->and(route('sunrice.admin.shop.catalog.options', ['field' => 'tags'], false))->toBe('/admin/shop/catalog/options/tags');
    get('/admin/shop/catalog/options/name')->assertNotFound(); // not a relation field
    get('/admin/shop/catalog/options/nothing')->assertNotFound();
});

it('caps relation options at 50', function () {
    Category::query()->insert(array_map(fn ($n) => ['name' => sprintf('Category %03d', $n)], range(1, 60)));
    catalogUser('view');

    expect(get('/admin/shop/catalog/options/category')->json('options'))->toHaveCount(50);
});

it('saves belongs-to and ordered belongs-to-many fields, only to records that may be chosen', function () {
    $tools = Category::query()->create(['name' => 'Tools']);
    [$red, $blue, $retired] = [Tag::query()->create(['name' => 'Red']), Tag::query()->create(['name' => 'Blue']), Tag::query()->create(['name' => 'Old', 'active' => false])];
    catalogUser('view', 'create', 'update');

    post('/admin/shop/catalog', ['name' => 'Saw', 'sku' => 'S-1', 'category' => $tools->id, 'tags' => [$blue->id, $red->id]])->assertSessionHasNoErrors();
    $saw = Product::query()->where('sku', 'S-1')->firstOrFail();
    expect($saw->category_id)->toBe($tools->id)
        ->and($saw->tags()->orderByPivot('sort_order')->pluck('name')->all())->toBe(['Blue', 'Red']);

    get("/admin/shop/catalog/{$saw->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('values.category', $tools->id)
        ->where('values.tags', [$blue->id, $red->id])
        ->where('sections.0.fields.1.type', 'belongs_to')
        ->where('sections.0.fields.1.selected', [['value' => $tools->id, 'label' => 'Tools']])
        ->where('sections.0.fields.2.type', 'belongs_to_many')
        ->where('sections.0.fields.2.selected', [['value' => $blue->id, 'label' => 'Blue'], ['value' => $red->id, 'label' => 'Red']]));

    put("/admin/shop/catalog/{$saw->id}", ['name' => 'Saw', 'category' => 999, 'tags' => [$retired->id]])->assertSessionHasErrors(['category', 'tags']);
    put("/admin/shop/catalog/{$saw->id}", ['name' => 'Saw', 'tags' => [$red->id, $red->id]])->assertSessionHasErrors('tags.1');
    put("/admin/shop/catalog/{$saw->id}", ['name' => 'Saw', 'category' => null, 'tags' => [$red->id]])->assertSessionHasNoErrors();
    expect($saw->fresh()?->category_id)->toBeNull()
        ->and($saw->tags()->pluck('name')->all())->toBe(['Red']);
});

it('keeps only the editor\'s formatting in rich text', function () {
    catalogUser('view', 'create', 'update');
    $html = '<h2 onclick="steal()">Hi</h2><p>Read <a href="javascript:alert(1)">this</a> and <a href="/about" target="_blank">that</a></p><script>alert(1)</script><img src="/media/a.jpg" data-media-id="7" onerror="x()">';

    post('/admin/shop/catalog', ['name' => 'Saw', 'sku' => 'S-1', 'description' => $html])->assertSessionHasNoErrors();
    $saved = (string) Product::query()->where('sku', 'S-1')->value('description');

    expect($saved)->toContain('<h2>Hi</h2>')->toContain('href="/about"')->toContain('rel="noopener noreferrer"')->toContain('data-media-id="7"')
        ->not->toContain('script')->not->toContain('onclick')->not->toContain('onerror')->not->toContain('javascript:')
        ->and(RichText::sanitize('<p>ok</p><iframe src="x"></iframe>'))->toBe('<p>ok</p>');

    $id = Product::query()->where('sku', 'S-1')->value('id');
    put("/admin/shop/catalog/{$id}", ['name' => 'Saw', 'description' => '<p></p>'])->assertSessionHasNoErrors();
    expect(Product::query()->whereKey($id)->value('description'))->toBeNull();
});

it('filters by a date range', function () {
    product(['name' => 'Old', 'released_on' => '2025-12-31']);
    product(['name' => 'January', 'released_on' => '2026-01-15']);
    product(['name' => 'Later', 'released_on' => '2026-03-01']);
    catalogUser('view');

    $names = fn (string $range) => array_column(get('/admin/shop/catalog?filters[released_on]='.$range)->viewData('page')['props']['rows']['data'], 'title');

    expect($names('2026-01-01,2026-01-31'))->toBe(['January'])
        ->and($names('2026-01-01,'))->toBe(['January', 'Later'])
        ->and($names(',2026-01-15'))->toBe(['January', 'Old'])
        ->and($names('2026-02-30,'))->toBe(['January', 'Later', 'Old']) // not a date: ignored
        ->and($names('soon'))->toBe(['January', 'Later', 'Old']);
    get('/admin/shop/catalog')->assertInertia(fn (Assert $page) => $page->where('filters.0.type', 'date_range')->where('filters.0.options', []));
});

it('opens url actions and checks authorize() closures', function () {
    $draft = product(['name' => 'Draft']);
    $live = product(['name' => 'Live', 'status' => 'live']);
    catalogUser('view', 'update');

    get('/admin/shop/catalog')->assertInertia(fn (Assert $page) => $page
        ->where('rowActions.0.key', 'view-on-site')->where('rowActions.0.url', true)->where('rowActions.0.newTab', true)
        ->where('rows.data.0.title', 'Draft')
        ->where('rows.data.0.actions', ['view-on-site', 'archive']) // the javascript: address is never offered
        ->where('rows.data.0.urls', ['view-on-site' => "/products/{$draft->id}"])
        ->where('rows.data.1.actions', ['view-on-site'])); // authorize() refuses archiving a live one

    post("/admin/shop/catalog/{$draft->id}/actions/view-on-site")->assertNotFound();
    post("/admin/shop/catalog/{$live->id}/actions/archive")->assertForbidden();
    post("/admin/shop/catalog/{$draft->id}/actions/archive")->assertRedirect();
    expect($draft->fresh()?->status)->toBe('archived')
        ->and(fn () => Action::bulk('open')->url('/x'))->toThrow(InvalidArgumentException::class);
});

it('needs the export permission to export, registered only for exportable tables', function () {
    product();
    $permissions = array_keys(app(Panel::class)->getPermissions());
    expect($permissions)->toContain('shop.products.export')->not->toContain('shop.catalog.export');

    shopUser('view');
    get('/admin/shop/products')->assertInertia(fn (Assert $page) => $page->where('can.export', false));
    get('/admin/shop/products/export')->assertForbidden();

    shopUser('view', 'export');
    get('/admin/shop/products')->assertInertia(fn (Assert $page) => $page->where('can.export', true));
    get('/admin/shop/products/export')->assertOk();

    catalogUser('view', 'export');
    get('/admin/shop/catalog/export')->assertNotFound(); // not exportable
});

it('lets a policy with export() decide exports', function () {
    Gate::policy(Product::class, ProductPolicy::class);
    shopUser(); // no product permissions: the policy's viewAny and export() decide

    get('/admin/shop/products/export')->assertOk();
    ProductPolicy::$exports = false;
    get('/admin/shop/products/export')->assertForbidden();
    ProductPolicy::$exports = true;
});

it('offers a search box on a select with many options when asked', function () {
    $plain = Select::make('kind')->options(['a' => 'A']);
    $searchable = Select::make('kind')->options(['a' => 'A'])->searchable();

    expect($plain->toArray())->not->toHaveKey('searchable')
        ->and($searchable->toArray())->toMatchArray(['type' => 'select', 'searchable' => true, 'options' => [['value' => 'a', 'label' => 'A']]]);
});

it('sends a slug field\'s source and separator to the form', function () {
    $field = TextInput::make('handle')->slugFrom('title')->toArray();

    expect($field)->toMatchArray(['slugFrom' => 'title', 'slugSeparator' => '_', 'inputType' => 'text'])
        ->and(TextInput::make('name')->toArray())->not->toHaveKey('slugFrom');
});

it('gives a select\'s options closure the record being edited', function () {
    $product = Product::query()->create(['name' => 'Lamp', 'sku' => 'L-1', 'price' => 10]);
    $select = Select::make('size')->options(fn (?Model $record) => $record === null ? ['any' => 'Any'] : ['for-'.$record->getKey() => 'Its own']);

    expect(array_column($select->toArray()['options'], 'value'))->toBe(['any'])
        ->and(array_column($select->toArray($product)['options'], 'value'))->toBe(['for-'.$product->getKey()])
        ->and($select->validationRules($product)[1]->__toString())->toBe('in:"for-'.$product->getKey().'"');
});
