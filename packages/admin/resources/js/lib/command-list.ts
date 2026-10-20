/*
 * The pure parts of the panel's command list (components/ui/command.tsx):
 * how an item scores against the search, the order the matches show in, and
 * where the arrow keys move the active item. Kept here so they're tested
 * without a DOM.
 */
import { commandScore } from '@/lib/command-sources';

/** How well an item with this value and keywords matches the search: 0 hides it. */
export function itemScore(value: string, keywords: readonly string[] | undefined, search: string): number {
    return commandScore({ id: value, label: value, keywords: keywords ? [...keywords] : undefined }, search);
}

export interface RankedItem {
    score: number;
    /** The group the item is in, or null when it sits directly in the list. */
    group: string | null;
}

/**
 * The order matches are shown in, as indexes into `items` (given in document
 * order): groups by their best item, then items by score, ties in document
 * order. With equal scores (no search, or no filtering) it's document order.
 * The list orders itself the same way with CSS `order`.
 */
export function displayOrder(items: readonly RankedItem[]): number[] {
    const best = new Map<string, number>();
    const first = new Map<string, number>();
    items.forEach((item, index) => {
        if (item.group === null) return;
        best.set(item.group, Math.max(best.get(item.group) ?? 0, item.score));
        if (!first.has(item.group)) first.set(item.group, index);
    });
    const top = (index: number): [number, number] => {
        const item = items[index]!;

        return item.group === null ? [item.score, index] : [best.get(item.group)!, first.get(item.group)!];
    };

    return items.map((_, index) => index).sort((a, b) => {
        const [scoreA, placeA] = top(a);
        const [scoreB, placeB] = top(b);

        return scoreB - scoreA || placeA - placeB || items[b]!.score - items[a]!.score || a - b;
    });
}

export type NavigationKey = 'ArrowDown' | 'ArrowUp' | 'Home' | 'End';

export function isNavigationKey(key: string): key is NavigationKey {
    return key === 'ArrowDown' || key === 'ArrowUp' || key === 'Home' || key === 'End';
}

/**
 * The value that becomes active after this key, from the selectable values
 * in display order. Down from the last (and up from the first) wraps round
 * when `loop`; from nothing active, down goes to the first and up to the last.
 */
export function nextActive(values: readonly string[], active: string | undefined, key: NavigationKey, loop = true): string | undefined {
    if (values.length === 0) return undefined;
    const last = values.length - 1;
    const index = active === undefined ? -1 : values.indexOf(active);

    switch (key) {
        case 'Home':
            return values[0];
        case 'End':
            return values[last];
        case 'ArrowDown':
            if (index === -1) return values[0];
            return values[index === last ? (loop ? 0 : last) : index + 1];
        case 'ArrowUp':
            if (index === -1) return values[last];
            return values[index === 0 ? (loop ? last : 0) : index - 1];
    }
}
