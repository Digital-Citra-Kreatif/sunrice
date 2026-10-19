<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * One editable attribute of a resource's model. The field decides how it
 * is shown (its `type`, rendered by the panel's React form), how its input
 * is validated, and how the value is written to the model.
 *
 * Only attributes with a field are ever written: a request can't set
 * anything else on the model.
 *
 * @api
 */
abstract class Field implements Component
{
    protected string $label;

    protected bool $required = false;

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected mixed $default = null;

    /** @var list<string|object> */
    protected array $rules = [];

    /** 'create', 'edit', or null for both. */
    protected ?string $only = null;

    protected int $span = 1;

    /** @var array{table: string|null, column: string|null}|null */
    protected ?array $unique = null;

    final public function __construct(public readonly string $name)
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/i', $name) !== 1) {
            throw new InvalidArgumentException("Field \"{$name}\": use an attribute name (letters, numbers, underscores).");
        }
        $this->label = Str::headline($name);
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    /** The React component that renders it ("text", "select", …). */
    abstract public function type(): string;

    /**
     * Rules for the field's value, besides required/nullable.
     *
     * @return list<string|object>
     */
    abstract protected function typeRules(): array;

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function help(?string $text): static
    {
        $this->help = $text;

        return $this;
    }

    public function placeholder(?string $text): static
    {
        $this->placeholder = $text;

        return $this;
    }

    /** The value on the create form. */
    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    /**
     * More validation rules: ->rules(['min:3', Rule::unique('products', 'sku')]).
     *
     * @param  list<string|object>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = [...$this->rules, ...$rules];

        return $this;
    }

    /**
     * No other record may have the same value (the record being edited
     * excepted). The model's table and this field's column by default.
     */
    public function unique(?string $table = null, ?string $column = null): static
    {
        $this->unique = ['table' => $table, 'column' => $column];

        return $this;
    }

    /** Show the field only when creating. */
    public function onlyOnCreate(): static
    {
        $this->only = 'create';

        return $this;
    }

    /** Show the field only when editing. */
    public function onlyOnEdit(): static
    {
        $this->only = 'edit';

        return $this;
    }

    /** Columns it spans in a section's grid. */
    public function span(int $columns): static
    {
        $this->span = max(1, $columns);

        return $this;
    }

    public function shownOn(string $operation): bool
    {
        return $this->only === null || $this->only === $operation;
    }

    /**
     * The validation rules for this field's input. `$record` is the record
     * being edited, or a new instance when creating.
     *
     * @return list<string|object>
     */
    public function validationRules(Model $record): array
    {
        return [$this->required ? 'required' : 'nullable', ...$this->typeRules(), ...$this->uniqueRule($record), ...$this->rules];
    }

    /** @return list<object> */
    protected function uniqueRule(Model $record): array
    {
        if ($this->unique === null) {
            return [];
        }
        $rule = Rule::unique($this->unique['table'] ?? $record->getTable(), $this->unique['column'] ?? $this->name);

        return [$record->exists ? $rule->ignore($record->getKey(), $record->getKeyName()) : $rule];
    }

    /** The value shown in the form for a record (null when creating). */
    public function formValue(?Model $record): mixed
    {
        return $record === null ? $this->default : $record->getAttribute($this->name);
    }

    /** Write the validated input to the record. */
    public function fill(Model $record, mixed $value): void
    {
        $record->setAttribute($this->name, $value);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => 'field',
            'type' => $this->type(),
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'span' => $this->span,
            ...$this->props(),
        ];
    }

    /**
     * Extra props for the React component.
     *
     * @return array<string, mixed>
     */
    protected function props(): array
    {
        return [];
    }

    /** @return list<Field> */
    public function fields(): array
    {
        return [$this];
    }
}
