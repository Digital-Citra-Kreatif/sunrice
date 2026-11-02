<?php

declare(strict_types=1);

namespace Sunrice\Admin\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use InvalidArgumentException;
use Sunrice\Admin\Support\Say;

/**
 * A tile on the dashboard. The panel draws it from data alone, so a plugin
 * adds one without JavaScript (plugin bundles load only on their own pages):
 *
 *     Widget::stat('shop.revenue')->title('Revenue today')->link('shop/orders')
 *         ->permission('shop.orders.view')
 *         ->data(fn (Authorizable $user): array => ['value' => Order::today()->sum('total'), 'delta' => '+12%', 'trend' => 'up']);
 *
 *     Widget::list('shop.orders')->title('Latest orders')->span(2)->link('shop/orders', 'All orders')->empty('No orders yet.')
 *         ->data(fn (Authorizable $user): array => ['rows' => Order::latest()->limit(8)->get()->map(fn (Order $order) => [
 *             'title' => $order->number, 'meta' => $order->customer_name, 'url' => "shop/orders/{$order->id}",
 *             'badge' => $order->status, 'tone' => 'success', 'at' => $order->created_at?->toIso8601String(),
 *         ])->all()]);
 *
 * A notice is no tile: a full-width alert above them, for something the
 * user should know about (a setting that may be a mistake). Its `data`
 * runs with the page, as it decides whether there's anything to show, so
 * keep it cheap (a setting, a config value); null shows nothing:
 *
 *     Widget::notice('shop.test-mode')->title('Payments are in test mode')->link('settings/shop', 'Open shop settings')
 *         ->permission('shop.settings')
 *         ->data(fn (Authorizable $user): ?array => app(ShopSettings::class)->testMode
 *             ? ['text' => 'Orders placed now aren\'t charged.', 'tone' => 'warning'] : null);
 *
 * Every widget says who sees it: `permission()`, `visible()`, or
 * `everyone()` for one meant for all who open the panel; one that says
 * nothing is refused when it's registered.
 *
 * The definition (title, link, span …) goes with the page; `data` runs
 * afterwards, in a deferred request (a notice's with the page), only for
 * users who may see the widget. What it returns, by type:
 *
 *  - stat: `value` (number or short text), optional `delta` ("+3 this week"), `trend` (up, down), `description`
 *  - list: `rows` of `title`, optional `meta`, `url`, `badge`, `tone` (neutral, success, warning, danger), `at` (ISO 8601);
 *    optional `count` (shown beside the title)
 *  - cards: `cards` of `title`, optional `text`, `url`, `icon`
 *  - notice: `text`, optional `tone` (warning, the default, or danger), `url` and `label` (instead of the
 *    widget's link); or null, and it doesn't show
 *
 * URLs are relative to the panel's path ("shop/orders"), or start with "/"
 * or "http(s)://"; anything else (`javascript:` …) is dropped.
 *
 * @api
 */
final class Widget
{
    public const STAT = 'stat';

    public const LIST = 'list';

    public const CARDS = 'cards';

    public const NOTICE = 'notice';

    public const TONES = ['neutral', 'success', 'warning', 'danger'];

    /** A notice's tones; warning by default. */
    public const NOTICE_TONES = ['warning', 'danger'];

    /** Rows and cards past this many are left out. */
    public const MAX_ITEMS = 20;

    private string $title;

    private ?string $description = null;

    private ?string $icon = null;

    private ?string $linkUrl = null;

    private ?string $linkLabel = null;

    private ?string $empty = null;

    private int $sort = 100;

    private int $span = 1;

    private ?string $permission = null;

    /** @var (Closure(Authorizable): bool)|null */
    private ?Closure $visible = null;

    private bool $everyone = false;

    /** @var (Closure(Authorizable): (array<string, mixed>|null))|null */
    private ?Closure $data = null;

    private string $group = 'widgets';

    private function __construct(public readonly string $key, public readonly string $type)
    {
        $this->title = $key;
    }

    /** A number, with an optional change and description. */
    public static function stat(string $key): self
    {
        return self::make($key, self::STAT);
    }

    /** A short list of rows, each a link. */
    public static function list(string $key): self
    {
        return self::make($key, self::LIST);
    }

    /** A few cards with a title, a line of text and a link. */
    public static function cards(string $key): self
    {
        return self::make($key, self::CARDS);
    }

    /**
     * A full-width alert above the tiles (warning or danger), with text
     * and an optional link, shown only when its data returns something.
     */
    public static function notice(string $key): self
    {
        return self::make($key, self::NOTICE);
    }

    private static function make(string $key, string $type): self
    {
        if (preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', $key) !== 1) {
            throw new InvalidArgumentException("Widget \"{$key}\": use a lowercase key like \"shop.orders\".");
        }

        return new self($key, $type);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /** A line under the title. */
    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /** A lucide icon name, as navigation items take. */
    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** Where the widget leads: the stat itself, a list's "See all", or a notice's link. */
    public function link(?string $url, ?string $label = null): self
    {
        $this->linkUrl = $url;
        $this->linkLabel = $label;

        return $this;
    }

    /** What a list or cards widget says with nothing to show. */
    public function empty(string $text): self
    {
        $this->empty = $text;

        return $this;
    }

    /** Lower comes first; 100 by default. */
    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /** How many of the grid's three columns it takes on wide screens. */
    public function span(int $span): self
    {
        if ($span < 1 || $span > 3) {
            throw new InvalidArgumentException("Widget \"{$this->key}\": span is 1, 2 or 3.");
        }
        $this->span = $span;

        return $this;
    }

    public function permission(?string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * Show the widget only when this returns true for the user (instead
     * of, or as well as, a permission).
     *
     * @param  Closure(Authorizable): bool  $check
     */
    public function visible(Closure $check): self
    {
        $this->visible = $check;

        return $this;
    }

    /**
     * Show it to everyone who opens the panel, on purpose. A widget must
     * say who sees it: a permission, a visibility check, or this.
     */
    public function everyone(): self
    {
        $this->everyone = true;

        return $this;
    }

    /** Whether it says who may see it (permission(), visible() or everyone()). */
    public function isGuarded(): bool
    {
        return $this->permission !== null || $this->visible !== null || $this->everyone;
    }

    /**
     * What it shows, for this user (see the class comment for the shape).
     * Runs only when the dashboard asks for it, after the page has painted
     * (a notice's with the page). Only a notice may return null: nothing to show.
     *
     * @param  Closure(Authorizable): (array<string, mixed>|null)  $data
     */
    public function data(Closure $data): self
    {
        $this->data = $data;

        return $this;
    }

    /** Whether the user may see it: the permission, if any, and the visibility check, if any. */
    public function isVisibleTo(Authorizable $user): bool
    {
        return ($this->permission === null || $user->can($this->permission))
            && ($this->visible === null || ($this->visible)($user));
    }

    public function hasData(): bool
    {
        return $this->data !== null;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * The deferred group its data loads in: one request per plugin (and
     * one for the panel's and the app's). Set by the panel.
     *
     * @internal
     */
    public function inGroup(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    /** The page prop its data arrives in: "widget_shop_orders" for "shop.orders". */
    public function prop(): string
    {
        // Keys use single dots and dashes, never together, so this can't collide.
        return 'widget_'.strtr($this->key, ['.' => '_', '-' => '__']);
    }

    /**
     * What the page gets up front.
     *
     * @return array{key: string, type: string, title: string, description: string|null, icon: string|null, link: array{url: string, label: string|null}|null, empty: string|null, span: int, prop: string}
     */
    public function definition(): array
    {
        $url = self::url($this->linkUrl);

        return [
            'key' => $this->key,
            'type' => $this->type,
            'title' => Say::it($this->title),
            'description' => Say::it($this->description),
            'icon' => $this->icon,
            'link' => $url === null ? null : ['url' => $url, 'label' => Say::it($this->linkLabel)],
            'empty' => Say::it($this->empty),
            'span' => $this->span,
            'prop' => $this->prop(),
        ];
    }

    /**
     * Run `data` for the user and check its shape. Null only for a notice
     * with nothing to say.
     *
     * @return array<string, mixed>|null
     *
     * @throws InvalidArgumentException when it returns something the panel can't show
     */
    public function resolve(Authorizable $user): ?array
    {
        if ($this->data === null) {
            throw new InvalidArgumentException("Widget \"{$this->key}\" has no data.");
        }
        $data = ($this->data)($user);
        if ($data === null) {
            return $this->type === self::NOTICE ? null
                : throw new InvalidArgumentException("Widget \"{$this->key}\": only a notice's data may be null.");
        }

        return match ($this->type) {
            self::STAT => $this->statData($data),
            self::LIST => $this->listData($data),
            self::NOTICE => $this->noticeData($data),
            default => $this->cardsData($data),
        };
    }

    /**
     * The notice as the page shows it: its title (null when none was set),
     * text, tone and link (the data's `url` and `label`, else the widget's link).
     *
     * @param  array<string, mixed>  $data
     * @return array{key: string, title: string|null, text: string, tone: string, link: array{url: string, label: string|null}|null}
     */
    private function noticeData(array $data): array
    {
        $text = self::text($data, 'text');
        if ($text === null) {
            throw new InvalidArgumentException("Widget \"{$this->key}\": a notice's data needs a \"text\" (or null, to show nothing).");
        }
        $tone = $data['tone'] ?? null;
        $own = array_key_exists('url', $data);
        $url = self::url($own ? self::text($data, 'url') : $this->linkUrl);
        $label = $own ? self::text($data, 'label') : Say::it($this->linkLabel);

        return [
            'key' => $this->key,
            'title' => $this->title === $this->key ? null : Say::it($this->title),
            'text' => $text,
            'tone' => is_string($tone) && in_array($tone, self::NOTICE_TONES, true) ? $tone : 'warning',
            'link' => $url === null ? null : ['url' => $url, 'label' => $label],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{value: int|float|string, delta: string|null, trend: string|null, description: string|null}
     */
    private function statData(array $data): array
    {
        $value = $data['value'] ?? null;
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new InvalidArgumentException("Widget \"{$this->key}\": a stat's data needs a \"value\" (a number or text).");
        }
        $trend = $data['trend'] ?? null;

        return [
            'value' => $value,
            'delta' => self::text($data, 'delta'),
            'trend' => in_array($trend, ['up', 'down'], true) ? $trend : null,
            'description' => self::text($data, 'description'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{rows: list<array{title: string, meta: string|null, url: string|null, badge: string|null, tone: string, at: string|null}>, count: int|null}
     */
    private function listData(array $data): array
    {
        $rows = [];
        foreach (array_slice($this->items($data, 'rows'), 0, self::MAX_ITEMS) as $row) {
            $tone = $row['tone'] ?? 'neutral';
            $rows[] = [
                'title' => $this->itemTitle($row),
                'meta' => self::text($row, 'meta'),
                'url' => self::url(self::text($row, 'url')),
                'badge' => self::text($row, 'badge'),
                'tone' => is_string($tone) && in_array($tone, self::TONES, true) ? $tone : 'neutral',
                'at' => self::text($row, 'at'),
            ];
        }
        $count = $data['count'] ?? null;

        return ['rows' => $rows, 'count' => is_int($count) ? $count : null];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{cards: list<array{title: string, text: string|null, url: string|null, icon: string|null}>}
     */
    private function cardsData(array $data): array
    {
        $cards = [];
        foreach (array_slice($this->items($data, 'cards'), 0, self::MAX_ITEMS) as $card) {
            $cards[] = [
                'title' => $this->itemTitle($card),
                'text' => self::text($card, 'text'),
                'url' => self::url(self::text($card, 'url')),
                'icon' => self::text($card, 'icon'),
            ];
        }

        return ['cards' => $cards];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    private function items(array $data, string $key): array
    {
        $items = $data[$key] ?? null;
        if (! is_array($items)) {
            throw new InvalidArgumentException("Widget \"{$this->key}\": its data needs \"{$key}\" (a list).");
        }

        return array_values(array_filter($items, is_array(...)));
    }

    /** @param array<array-key, mixed> $item */
    private function itemTitle(array $item): string
    {
        $title = $item['title'] ?? null;
        if (! is_string($title) && ! is_int($title)) {
            throw new InvalidArgumentException("Widget \"{$this->key}\": each row and card needs a \"title\".");
        }

        return (string) $title;
    }

    /** @param array<array-key, mixed> $item */
    private static function text(array $item, string $key): ?string
    {
        $value = $item[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : (is_int($value) || is_float($value) ? (string) $value : null);
    }

    /** A URL the page may link to: panel-relative, "/…" or http(s); null otherwise. */
    public static function url(?string $url): ?string
    {
        // "//host", "/\host" and "\…" leave the site in browsers.
        if ($url === null || $url === '' || str_starts_with($url, '\\') || str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return null;
        }
        if (preg_match('#^https?://#i', $url) === 1 || str_starts_with($url, '/')) {
            return $url;
        }

        // No scheme before the first "/", "?" or "#": a path under the panel.
        return preg_match('~^[^/?#]*:~', $url) === 1 ? null : $url;
    }
}
