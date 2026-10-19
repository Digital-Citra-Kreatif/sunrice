<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Something people own in a plugin (entries they wrote, files they
 * uploaded). The user page says how much each person has; deleting a user
 * hands it to whoever deletes them, so nothing is left without an owner.
 *
 *     $panel->ownedContent(OwnedContent::make('shop.orders')->label('orders', 'order')
 *         ->count(fn (Model $user) => Order::where('owner_id', $user->getKey())->count())
 *         ->handOver(fn (Model $from, Model $to) => Order::where('owner_id', $from->getKey())->update(['owner_id' => $to->getKey()])));
 *
 * @api
 */
final class OwnedContent
{
    private string $label;

    private ?string $singular = null;

    /** @var Closure(Model): int */
    private Closure $count;

    /** @var Closure(Model, Model): void */
    private Closure $handOver;

    private function __construct(public readonly string $key)
    {
        $this->label = $key;
        $this->count = fn (): int => 0;
        $this->handOver = function (): void {};
    }

    public static function make(string $key): self
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Owned content \"{$key}\": use a lowercase key like \"shop.orders\".");
        }

        return new self($key);
    }

    /** What it is, in the plural and for one: "entries", "entry". */
    public function label(string $plural, ?string $singular = null): self
    {
        $this->label = $plural;
        $this->singular = $singular;

        return $this;
    }

    /** @param Closure(Model): int $count how much a user owns */
    public function count(Closure $count): self
    {
        $this->count = $count;

        return $this;
    }

    /** @param Closure(Model, Model): void $handOver gives what `$from` owns to `$to` */
    public function handOver(Closure $handOver): self
    {
        $this->handOver = $handOver;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /** "1 entry", "3 entries". */
    public function describe(int $count): string
    {
        return $count.' '.($count === 1 && $this->singular !== null ? $this->singular : $this->label);
    }

    public function countFor(Model $user): int
    {
        return ($this->count)($user);
    }

    public function handOverFrom(Model $from, Model $to): void
    {
        ($this->handOver)($from, $to);
    }
}
