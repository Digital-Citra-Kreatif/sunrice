<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\Category;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ProductPolicy;
use Sunrice\Tests\Admin\Fixtures\ProductResource;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    Product::createTables();
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->resources(ProductResource::class))));
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
        ->and(route('sunrice.admin.resources.products.index', absolute: false))->toBe('/admin/products');

    shopUser('view');
    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('navigation.0.group', 'Shop')
        ->where('navigation.0.items.0.url', 'products'));

    actingAs(adminUser(Panel::ACCESS));
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('navigation', []));
});

it('refuses users without the resource\'s permissions', function () {
    $product = product();
    shopUser();

    get('/admin/products')->assertForbidden();
    get('/admin/products/create')->assertForbidden();
    post('/admin/products', ['name' => 'X'])->assertForbidden();
    get("/admin/products/{$product->id}/edit")->assertForbidden();
    put("/admin/products/{$product->id}", ['name' => 'X'])->assertForbidden();
    delete("/admin/products/{$product->id}")->assertForbidden();

    expect($product->fresh()?->name)->toBe($product->name);
});

it('lists records with search, filters, sorting and pages', function () {
    $tools = Category::query()->create(['name' => 'Tools']);
    product(['name' => 'Hammer', 'sku' => 'H-1', 'price' => 25, 'status' => 'live', 'active' => true, 'category_id' => $tools->id, 'released_on' => '2026-01-02']);
    product(['name' => 'Anvil', 'sku' => 'A-1', 'price' => 5]);
    product(['name' => 'Chisel', 'sku' => 'C-1', 'price' => 8, 'status' => 'archived']);
    shopUser('view', 'update');

    get('/admin/products')->assertOk()->assertInertia(fn (Assert $page) => $page
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

    get('/admin/products?search=hamm')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    get('/admin/products?search=tools')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1)); // a relation's column
    get('/admin/products?search=%25')->assertInertia(fn (Assert $page) => $page->where('rows.total', 0)); // % is literal
    get('/admin/products?filters[status]=live')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    get('/admin/products?filters[active]=0')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.title', 'Anvil')->where('rows.total', 1));
    get('/admin/products?filters[price]=cheap')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    get('/admin/products?filters[status]=bogus&filters[name]=x')->assertInertia(fn (Assert $page) => $page->where('rows.total', 2)->where('query.filters', []));
    get('/admin/products?sort=-price')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.title', 'Hammer'));
    get('/admin/products?sort=sku')->assertInertia(fn (Assert $page) => $page->where('query.sort', 'name')); // not sortable
    get('/admin/products?per_page=10&page=2')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 10)->where('rows.data', []));
    get('/admin/products?per_page=7')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 25));
});

it('creates a record from the declared fields only', function () {
    shopUser('view', 'create', 'update');

    get('/admin/products/create')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Resources/Form', shouldExist: false)
        ->where('record', null)
        ->where('sections.0.title', null)
        ->where('sections.0.fields.0.name', 'name')
        ->where('sections.1.title', 'Details')
        ->where('sections.1.columns', 2)
        ->has('sections.1.fields', 5) // notes is only on edit
        ->where('values.status', 'draft')
        ->where('values.active', false));

    post('/admin/products', ['name' => 'Saw', 'sku' => 'S-1', 'price' => '12.50', 'status' => 'live', 'active' => '1', 'released_on' => '2026-03-01', 'notes' => 'sneaky', 'id' => 999, 'created_at' => '2000-01-01'])
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

    post('/admin/products', ['name' => '', 'sku' => 'TAKEN', 'price' => -1, 'status' => 'deleted', 'released_on' => 'soon'])
        ->assertSessionHasErrors(['name', 'sku', 'price', 'status', 'released_on']);
    expect(Product::query()->count())->toBe(1);
});

it('edits a record, ignoring its own value for unique fields', function () {
    $product = product(['sku' => 'KEEP', 'released_on' => '2026-05-06']);
    shopUser('view', 'update');

    get("/admin/products/{$product->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('record.id', $product->id)
        ->where('values.released_on', '2026-05-06')
        ->where('values.notes', null)
        ->where('can.delete', false));

    put("/admin/products/{$product->id}", ['name' => 'Renamed', 'sku' => 'KEEP', 'price' => 3, 'status' => 'draft', 'notes' => 'Hello'])
        ->assertSessionHasNoErrors();

    expect($product->fresh()?->only('name', 'notes', 'active'))->toBe(['name' => 'Renamed', 'notes' => 'Hello', 'active' => false]);
});

it('deletes records, one or many, only when allowed', function () {
    [$a, $b, $c] = [product(), product(), product()];
    shopUser('view', 'delete');

    delete("/admin/products/{$a->id}")->assertRedirect('/admin/products');
    post('/admin/products/bulk/delete', ['ids' => [$b->id]])->assertSessionHas('success', '1 deleted.');
    expect(Product::query()->pluck('id')->all())->toBe([$c->id]);

    shopUser('view');
    post('/admin/products/bulk/delete', ['ids' => [$c->id]])->assertForbidden();
    expect(Product::query()->count())->toBe(1);
});

it('cannot reach records outside the resource\'s query', function () {
    $archived = product(['status' => 'archived']);
    shopUser('view', 'update', 'delete');

    get("/admin/products/{$archived->id}/edit")->assertNotFound();
    delete("/admin/products/{$archived->id}")->assertNotFound();
    post('/admin/products/bulk/delete', ['ids' => [$archived->id]]);
    expect($archived->fresh())->not->toBeNull();
});

it('runs row and bulk actions', function () {
    $product = product(['active' => true]);
    shopUser('view', 'update');

    post("/admin/products/{$product->id}/actions/publish")->assertSessionHas('success', 'Published.');
    expect($product->fresh()?->status)->toBe('live');
    post("/admin/products/{$product->id}/actions/publish")->assertSessionHas('error', 'It is already live.');
    post("/admin/products/{$product->id}/actions/nope")->assertNotFound();

    post('/admin/products/bulk/deactivate', ['ids' => [$product->id]])->assertSessionHas('success');
    expect($product->fresh()?->active)->toBeFalse();

    shopUser('view');
    post("/admin/products/{$product->id}/actions/publish")->assertForbidden();
});

it('exports the list as CSV, safe for spreadsheets', function () {
    product(['name' => '=HYPERLINK("http://evil")', 'sku' => 'E-1']);
    product(['name' => 'Plain', 'sku' => 'P-1', 'status' => 'live']);
    shopUser('view');

    $csv = get('/admin/products/export?filters[status]=draft')->assertOk()->streamedContent();

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

    get('/admin/products')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('rows.data.0.title', 'Locked')->where('rows.data.0.can.update', false)
        ->where('rows.data.1.title', 'Open')->where('rows.data.1.can.update', true)
        ->where('can.create', false));
    get("/admin/products/{$open->id}/edit")->assertOk();
    get("/admin/products/{$locked->id}/edit")->assertForbidden();
    delete("/admin/products/{$open->id}")->assertForbidden();
});
