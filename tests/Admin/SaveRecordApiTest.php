<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ProductResource;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

beforeEach(function () {
    Product::createTables();
});

it('saves a record from form values as the form does', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->resources(ProductResource::class))));

    $product = ProductResource::saveRecord(['name' => 'Mug', 'sku' => 'MUG-1', 'price' => 9.5, 'status' => 'live', 'id' => 99]);
    expect($product->exists)->toBeTrue()->and($product->getKey())->not->toBe(99)->and($product->getAttribute('name'))->toBe('Mug')
        ->and(Activity::query()->latest('id')->first()?->action)->toBe('created');

    ProductResource::saveRecord(['name' => 'Big mug', 'sku' => 'MUG-1', 'price' => 9.5, 'status' => 'live'], $product);
    expect($product->fresh()?->getAttribute('name'))->toBe('Big mug')
        ->and(Activity::query()->latest('id')->first()?->properties)->toMatchArray(['changed' => ['name']]);

    expect(fn () => ProductResource::saveRecord(['name' => 'Cup', 'sku' => 'MUG-1', 'price' => 1, 'status' => 'live']))->toThrow(ValidationException::class);
});

it('needs the resource registered', function () {
    usePanel();

    ProductResource::saveRecord(['name' => 'Mug']);
})->throws(PanelMisconfigured::class);
