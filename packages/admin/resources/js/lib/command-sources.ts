/*
 * What the command palette (Cmd/Ctrl+K) lists: the navigation, and the
 * results of sources plugins register (search entries, orders…).
 */

export interface CommandItem {
    /** Unique within its source. */
    id: string;
    label: string;
    /** Smaller text after the label (a collection, a status). */
    description?: string;
    /** The heading the item is listed under. */
    group?: string;
    /** A navigation icon name (see nav-icon.tsx). */
    icon?: string;
    /** Opened with Inertia when chosen (a full href: use adminUrl()). */
    url?: string;
    /** Run when chosen, instead of or as well as opening `url`. */
    onSelect?: () => void;
    /** Other words the item matches on. */
    keywords?: string[];
}

export type CommandSource = (query: string, signal: AbortSignal) => Promise<CommandItem[]> | CommandItem[];

export interface CommandSourceOptions {
    /** Letters typed before the source is asked (2 by default). */
    minLength?: number;
}

interface Registered { source: CommandSource; minLength: number }

const sources = new Set<Registered>();
const listeners = new Set<() => void>();

/**
 * Adds a source of results to the command palette; returns a function that
 * removes it again. Called while the person types (debounced); a source that
 * fails or is aborted contributes nothing for that query.
 */
export function registerCommandSource(source: CommandSource, options: CommandSourceOptions = {}): () => void {
    const entry: Registered = { source, minLength: options.minLength ?? 2 };
    sources.add(entry);
    listeners.forEach((listener) => listener());

    return () => {
        sources.delete(entry);
        listeners.forEach((listener) => listener());
    };
}

/** The palette re-renders when a source is added or removed (useSyncExternalStore). */
export function subscribeCommandSources(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

let snapshot: readonly Registered[] = [];
let snapshotSize = -1;

export function commandSourcesSnapshot(): readonly Registered[] {
    if (snapshotSize !== sources.size) {
        snapshot = [...sources];
        snapshotSize = sources.size;
    }

    return snapshot;
}

/** Asks every registered source for this query; the ones that fail are left out. */
export async function queryCommandSources(query: string, signal: AbortSignal): Promise<CommandItem[]> {
    const asked = commandSourcesSnapshot().filter((entry) => query.trim().length >= entry.minLength);
    const results = await Promise.allSettled(asked.map((entry) => entry.source(query, signal)));

    return results.flatMap((result) => (result.status === 'fulfilled' ? result.value : []));
}

const normalize = (text: string) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const words = (query: string) => normalize(query).split(/\s+/).filter(Boolean);

/**
 * How well an item matches: 0 when a word of the query is missing, higher
 * when the label starts with the query, then when a word of the label does,
 * then when the match is in the label at all rather than in its keywords or
 * group.
 */
export function commandScore(item: CommandItem, query: string): number {
    const needles = words(query);
    if (needles.length === 0) return 1;
    const label = normalize(item.label);
    const haystack = [label, item.description ?? '', item.group ?? '', ...(item.keywords ?? [])].map(normalize).join(' ');

    let score = 0;
    for (const needle of needles) {
        if (label.startsWith(needle)) score += 4;
        else if (label.split(/\s+/).some((word) => word.startsWith(needle))) score += 3;
        else if (label.includes(needle)) score += 2;
        else if (haystack.includes(needle)) score += 1;
        else return 0;
    }

    return score;
}

/** The items matching the query, best first; everything for an empty query. */
export function matchCommands(items: readonly CommandItem[], query: string): CommandItem[] {
    return items
        .map((item, index) => ({ item, index, score: commandScore(item, query) }))
        .filter(({ score }) => score > 0)
        .sort((a, b) => b.score - a.score || a.index - b.index)
        .map(({ item }) => item);
}

export interface NavigationLike {
    group: string | null;
    items: { key: string; label: string; url: string; icon: string; children: { key: string; label: string; url: string; icon: string }[] }[];
}

/**
 * The navigation as commands: every item and its children, grouped as in the sidebar;
 * ungrouped ones under `pages` (t('Pages'): this file stays free of imports for the node tests).
 */
export function navigationCommands(navigation: readonly NavigationLike[], href: (url: string) => string, pages = 'Pages'): CommandItem[] {
    const commands: CommandItem[] = [];
    for (const group of navigation) {
        for (const item of group.items) {
            commands.push({ id: item.key, label: item.label, group: group.group ?? pages, icon: item.icon, url: href(item.url) });
            for (const child of item.children) {
                commands.push({ id: child.key, label: child.label, description: item.label, group: group.group ?? pages, icon: child.icon || item.icon, url: href(child.url), keywords: [item.label] });
            }
        }
    }

    return commands;
}
