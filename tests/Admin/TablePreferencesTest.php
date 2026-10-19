<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\Admin\Users\UserPreferences;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ProductResource;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    Product::createTables();
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->resources(ProductResource::class))));
});

it('keeps each person\'s page size and hidden columns for a list', function () {
    actingAs($rina = adminUser(Panel::ACCESS, 'shop.products.view'));
    get('/admin/products')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 25)->where('hidden', []));

    get('/admin/products?per_page=50')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 50));
    put('/admin/products/columns', ['hidden' => ['sku']])->assertRedirect();
    get('/admin/products')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 50)->where('hidden', ['sku']));

    // Someone else's list is their own.
    actingAs(adminUser(Panel::ACCESS, 'shop.products.view'));
    get('/admin/products')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 25)->where('hidden', []));
    expect(app(UserPreferences::class)->get($rina, 'table.products'))->toBe(['hidden' => ['sku'], 'perPage' => 50]);
});

it('hides only columns the list has, and never all of them', function () {
    actingAs(adminUser(Panel::ACCESS, 'shop.products.view'));
    $columns = collect(ProductResource::table(new Table)->getColumns())->pluck('name')->all();

    put('/admin/products/columns', ['hidden' => ['password']])->assertSessionHasErrors('hidden.0');
    put('/admin/products/columns', ['hidden' => $columns])->assertSessionHasErrors('hidden');
    actingAs(adminUser(Panel::ACCESS));
    put('/admin/products/columns', ['hidden' => []])->assertForbidden();
});
