<?php

declare(strict_types=1);

namespace Sunrice\Admin\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use InvalidArgumentException;

/**
 * A card on the dashboard: figures (stats) or a list of links, worked out
 * for the user looking, after the page shows.
 *
 *     DashboardCard::make('shop.orders')
 *         ->title('Recent orders')->permission('shop.orders.view')
 *         ->list(fn (User $user) => Order::latest()->limit(8)->get()
 *             ->map(fn (Order $order) => ['title' => $order->number, 'meta' => $order->total, 'url' => "/admin/shop/orders/{$order->id}"])
 *             ->all())
 *         ->empty('No orders yet.')
 *         ->link('All orders', 'shop/orders');
 *
 * Item addresses are paths ("/admin/shop/orders/12") or web addresses;
 * the link's is relative to the panel's path, like navigation items'.
 *
 * Every card says who sees it: a permission, a visibility check, or
 * everyone() for everyone who can open the panel.
 *
 * @api
 */
final class DashboardCard
{
    private string $title;

    private ?string $description = null;

    private int $sort = 100;

    private int $span = 1;

    private ?string $permission = null;

    /** @var (Closure(Authorizable): bool)|null */
    private ?Closure $visible = null;

    private bool $everyone = false;

    private string $type = 'list';

    /** @var (Closure(Authorizable): array<mixed>)|null */
    private ?Closure $items = null;

    private string $empty = 'Nothing yet.';

    /** @var array{label: string, url: string}|null */
    private ?array $link = null;

    private function __construct(public readonly string $key)
    {
        $this->title = $key;
    }

    public static function make(string $key): self
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Dashboard card \"{$key}\": use a lowercase key like \"shop.orders\".");
        }

        return new self($key);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /** Lower comes first; 100 by default. */
    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /** How many of the dashboard's three columns it takes. */
    public function span(int $columns): self
    {
        $this->span = max(1, min(3, $columns));

        return $this;
    }

    public function permission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /** @param Closure(Authorizable): bool $check */
    public function visible(Closure $check): self
    {
        $this->visible = $check;

        return $this;
    }

    /** Shown to everyone who can open the panel. */
    public function everyone(): self
    {
        $this->everyone = true;

        return $this;
    }

    /**
     * Figures: [['label' => 'Entries', 'value' => 120, 'url' => …], …].
     *
     * @param  Closure(Authorizable): array<mixed>  $stats
     */
    public function stats(Closure $stats): self
    {
        $this->type = 'stats';
        $this->items = $stats;

        return $this;
    }

    /**
     * Links: [['title' => …, 'meta' => 'Edited 2 hours ago', 'url' => …], …].
     *
     * @param  Closure(Authorizable): array<mixed>  $items
     */
    public function list(Closure $items): self
    {
        $this->type = 'list';
        $this->items = $items;

        return $this;
    }

    /** What an empty list says. */
    public function empty(string $message): self
    {
        $this->empty = $message;

        return $this;
    }

    /** A link at the card's foot, to a page of the panel ("shop/orders"). */
    public function link(string $label, string $url): self
    {
        $this->link = ['label' => $label, 'url' => trim($url, '/')];

        return $this;
    }

    public function isGuarded(): bool
    {
        return $this->permission !== null || $this->visible !== null || $this->everyone;
    }

    public function isVisibleTo(Authorizable $user): bool
    {
        return $this->isGuarded()
            && ($this->permission === null || $user->can($this->permission))
            && ($this->visible === null || ($this->visible)($user));
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * What the dashboard shows before the card's content comes.
     *
     * @return array{key: string, title: string, description: string|null, type: string, span: int, link: array{label: string, url: string}|null}
     */
    public function frame(string $panelPath): array
    {
        return [
            'key' => $this->key, 'title' => $this->title, 'description' => $this->description, 'type' => $this->type, 'span' => $this->span,
            'link' => $this->link === null ? null : ['label' => $this->link['label'], 'url' => rtrim('/'.trim($panelPath, '/').'/'.$this->link['url'], '/')],
        ];
    }

    /**
     * The card's content for the user: items with text and safe addresses only.
     *
     * @return array{items: list<array{label: string, value: string, url: string|null}|array{title: string, meta: string|null, url: string|null}>, empty: string}
     */
    public function content(Authorizable $user): array
    {
        $items = [];
        foreach ($this->items === null ? [] : ($this->items)($user) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = is_string($item['url'] ?? null) && self::safe($item['url']) ? $item['url'] : null;
            $items[] = $this->type === 'stats'
                ? ['label' => self::text($item['label'] ?? ''), 'value' => self::text($item['value'] ?? ''), 'url' => $url]
                : ['title' => self::text($item['title'] ?? ''), 'meta' => isset($item['meta']) ? self::text($item['meta']) : null, 'url' => $url];
        }

        return ['items' => $items, 'empty' => $this->empty];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** A path on the site or a web address: never script. */
    private static function safe(string $url): bool
    {
        return preg_match('#^(/(?!/)|https?://)#i', $url) === 1;
    }
}
