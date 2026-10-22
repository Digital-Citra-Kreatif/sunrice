<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Tables;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Support\Say;

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
 * A row action may open an address instead of running anything (a PDF,
 * the record on the site), and may need more than the resource ability:
 *
 *     Action::make('invoice')->url(fn (Order $order) => route('invoices.show', $order), newTab: true)
 *         ->authorize(fn (User $user, Order $order) => $user->can('shop.orders.invoice'));
 *
 * A row action with the key "delete" replaces the built-in Delete in the
 * row's menu and on the edit page; a bulk action with the key "delete"
 * replaces the built-in bulk Delete. A handler may return a message to show, and may throw
 * ActionFailed with one to show as an error.
 *
 * @api
 */
final class Action
{
    private string $label;

    private ?string $confirm = null;

    /** @var (Closure(Model): ?string)|null */
    private ?Closure $confirmFor = null;

    private bool $destructive = false;

    private string $ability = 'update';

    private ?Closure $handler = null;

    /** @var (Closure(Model): bool)|null */
    private ?Closure $visible = null;

    private bool $trashed = false;

    /** @var list<Field> */
    private array $fields = [];

    /** @var (Closure(Model): string)|string|null */
    private Closure|string|null $url = null;

    private bool $newTab = false;

    /** @var (Closure(Authorizable, Model): bool)|null */
    private ?Closure $authorize = null;

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

    /**
     * Something about the record, added after the question when the dialog
     * is for one record known up front (the edit page's Delete): "They
     * own 3 entries." Rows and bulk actions ask only the question.
     *
     * @param  Closure(Model): ?string  $detail
     */
    public function confirmFor(Closure $detail): self
    {
        $this->confirmFor = $detail;

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

    /**
     * Open this address instead of running a handler (row actions only): a
     * string, or a closure given the record. Only http(s) and relative
     * addresses are opened.
     *
     * @param  (Closure(Model): string)|string  $url
     */
    public function url(Closure|string $url, bool $newTab = false): self
    {
        if ($this->isBulk) {
            throw new InvalidArgumentException("Action \"{$this->key}\": a bulk action can't open an address.");
        }
        $this->url = $url;
        $this->newTab = $newTab;

        return $this;
    }

    public function isUrl(): bool
    {
        return $this->url !== null;
    }

    /** The address it opens for a record (null when it runs a handler, or the address isn't safe). */
    public function urlFor(Model $record): ?string
    {
        if ($this->url === null) {
            return null;
        }
        $url = $this->url instanceof Closure ? ($this->url)($record) : $this->url;
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return $scheme === null || $scheme === false
            ? (str_starts_with(ltrim($url), '//') || str_contains($url, '\\') ? null : $url)
            : (in_array(strtolower($scheme), ['http', 'https'], true) ? $url : null);
    }

    /**
     * A further check for each record, after the resource ability: it can
     * only refuse. Offered, and run, only when it returns true.
     *
     * @param  Closure(Authorizable, Model): bool  $authorize
     */
    public function authorize(Closure $authorize): self
    {
        $this->authorize = $authorize;

        return $this;
    }

    public function allows(Authorizable $user, Model $record): bool
    {
        return $this->authorize === null || ($this->authorize)($user, $record) === true;
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
            $attributes[$field->name] = Say::it($field->getLabel());
            foreach ($field->nestedRules() as $path => $nested) {
                $rules[$path] = $nested;
                $attributes[$path] = Say::it($field->getLabel());
            }
        }
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($input, $rules, [], $attributes)->validate();
        foreach ($this->fields as $field) {
            if (array_key_exists($field->name, $validated)) {
                $validated[$field->name] = $field->dehydrate($validated[$field->name]);
            }
        }

        return $validated;
    }

    /**
     * @param  Model|null  $record  the one record the dialog is for, when known up front (->confirmFor())
     * @return array{key: string, label: string, confirm: string|null, destructive: bool, url: bool, newTab: bool, fields: list<array<string, mixed>>}
     */
    public function toArray(?Model $record = null): array
    {
        $confirm = Say::it($this->confirm);
        $detail = $record !== null && $this->confirmFor !== null ? ($this->confirmFor)($record) : null;
        if (is_string($detail) && $detail !== '') {
            $confirm = is_string($confirm) && $confirm !== '' ? $confirm.' '.$detail : $detail;
        }

        return [
            'key' => $this->key,
            'url' => $this->url !== null,
            'newTab' => $this->newTab,
            'label' => Say::it($this->label),
            'confirm' => is_string($confirm) ? $confirm : null,
            'destructive' => $this->destructive,
            'fields' => array_map(fn (Field $field) => [...$field->toArray(), 'default' => $field->formValue(null)], $this->fields),
        ];
    }
}
