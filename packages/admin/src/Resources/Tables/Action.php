<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Tables;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
 * A handler may return a message to show, and may throw ActionFailed with
 * one to show as an error.
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
        return $this->visible === null || ($this->visible)($record) === true;
    }

    /** @param Closure(Model): mixed|Closure(Collection<int, Model>): mixed $handler */
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
     */
    public function run(Model|Collection $target): ?string
    {
        if ($this->handler === null) {
            throw new InvalidArgumentException("Action \"{$this->key}\" has no handler: add one with ->handle().");
        }
        $result = ($this->handler)($target);

        return is_string($result) ? $result : null;
    }

    /** @return array{key: string, label: string, confirm: string|null, destructive: bool} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'confirm' => $this->confirm, 'destructive' => $this->destructive];
    }
}
