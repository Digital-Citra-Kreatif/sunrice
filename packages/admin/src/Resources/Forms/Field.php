<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Sunrice\Admin\Support\Say;

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
    use Concerns\HelpExtras;

    protected string $label;

    protected bool $required = false;

    /** @var string|(Closure(?Model): ?string)|null */
    protected string|Closure|null $help = null;

    protected ?string $placeholder = null;

    /** @var array{field: string|null, fallback: string|null}|null */
    protected ?array $placeholderFrom = null;

    protected ?string $warnWhenChanged = null;

    /** @var (Closure(Model): (Warning|string|null))|null */
    protected ?Closure $warning = null;

    protected mixed $default = null;

    /** @var list<string|object> */
    protected array $rules = [];

    /** 'create', 'edit', or null for both. */
    protected ?string $only = null;

    protected int $span = 1;

    /** @var array{table: string|null, column: string|null}|null */
    protected ?array $unique = null;

    /** @var bool|Closure(?Model): bool */
    protected bool|Closure $disabled = false;

    /** @var (Closure(Model): mixed)|null */
    protected ?Closure $loadUsing = null;

    /** @var (Closure(Model, mixed): void)|null */
    protected ?Closure $saveUsing = null;

    /** @var array{field: string, value: string|int|bool|null, changed?: true}|null */
    private ?array $showWhen = null;

    private ?string $group = null;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * The line under the field. A closure gets the record being edited
     * (null when creating), for help that names it.
     *
     * @param  string|(Closure(?Model): ?string)|null  $text
     */
    public function help(string|Closure|null $text): static
    {
        $this->help = $text;

        return $this;
    }

    /**
     * Puts the field in a titled group within its section: fields next to
     * each other with the same group show under its heading, set apart
     * from the rest by a divider. For sets of fields about one thing, like
     * each collection's settings on a taxonomy, so their labels needn't
     * repeat its name. A group whose fields are all hidden (showWhen()) is
     * hidden too. The title is shown as given, as it's usually a record's
     * name: wrap words of your own in __().
     */
    public function group(?string $title): static
    {
        $this->group = $title;

        return $this;
    }

    /**
     * A warning shown under the field on an edit form once its value
     * differs from the saved one (not when creating), for values code
     * relies on (a handle templates name, a role's name). `:old` and `:new`
     * in the text become the saved and the typed value.
     */
    public function warnWhenChanged(?string $text): static
    {
        $this->warnWhenChanged = $text;

        return $this;
    }

    /**
     * A warning under the field on the edit form about the record as it's
     * saved (a value that's allowed but won't do what it seems to): the
     * closure gets the record and gives a Warning (text and links), its
     * text alone, or null for none. Worked out once when the form opens
     * (keep it cheap); shown while the field holds its saved value, as it
     * may not hold for another. Never when creating: the record is saved
     * first, then edited, so it shows right after saving.
     *
     * @param  (Closure(Model): (Warning|string|null))|null  $warning
     */
    public function warning(?Closure $warning): static
    {
        $this->warning = $warning;

        return $this;
    }

    public function placeholder(?string $text): static
    {
        $this->placeholder = $text;

        return $this;
    }

    /**
     * The placeholder follows another field of the form as it's edited:
     * ->placeholderFrom('route', '/{handle}/{slug}') shows the route field's
     * value, or while that's empty the fallback with `{name}` as the field
     * `name`'s value (else the placeholder()). For a value that defaults to
     * another field's when left empty. Without a field (null), always the
     * fallback with the form's values: ->placeholderFrom(null, '/{handle}')
     * shows "/blog" as the handle "blog" is typed (`{name}` stays while
     * that field is empty).
     */
    public function placeholderFrom(?string $field, ?string $fallback = null): static
    {
        $this->placeholderFrom = ['field' => $field, 'fallback' => $fallback];

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
     * Shown but not editable: never validated or written. A closure gets
     * the record being edited, or null when creating:
     * ->disabled(fn (?User $user) => $user?->is(auth()->user())).
     *
     * @param  bool|Closure(?Model): bool  $disabled
     */
    /**
     * Shown only while another field of the form is filled in (switched
     * on, chosen, typed in), or holds `$value`: ->showWhen('route'),
     * ->showWhen('kind', 'link'). It's only hidden on screen: what it
     * holds is still sent, checked and saved.
     */
    public function showWhen(string $field, string|int|bool|null $value = null): static
    {
        $this->showWhen = ['field' => $field, 'value' => $value];

        return $this;
    }

    public function disabled(bool|Closure $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function isDisabled(?Model $record): bool
    {
        return $this->disabled instanceof Closure ? (bool) ($this->disabled)($record) : $this->disabled;
    }

    /** Whether the form requires a value (when creating, or editing `$record`). */
    public function isRequired(?Model $record): bool
    {
        return $this->required;
    }

    /**
     * Read the form's value from the record some other way than its
     * attribute: ->loadUsing(fn (User $user) => $user->roles->pluck('name')->all()).
     *
     * @param  Closure(Model): mixed  $load
     */
    public function loadUsing(Closure $load): static
    {
        $this->loadUsing = $load;

        return $this;
    }

    /**
     * Write the validated value some other way than to its attribute, after
     * the record is saved (in the same transaction):
     * ->saveUsing(fn (User $user, array $roles) => $user->syncRoles($roles)).
     *
     * @param  Closure(Model, mixed): void  $save
     */
    public function saveUsing(Closure $save): static
    {
        $this->saveUsing = $save;

        return $this;
    }

    /**
     * The validation rules for this field's input. `$record` is the record
     * being edited, a new instance when creating, or null outside a
     * resource (settings).
     *
     * @return list<string|object>
     */
    public function validationRules(?Model $record): array
    {
        $existing = $record !== null && $record->exists ? $record : null;

        return [$this->isRequired($existing) ? 'required' : 'nullable', ...$this->typeRules(), ...$this->uniqueRule($record), ...$this->rules];
    }

    /**
     * Whether an empty input keeps the current value (passwords and
     * secrets) instead of clearing it.
     */
    public function keepsValueWhenEmpty(): bool
    {
        return false;
    }

    /**
     * Rules for the parts of an array value, keyed by their path under the
     * field ("roles.*").
     *
     * @return array<string, list<string|object>>
     */
    public function nestedRules(): array
    {
        return [];
    }

    /** @return list<object> */
    protected function uniqueRule(?Model $record): array
    {
        if ($this->unique === null || $record === null) {
            return [];
        }
        $rule = Rule::unique($this->unique['table'] ?? $record->getTable(), $this->unique['column'] ?? $this->name);

        return [$record->exists ? $rule->ignore($record->getKey(), $record->getKeyName()) : $rule];
    }

    /** The value shown in the form for a record (null when creating). */
    public function formValue(?Model $record): mixed
    {
        return match (true) {
            $record === null => $this->default,
            $this->loadUsing !== null => ($this->loadUsing)($record),
            default => $record->getAttribute($this->name),
        };
    }

    /**
     * The validated value as it is kept: what fill(), saveUsing(), an
     * action's handler and settings get (RichText sanitises its HTML here).
     */
    public function dehydrate(mixed $value): mixed
    {
        return $value;
    }

    /** Write the validated input to the record, before it is saved. */
    public function fill(Model $record, mixed $value): void
    {
        $record->setAttribute($this->name, $value);
    }

    /** Whether saveUsing() writes it, after the record is saved. */
    public function isSavedAfterRecord(): bool
    {
        return $this->saveUsing !== null;
    }

    public function saveAfterRecord(Model $record, mixed $value): void
    {
        if ($this->saveUsing !== null) {
            ($this->saveUsing)($record, $value);
        }
    }

    /**
     * The field for the form: creating, or editing `$record`.
     *
     * @return array<string, mixed>
     */
    public function toArray(?Model $record = null): array
    {
        return [
            'kind' => 'field',
            'type' => $this->type(),
            'name' => $this->name,
            'label' => Say::it($this->label),
            'required' => $this->isRequired($record),
            'disabled' => $this->isDisabled($record),
            'help' => Say::it($this->help instanceof Closure ? ($this->help)($record) : $this->help),
            'placeholder' => Say::it($this->placeholder),
            'placeholderFrom' => $this->placeholderFrom,
            // Only on an edit form: a new record has nothing saved to change.
            'warnWhenChanged' => $record !== null && $record->exists ? Say::it($this->warnWhenChanged) : null,
            'warning' => $this->warningFor($record),
            'span' => $this->span,
            'showWhen' => $this->showWhen,
            ...$this->helpExtras($record),
            ...$this->props(),
        ] + ($this->group !== null ? ['group' => $this->group] : []);
    }

    /**
     * Field::warning() for the record being edited, as the form shows it.
     *
     * @return array{text: string, links: list<array<string, mixed>>}|null
     */
    private function warningFor(?Model $record): ?array
    {
        $warning = $this->warning === null || $record === null || ! $record->exists ? null : ($this->warning)($record);

        return match (true) {
            $warning instanceof Warning => $warning->toArray(),
            is_string($warning) && $warning !== '' => Warning::make($warning)->toArray(),
            default => null,
        };
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
