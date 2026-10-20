<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * One related record, through a BelongsTo relation of the model, chosen
 * from a searchable list (see RelationSelect):
 *
 *     BelongsToSelect::make('category')->title('name')->required();
 *
 * It writes the relation's foreign key ("category_id").
 *
 * @api
 */
final class BelongsToSelect extends RelationSelect
{
    public function type(): string
    {
        return 'belongs_to';
    }

    protected function typeRules(): array
    {
        return [];
    }

    public function validationRules(?Model $record): array
    {
        return [...parent::validationRules($record), $this->existsRule($record)];
    }

    /** @return BelongsTo<Model, Model> */
    protected function relation(Model $model): BelongsTo
    {
        $relation = $this->resolveRelation($model);
        if (! $relation instanceof BelongsTo) {
            throw new InvalidArgumentException('Field "'.$this->name.'": '.$model::class.'::'.$this->name.'() isn\'t a BelongsTo relation.');
        }

        /** @var BelongsTo<Model, Model> */
        return $relation;
    }

    public function formValue(?Model $record): mixed
    {
        return $record === null ? $this->default : $record->getAttribute($this->relation($record)->getForeignKeyName());
    }

    public function fill(Model $record, mixed $value): void
    {
        $record->setAttribute($this->relation($record)->getForeignKeyName(), $value === '' ? null : $value);
        $record->unsetRelation($this->name);
    }

    protected function selected(Model $record): array
    {
        $related = $record->getAttribute($this->relation($record)->getForeignKeyName()) === null ? null : $record->getRelationValue($this->name);

        return $related instanceof Model ? [$this->option($related)] : [];
    }
}
