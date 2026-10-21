<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Link;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\TextInput;

it('sends what a field says besides its help: live values, a line for developers, more, links', function () {
    $field = TextInput::make('handle')->help('A short name.')
        ->helpValues(['url' => ['route', '/{handle}']])
        ->developerHelp('In a template: `<x-form handle=":value" />`', 'contact')
        ->helpDetails('Which file?', 'cms/listing.blade.php')
        ->links([Link::make('Edit', '/admin/things/{handle}/edit')->showWhen('handle'), Link::make('Create', '/admin/things/create')->showUnless('handle'), Link::make('Site', 'https://example.com')->newTab()])
        ->toArray();

    expect($field['helpValues'])->toBe(['url' => ['field' => 'route', 'fallback' => '/{handle}']])
        ->and($field['developerHelp'])->toBe(['text' => 'In a template: `<x-form handle=":value" />`', 'example' => 'contact', 'code' => null])
        ->and($field['helpDetails'])->toBe(['summary' => 'Which file?', 'text' => 'cms/listing.blade.php'])
        ->and($field['links'])->toBe([
            ['label' => 'Edit', 'url' => '/admin/things/{handle}/edit', 'newTab' => false, 'showWhen' => ['field' => 'handle', 'value' => null, 'not' => false]],
            ['label' => 'Create', 'url' => '/admin/things/create', 'newTab' => false, 'showWhen' => ['field' => 'handle', 'value' => null, 'not' => true]],
            ['label' => 'Site', 'url' => 'https://example.com', 'newTab' => true, 'showWhen' => null],
        ])
        // Nothing of it unless asked for.
        ->and(TextInput::make('title')->toArray())->not->toHaveKeys(['helpValues', 'developerHelp', 'helpDetails', 'links']);
});

it('gives section and field links the record, none when creating', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->exists = true;
    $record->setAttribute('slug', 'hello');
    $form = (new Form)->schema([
        Section::make('Page')->links(fn (?Model $page) => $page ? [Link::make('View on site', '/'.$page->getAttribute('slug'))->newTab()] : [])->schema([
            Select::make('blueprint')->options(['a' => 'A'])->links(fn (?Model $page) => $page ? [Link::make('Edit', '/x')] : []),
        ]),
    ]);

    $editing = $form->toSections('edit', $record)[0];
    expect($editing['links'][0])->toMatchArray(['label' => 'View on site', 'url' => '/hello', 'newTab' => true])
        ->and($editing['fields'][0]['links'][0]['url'])->toBe('/x');
    $creating = $form->toSections('create')[0];
    expect($creating)->not->toHaveKey('links')
        ->and($creating['fields'][0])->not->toHaveKey('links');
});

it('shows a field once another one is changed', function () {
    expect(TextInput::make('note')->showWhenChanged('tags')->toArray()['showWhen'])->toBe(['field' => 'tags', 'value' => null, 'changed' => true]);
});

it('sends a placeholder that follows another field', function () {
    expect(TextInput::make('route_id')->placeholder('Own')->placeholderFrom('route', '/{handle}/{slug}')->toArray())
        ->toMatchArray(['placeholder' => 'Own', 'placeholderFrom' => ['field' => 'route', 'fallback' => '/{handle}/{slug}']])
        ->and(TextInput::make('title')->toArray()['placeholderFrom'])->toBeNull();
});
