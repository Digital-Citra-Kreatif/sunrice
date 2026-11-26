<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Table;

/** Palettes: selects of several options, in a JSON column and through a relation. */
final class PaletteResource extends Resource
{
    protected static string $model = Palette::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            Select::make('colours')->multiple()->required()->rules(['max:3'])
                ->options(['Warm' => ['red' => 'Red', 'orange' => 'Orange'], 'blue' => 'Blue', 'green' => 'Green']),
            Select::make('sizes')->multiple()->options([1 => 'Small', 2 => 'Large']),
            // Active tags only, written to the relation.
            Select::make('tags')->multiple()->searchable()
                ->options(fn () => Tag::query()->where('active', true)->orderBy('id')->pluck('name', 'id')->all())
                ->loadUsing(fn (Palette $palette) => $palette->tags()->orderBy('tags.id')->pluck('tags.id')->all())
                ->saveUsing(function (Model $palette, array $ids): void {
                    if ($palette instanceof Palette) {
                        $palette->tags()->sync($ids);
                    }
                }),
            // A shade of its own for a saved palette: options that depend on the record.
            Select::make('shades')->multiple()
                ->options(fn (?Model $palette) => ['base' => 'Base'] + ($palette === null ? [] : ["own-{$palette->getKey()}" => 'Its own'])),
            Select::make('finish')->options(['matte' => 'Matte', 'gloss' => 'Gloss']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([Column::make('name'), Column::make('colours')])->exportable();
    }
}
