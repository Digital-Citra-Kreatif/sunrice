<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * Several related records, through a BelongsToMany relation of the
 * model, chosen from a searchable list (see RelationSelect) and kept in
 * the order chosen. The relation is synced after the record is saved;
 * with ->orderColumn() each pivot row gets its position:
 *
 *     BelongsToManySelect::make('tags')->title('name')->orderColumn('sort_order');
 *
 * @api
 */
final class BelongsToManySelect extends RelationSelect
{
    protected mixed $default = [];

    private ?string $orderColumn = null;

    private int $max = 500;

    /** A pivot column that keeps the chosen order (0, 1, 2 …). */
    public function orderColumn(?string $column): self
    {
        $this->orderColumn = $column;

        return $this;
    }

    /** At most this many (500 by default). */
    public function max(int $max): self
    {
        $this->max = max(1, $max);

        return $this;
    }

    public function type(): string
    {
        return 'belongs_to_many';
    }

    protected function typeRules(): array
    {
        return ['array', "max:{$this->max}"];
    }

    public function validationRules(?Model $record): array
    {
        return [...parent::validationRules($record), $this->existsRule($record)];
    }

    public function nestedRules(): array
    {
        return ["{$this->name}.*" => ['distinct']];
    }

    /** @return BelongsToMany<Model, Model> */
    protected function relation(Model $model): BelongsToMany
    {
        $relation = $this->resolveRelation($model);
        if (! $relation instanceof BelongsToMany) {
            throw new InvalidArgumentException('Field "'.$this->name.'": '.$model::class.'::'.$this->name.'() isn\'t a BelongsToMany relation.');
        }

        /** @var BelongsToMany<Model, Model> */
        return $relation;
    }

    public function formValue(?Model $record): mixed
    {
        if ($record === null) {
            return $this->default;
        }

        return array_map(fn (array $option): int|string => $option['value'], $this->selected($record));
    }

    public function isSavedAfterRecord(): bool
    {
        return true;
    }

    public function saveAfterRecord(Model $record, mixed $value): void
    {
        $ids = array_values(is_array($value) ? $value : []);
        $relation = $this->relation($record);
        if ($this->orderColumn === null) {
            $relation->sync($ids);
        } else {
            $rows = [];
            foreach ($ids as $position => $id) {
                if (is_int($id) || is_string($id)) {
                    $rows[$id] = [$this->orderColumn => $position];
                }
            }
            $relation->sync($rows);
        }
        $record->unsetRelation($this->name);
    }

    protected function selected(Model $record): array
    {
        if (! $record->exists) {
            return [];
        }
        $relation = $this->relation($record);
        $this->orderColumn === null
            ? $relation->orderBy($relation->getRelated()->qualifyColumn($relation->getRelated()->getKeyName()))
            : $relation->orderByPivot($this->orderColumn);

        return array_values($relation->get()->map(fn (Model $related): array => $this->option($related))->all());
    }
}
