<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

/**
 * A warning under a field about the record as it's saved, from
 * Field::warning(): its text, shown as given (wrap your own words in
 * __(), it usually names records too), and links in it.
 *
 *     Warning::make($text)->links([Link::make('Edit', $url)]),
 *
 * @api
 */
final class Warning
{
    /** @var list<Link> */
    private array $links = [];

    private function __construct(private readonly string $text) {}

    public static function make(string $text): self
    {
        return new self($text);
    }

    /** @param  list<Link>  $links */
    public function links(array $links): self
    {
        $this->links = $links;

        return $this;
    }

    /** @return array{text: string, links: list<array{label: string, url: string, newTab: bool, showWhen: array{field: string, value: string|int|bool|null, not: bool}|null}>} */
    public function toArray(): array
    {
        return ['text' => $this->text, 'links' => array_map(fn (Link $link): array => $link->toArray(), $this->links)];
    }
}
