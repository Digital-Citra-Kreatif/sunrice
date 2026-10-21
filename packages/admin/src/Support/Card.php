<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * A read-only card beside a resource's edit form (`Resource::aside()`) or
 * on a settings section (`Settings::cards()`): a title, notices,
 * label/value rows, lists, links and buttons. The panel renders it; the
 * values are plain text (escaped), times are shown in the viewer's format.
 *
 *     Card::make('Orders')
 *         ->notice('This customer is blocked.', Card::DANGER)
 *         ->rows(['Orders' => 12, 'Last order' => $customer->last_order_at])
 *         ->list('Latest', [['label' => '#1042', 'meta' => 'Paid', 'at' => $order->created_at]])
 *         ->link('All orders', route('shop.orders', ['customer' => $customer->id]));
 *
 * A button POSTs to its URL, which guards itself like any other route.
 *
 * @api
 */
final class Card
{
    public const INFO = 'info';

    public const WARNING = 'warning';

    public const DANGER = 'danger';

    /** @var list<array<string, mixed>> */
    private array $blocks = [];

    /** @var list<array{label: string, url: string}> */
    private array $links = [];

    /** @var list<array{label: string, url: string, confirm: string|null}> */
    private array $buttons = [];

    private ?string $description = null;

    private function __construct(private string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /** A highlighted line: `info`, `warning` or `danger`, with optional smaller text under it. */
    public function notice(string $text, string $tone = self::INFO, ?string $detail = null): self
    {
        $this->blocks[] = ['type' => 'notice', 'text' => Say::it($text), 'tone' => in_array($tone, [self::INFO, self::WARNING, self::DANGER], true) ? $tone : self::INFO, 'detail' => Say::it($detail)];

        return $this;
    }

    /**
     * Label/value rows. A null value leaves the row out; a time is shown
     * in the viewer's date format.
     *
     * @param  array<string, string|int|float|DateTimeInterface|null>  $rows
     */
    public function rows(array $rows): self
    {
        $out = [];
        foreach ($rows as $label => $value) {
            if ($value !== null) {
                $out[] = ['label' => Say::it($label), ...self::value($value)];
            }
        }
        if ($out !== []) {
            $this->blocks[] = ['type' => 'rows', 'rows' => $out];
        }

        return $this;
    }

    /**
     * A list under a heading: each item a label (shown as a badge), an
     * optional line of detail and an optional time.
     *
     * @param  iterable<array{label: string, meta?: string|null, at?: DateTimeInterface|null}>  $items
     */
    public function list(string $heading, iterable $items, string $empty = 'None yet.'): self
    {
        $out = [];
        foreach ($items as $item) {
            $at = $item['at'] ?? null;
            $out[] = ['label' => $item['label'], 'meta' => $item['meta'] ?? null, 'at' => $at === null ? null : Carbon::instance($at)->toIso8601String()];
        }
        $this->blocks[] = ['type' => 'list', 'heading' => Say::it($heading), 'items' => $out, 'empty' => Say::it($empty)];

        return $this;
    }

    public function link(string $label, string $url): self
    {
        $this->links[] = ['label' => Say::it($label), 'url' => $url];

        return $this;
    }

    /** A button that POSTs to `$url` (after a confirmation when given). */
    public function button(string $label, string $url, ?string $confirm = null): self
    {
        $this->buttons[] = ['label' => Say::it($label), 'url' => $url, 'confirm' => Say::it($confirm)];

        return $this;
    }

    /** @return array{title: string, description: string|null, blocks: list<array<string, mixed>>, links: list<array{label: string, url: string}>, buttons: list<array{label: string, url: string, confirm: string|null}>} */
    public function toArray(): array
    {
        return ['title' => Say::it($this->title), 'description' => Say::it($this->description), 'blocks' => $this->blocks, 'links' => $this->links, 'buttons' => $this->buttons];
    }

    /** @return array{value: string, type: 'text'|'datetime'} */
    private static function value(string|int|float|DateTimeInterface $value): array
    {
        return $value instanceof DateTimeInterface
            ? ['value' => Carbon::instance($value)->toIso8601String(), 'type' => 'datetime']
            : ['value' => (string) $value, 'type' => 'text'];
    }
}
