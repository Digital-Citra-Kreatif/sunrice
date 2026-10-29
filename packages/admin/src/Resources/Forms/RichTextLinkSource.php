<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Something on the site rich text links can point at, registered with
 * Panel::richTextLinkSource(): the editor's link menu searches it, and
 * a chosen item's link carries `data-link="<key>:<id>"`.
 *
 * @internal Panel::richTextLinkSource() makes it; the link menu's routes ask it.
 */
final class RichTextLinkSource
{
    /** Items one source lists for a search. */
    public const LIMIT = 8;

    /**
     * @param  Closure(string, Authorizable): iterable<mixed>  $search
     * @param  (Closure(list<int>, Authorizable): iterable<mixed>)|null  $find
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        private readonly Closure $search,
        private readonly ?Closure $find = null,
    ) {}

    /** The label in the panel's language. */
    public function label(): string
    {
        $label = __($this->label);

        return is_string($label) ? $label : $this->label;
    }

    /**
     * What a search finds, at most LIMIT items.
     *
     * @return list<array{link: string, id: int, title: string, hint: string|null, url: string}>
     */
    public function search(string $query, Authorizable $user): array
    {
        return array_slice($this->items(($this->search)($query, $user)), 0, self::LIMIT);
    }

    /**
     * Items by id (for the menu's chip on a saved link), or none without a find callback.
     *
     * @param  list<int>  $ids
     * @return list<array{link: string, id: int, title: string, hint: string|null, url: string}>
     */
    public function find(array $ids, Authorizable $user): array
    {
        return $this->find === null || $ids === [] ? [] : $this->items(($this->find)($ids, $user));
    }

    /**
     * The well-formed items the callback gave: an id above 0, a title and
     * a URL that's a path or http(s); others are left out.
     *
     * @param  iterable<mixed>  $items
     * @return list<array{link: string, id: int, title: string, hint: string|null, url: string}>
     */
    private function items(iterable $items): array
    {
        $kept = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? null;
            $title = $item['title'] ?? null;
            $url = $item['url'] ?? null;
            $hint = $item['hint'] ?? null;
            if (! is_int($id) || $id < 1 || ! is_string($title) || trim($title) === '' || ! is_string($url) || ! self::isSiteUrl($url)) {
                continue;
            }
            $kept[] = ['link' => $this->key.':'.$id, 'id' => $id, 'title' => $title, 'hint' => is_string($hint) && $hint !== '' ? $hint : null, 'url' => $url];
        }

        return $kept;
    }

    /** A path on the site (/about, not //host) or an http(s) address. */
    private static function isSiteUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return false;
        }

        return (str_starts_with($url, '/') && ! str_starts_with($url, '//')) || preg_match('#^https?://#i', $url) === 1;
    }
}
