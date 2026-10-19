<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Sunrice\Admin\Resources\Forms\DatePicker;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;

final class ProductResource extends Resource
{
    protected static string $model = Product::class;

    public static function navigationGroup(): ?string
    {
        return 'Shop';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            Section::make('Details')->columns(2)->schema([
                TextInput::make('sku')->label('SKU')->required()->unique()->maxLength(40),
                NumberInput::make('price')->min(0)->step(0.01)->required(),
                Select::make('status')->options(['draft' => 'Draft', 'live' => 'Live'])->default('draft')->required(),
                Toggle::make('active'),
                DatePicker::make('released_on'),
                Textarea::make('notes')->onlyOnEdit(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable(),
                Column::make('sku')->searchable(),
                Column::make('price')->sortable(),
                Column::make('status')->badge(),
                Column::make('active')->boolean(),
                Column::make('category.name')->label('Category')->searchable(),
                Column::make('released_on')->date('d/m/Y'),
            ])
            ->filters([
                Filter::make('status')->options(['draft' => 'Draft', 'live' => 'Live']),
                Filter::make('active')->boolean(),
                Filter::make('price')->label('Price')->options(['cheap' => 'Under 10'])
                    ->query(fn (Builder $query) => $query->where('price', '<', 10)),
            ])
            ->actions([
                Action::make('publish')->confirm('Publish it?')->handle(function (Model $product) {
                    if ($product->getAttribute('status') === 'live') {
                        throw new ActionFailed('It is already live.');
                    }
                    $product->update(['status' => 'live']);

                    return 'Published.';
                }),
                Action::bulk('deactivate')->handle(fn (Collection $products) => $products->each->update(['active' => false])),
            ])
            ->defaultSort('name')
            ->exportable();
    }

    /** Archived products are out of reach. */
    public static function query(): Builder
    {
        return parent::query()->where('status', '!=', 'archived');
    }
}
