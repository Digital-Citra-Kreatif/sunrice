<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\BelongsToManySelect;
use Sunrice\Admin\Resources\Forms\BelongsToSelect;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\RichText;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;

/** Products again, with relation and rich text fields, date filters and link actions. */
final class CatalogResource extends Resource
{
    protected static string $model = Product::class;

    public static function key(): string
    {
        return 'catalog';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('sku')->required()->onlyOnCreate(),
            BelongsToSelect::make('category'),
            BelongsToManySelect::make('tags')->orderColumn('sort_order')
                ->query(fn (Builder $query) => $query->where('active', true)),
            RichText::make('description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Column::make('name')->searchable()->sortable()])
            ->filters([Filter::dateRange('released_on')->label('Released')])
            ->actions([
                Action::make('view-on-site')->url(fn (Model $product) => '/products/'.$product->getKey(), newTab: true),
                Action::make('evil-link')->url('javascript:alert(1)'),
                Action::make('archive')->authorize(fn (Authorizable $user, Model $product) => $product->getAttribute('status') === 'draft')
                    ->handle(fn (Model $product) => $product->update(['status' => 'archived'])),
            ])
            ->defaultSort('name');
    }
}
