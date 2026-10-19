<?php

declare(strict_types=1);

use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\FieldTypes;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Fields\UnknownFieldType;
use Sunrice\Tests\Cms\Fixtures\ColorField;
use Sunrice\Tests\Cms\Fixtures\RatingField;

it('has the built-in field types', function () {
    expect(array_keys(app(FieldTypes::class)->all()))->toBe([
        'text', 'textarea', 'rich_text', 'number', 'toggle', 'select', 'date', 'link', 'media', 'group', 'repeater', 'flexible', 'fieldset', 'terms', 'entries', 'file',
    ]);
});

it('registers the app\'s field types through the plugin', function () {
    $panel = Panel::make()->plugin(CmsPlugin::make()->fieldTypes(ColorField::class, RatingField::class));
    $panel->boot();

    expect(app(FieldTypes::class)->get('color'))->toBeInstanceOf(ColorField::class)
        ->and(app(FieldTypes::class)->has('rating'))->toBeTrue();
});

it('registers them on an already resolved registry too', function () {
    app(FieldTypes::class);
    Panel::make()->plugin(CmsPlugin::make()->fieldTypes(ColorField::class))->boot();

    expect(app(FieldTypes::class)->has('color'))->toBeTrue();
});

it('refuses a type registered twice', function () {
    app(FieldTypes::class)->register(ColorField::class, ColorField::class);
})->throws(InvalidArgumentException::class, 'Field type "color" is registered twice');

it('says how to register an unknown type', function () {
    app(FieldTypes::class)->get('map');
})->throws(UnknownFieldType::class, 'CmsPlugin::make()->fieldTypes()');

it('builds a custom field on a built-in one', function () {
    app(FieldTypes::class)->register(RatingField::class);
    $schema = Schema::make([Field::make('stars', 'rating')]);

    expect($schema->rules([]))->toBe(['data.stars' => ['nullable', 'numeric', 'min:1', 'max:5', 'integer']])
        ->and($schema->normalize(['stars' => '4']))->toBe(['stars' => 4])
        ->and($schema->toAdmin()[0])->toMatchArray(['type' => 'number', 'custom_type' => 'rating', 'config' => ['min' => 1, 'max' => 5]]);
});

it('keeps values of a type that is no longer registered', function () {
    $schema = Schema::make([Field::make('shade', 'color')]);

    expect($schema->normalize(['shade' => '#FFF']))->toBe(['shade' => '#FFF'])
        ->and($schema->rules([]))->toBe(['data.shade' => ['nullable']]);
});
