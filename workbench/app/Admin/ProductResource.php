<?php

declare(strict_types=1);

namespace Workbench\App\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Sunrice\Admin\Resources\Forms\DatePicker;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\MediaPicker;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;
use Workbench\App\Models\Product;

/** The workbench's demo resource. */
class ProductResource extends Resource
{
    protected static string $model = Product::class;

    public static function navigationGroup(): ?string
    {
        return 'Shop';
    }

    public static function navigationIcon(): string
    {
        return 'package';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            Section::make('Details')->columns(2)->schema([
                TextInput::make('sku')->label('SKU')->required()->unique()->maxLength(40),
                NumberInput::make('price')->min(0)->step(0.01)->required(),
                Select::make('status')->options(['draft' => 'Draft', 'live' => 'Live'])->default('draft')->required(),
                DatePicker::make('released_on'),
                Toggle::make('active')->help('Shown in the shop.'),
            ]),
            Section::make('Images')->schema([
                MediaPicker::make('image_id')->label('Main image')->images(),
                MediaPicker::make('gallery')->images()->multiple(8),
            ]),
            Section::make('Description')->schema([
                Textarea::make('description')->rows(6),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable(),
                Column::make('sku')->label('SKU')->searchable(),
                Column::make('price')->sortable(),
                Column::make('status')->badge(),
                Column::make('active')->boolean(),
                Column::make('released_on')->date(),
            ])
            ->filters([
                Filter::make('status')->options(['draft' => 'Draft', 'live' => 'Live']),
                Filter::make('active')->boolean(),
            ])
            ->actions([
                Action::make('publish')->confirm('Publish this product?')
                    ->handle(fn (Model $product) => $product->update(['status' => 'live']) ? 'Published.' : null),
                Action::bulk('deactivate')->handle(fn (Collection $products) => $products->each->update(['active' => false])),
            ])
            ->defaultSort('name')
            ->exportable();
    }
}
