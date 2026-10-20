<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Tables;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Sunrice\Admin\Resources\Forms\Field;

/**
 * A button on each row (Action::make) or on the selected rows
 * (Action::bulk), besides the built-in edit and delete:
 *
 *     Action::make('publish')
 *         ->ability('update')                     // the resource ability it needs, per record
 *         ->confirm('Publish this product?')
 *         ->handle(fn (Product $product) => $product->publish());
 *
 *     Action::bulk('archive')->destructive()->ability('delete')
 *         ->handle(fn (Collection $products) => $products->each->archive());
 *
 * An action may ask for something in its dialog (->form()); the handler
 * then gets the validated input as its second argument:
 *
 *     Action::make('reject')->form(Select::make('reason')->options([...])->required())
 *         ->handle(fn (Order $order, array $input) => $order->reject($input['reason']));
 *
 * A row action with the key "delete" replaces the built-in Delete in the
 * row's menu. A handler may return a message to show, and may throw
 * ActionFailed with one to show as an error.
 *
 * @api
 */
final class Action
{
    private string $label;

    private ?string $confirm = null;

    private bool $destructive = false;

    private string $ability = 'update';

    private ?Closure $handler = null;

    /** @var (Closure(Model): bool)|null */
    private ?Closure $visible = null;

    private bool $trashed = false;

    /** @var list<Field> */
    private array $fields = [];

    private function __construct(public readonly string $key, public readonly bool $isBulk)
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1) {
            throw new InvalidArgumentException("Action \"{$key}\": use lowercase letters, numbers and dashes.");
        }
        $this->label = Str::headline($key);
    }

    /** A row action; its handler receives the record. */
    public static function make(string $key): self
    {
        return new self($key, false);
    }

    /** A bulk action; its handler receives a collection of the selected records. */
    public static function bulk(string $key): self
    {
        return new self($key, true);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** Ask before running. */
    public function confirm(string $question): self
    {
        $this->confirm = $question;

        return $this;
    }

    public function destructive(bool $destructive = true): self
    {
        $this->destructive = $destructive;

        return $this;
    }

    /**
     * The resource ability needed (view, create, update or delete),
     * checked for each record. "update" by default.
     */
    public function ability(string $ability): self
    {
        if (! in_array($ability, ['view', 'create', 'update', 'delete'], true)) {
            throw new InvalidArgumentException("Action \"{$this->key}\": the ability is view, create, update or delete.");
        }
        $this->ability = $ability;

        return $this;
    }

    /**
     * Offer a row action only for some records: ->visible(fn (Order $o) => ! $o->isPaid()).
     * Running it on another record is refused too.
     *
     * @param  Closure(Model): bool  $visible
     */
    public function visible(Closure $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function isVisibleFor(Model $record): bool
    {
        if ($this->trashed && ! (in_array(SoftDeletes::class, class_uses_recursive($record), true) && method_exists($record, 'trashed') && $record->trashed() === true)) {
            return false;
        }

        return $this->visible === null || ($this->visible)($record) === true;
    }

    /**
     * For records in the trash (soft-deleted) only, such as a Restore: the
     * action finds them though the resource's query leaves them out, and
     * is offered for nothing else. List them with a filter:
     * Filter::make('trashed')->options(['only' => 'Deleted'])->query(fn ($q) => $q->onlyTrashed()).
     */
    public function trashed(bool $trashed = true): self
    {
        $this->trashed = $trashed;

        return $this;
    }

    public function isForTrashed(): bool
    {
        return $this->trashed;
    }

    /**
     * Fields the dialog asks for before running (a Select, a TextInput …);
     * their input is validated with the fields' rules and given to the
     * handler. The dialog always asks, with or without ->confirm().
     */
    public function form(Field ...$fields): self
    {
        $this->fields = array_values($fields);

        return $this;
    }

    /** @param Closure(Model, array<string, mixed>): mixed|Closure(Collection<int, Model>, array<string, mixed>): mixed $handler */
    public function handle(Closure $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function getAbility(): string
    {
        return $this->ability;
    }

    /** @return list<Field> */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Run it; returns the message to show, if any.
     *
     * @param  Model|Collection<int, Model>  $target
     * @param  array<mixed>  $input  the dialog's values, validated against the form's fields
     *
     * @throws ValidationException
     */
    public function run(Model|Collection $target, array $input = []): ?string
    {
        if ($this->handler === null) {
            throw new InvalidArgumentException("Action \"{$this->key}\" has no handler: add one with ->handle().");
        }
        $result = ($this->handler)($target, $this->validate($input));

        return is_string($result) ? $result : null;
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        if ($this->fields === []) {
            return [];
        }
        $rules = [];
        $attributes = [];
        foreach ($this->fields as $field) {
            $rules[$field->name] = $field->validationRules(null);
            $attributes[$field->name] = $field->getLabel();
            foreach ($field->nestedRules() as $path => $nested) {
                $rules[$path] = $nested;
                $attributes[$path] = $field->getLabel();
            }
        }
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($input, $rules, [], $attributes)->validate();

        return $validated;
    }

    /** @return array{key: string, label: string, confirm: string|null, destructive: bool, fields: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'confirm' => $this->confirm,
            'destructive' => $this->destructive,
            'fields' => array_map(fn (Field $field) => [...$field->toArray(), 'default' => $field->formValue(null)], $this->fields),
        ];
    }
}
