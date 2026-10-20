<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Users\DeleteUser;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ProductResource;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Product::createTables();
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->resources(ProductResource::class))));
});

it('keeps a value per user and key', function () {
    $ana = adminUser();
    $budi = adminUser();
    $preferences = app(Preferences::class);

    expect($preferences->get($ana, 'table.orders', ['columns' => null]))->toBe(['columns' => null]);
    $preferences->set($ana, 'table.orders', ['columns' => ['number', 'total'], 'perPage' => 50]);
    $preferences->set($ana, 'table.orders', ['columns' => ['number'], 'perPage' => 50]);
    $preferences->set($budi, 'table.orders', ['perPage' => 10]);

    expect($preferences->get($ana, 'table.orders'))->toBe(['columns' => ['number'], 'perPage' => 50])
        ->and($preferences->table($ana, 'table.orders'))->toBe(['columns' => ['number'], 'perPage' => 50])
        ->and($preferences->table($budi, 'table.orders'))->toBe(['columns' => null, 'perPage' => 10])
        ->and($preferences->table($budi, 'table.users'))->toBe(['columns' => null, 'perPage' => null])
        ->and(DB::table('sunrice_user_preferences')->count())->toBe(2);

    $preferences->set($ana, 'table.orders', null);
    expect($preferences->get($ana, 'table.orders'))->toBeNull();
});

it('refuses keys that aren\'t keys, and values that are too big', function () {
    $preferences = app(Preferences::class);
    $user = adminUser();

    expect(fn () => $preferences->set($user, '../etc', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $preferences->get($user, ''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $preferences->set($user, 'big', str_repeat('x', Preferences::MAX_BYTES)))->toThrow(InvalidArgumentException::class);
});

it('reads and writes the signed-in user\'s own preferences over JSON', function () {
    $ana = adminUser(Panel::ACCESS);
    $budi = adminUser(Panel::ACCESS);

    actingAs($ana);
    getJson('/admin/preferences/table.orders')->assertOk()->assertExactJson(['key' => 'table.orders', 'value' => null]);
    putJson('/admin/preferences/table.orders', ['value' => ['columns' => ['total'], 'perPage' => 25]])->assertOk();
    getJson('/admin/preferences/table.orders')->assertJson(['value' => ['columns' => ['total'], 'perPage' => 25]]);
    putJson('/admin/preferences/table.orders', [])->assertJsonValidationErrors('value');
    putJson('/admin/preferences/table.orders', ['value' => str_repeat('x', Preferences::MAX_BYTES)])->assertJsonValidationErrors('value');
    putJson('/admin/preferences/bad%20key', ['value' => 1])->assertNotFound();

    actingAs($budi);
    getJson('/admin/preferences/table.orders')->assertJson(['value' => null]);
    putJson('/admin/preferences/table.orders', ['value' => null])->assertOk();
    expect(app(Preferences::class)->get($ana, 'table.orders'))->toBe(['columns' => ['total'], 'perPage' => 25]);
});

it('needs sign-in and panel access', function () {
    getJson('/admin/preferences/table.orders')->assertUnauthorized();
    actingAs(adminUser());
    getJson('/admin/preferences/table.orders')->assertForbidden();
    putJson('/admin/preferences/table.orders', ['value' => 1])->assertForbidden();
});

it('forgets a deleted user\'s preferences', function () {
    $user = adminUser();
    app(Preferences::class)->set($user, 'table.orders', ['perPage' => 10]);

    app(DeleteUser::class)->handle($user);

    expect(DB::table('sunrice_user_preferences')->count())->toBe(0);
});

it('gives a resource table the user\'s saved page size, and the URL\'s first', function () {
    foreach (range(1, 12) as $i) {
        Product::query()->create(['name' => "Product {$i}", 'sku' => "SKU-{$i}", 'price' => $i]);
    }
    $user = adminUser(Panel::ACCESS, 'shop.products.view');
    actingAs($user);
    app(Preferences::class)->set($user, 'table.resources.products', ['columns' => ['name'], 'perPage' => 10]);

    get('/admin/shop/products')->assertInertia(fn (Assert $page) => $page
        ->where('rows.perPage', 10)
        ->where('preferences', ['key' => 'table.resources.products', 'columns' => ['name'], 'perPage' => 10]));
    get('/admin/shop/products?per_page=50')->assertInertia(fn (Assert $page) => $page->where('rows.perPage', 50));
});
