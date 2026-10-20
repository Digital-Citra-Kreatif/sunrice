<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;

/** Notes with a trash: a Deleted filter and a Restore action. */
final class NoteResource extends Resource
{
    protected static string $model = Note::class;

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('title')->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Column::make('title')])
            ->filters([Filter::make('trashed')->options(['only' => 'Deleted'])->query(fn (Builder $query) => $query->onlyTrashed())])
            ->actions([
                Action::make('restore')->trashed()->ability('delete')->handle(function (Model $note): string {
                    $note instanceof Note && $note->restore();

                    return 'Restored.';
                }),
            ]);
    }
}
