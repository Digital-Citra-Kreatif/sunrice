/*
 * The rich text editor's link menu: the rel choices a link keeps (the
 * server always adds noopener noreferrer, RichTextLinkAttributes), what it
 * points at on the site (data-link="entry:12", Panel::richTextLinkSource())
 * and the search of what it can point at.
 */

/** The rel tokens editors may turn on, in the order they're written. */
export const REL_OPTIONS = ['nofollow', 'sponsored', 'ugc'] as const;

export type RelOption = (typeof REL_OPTIONS)[number];

/** The editor's choices in a rel attribute ("nofollow noopener noreferrer" → ['nofollow']), each once, in REL_OPTIONS' order. */
export function relTokens(rel: string | null | undefined): RelOption[] {
    const tokens = (rel ?? '').toLowerCase().split(/\s+/);

    return REL_OPTIONS.filter((option) => tokens.includes(option));
}

/** The rel to store after turning a choice on or off: the choices alone (the server adds the rest), or null for none. */
export function toggleRel(rel: string | null | undefined, option: RelOption): string | null {
    const current = relTokens(rel);
    const next = current.includes(option) ? current.filter((token) => token !== option) : [...current, option];
    const ordered = REL_OPTIONS.filter((token) => next.includes(token));

    return ordered.length === 0 ? null : ordered.join(' ');
}

/** What a link points at, from its data-link ("entry:12"), or null. */
export function parseDataLink(value: unknown): { source: string; id: number } | null {
    if (typeof value !== 'string') return null;
    const match = /^([a-z][a-z0-9_-]*):(\d{1,18})$/.exec(value.trim());

    return match ? { source: match[1]!, id: Number(match[2]) } : null;
}

/** The rel to store for these choices (the server adds the rest), or null for none. */
export function relAttribute(tokens: readonly string[]): string | null {
    const ordered = REL_OPTIONS.filter((token) => tokens.includes(token));

    return ordered.length === 0 ? null : ordered.join(' ');
}

/** A new link opens in a new tab when it's an address (another site, usually); the site's own pages (an entry, a term…) don't. */
export function defaultNewTab(kind: string): boolean {
    return kind === 'url';
}

export interface LinkItem { link: string; id: number; title: string; hint: string | null; url: string }

export interface LinkGroup { key: string; label: string; items: LinkItem[] }

/** The search's query string: what was typed and, for one kind only, its source. */
export function linkSearchQuery(query: string, source: string | null): string {
    const params = new URLSearchParams({ q: query.trim() });
    if (source !== null) params.set('source', source);

    return params.toString();
}

/** The answer's groups, leaving out anything malformed. */
export function linkGroupsOf(body: unknown): LinkGroup[] {
    const groups = (body as { groups?: unknown } | null)?.groups;
    if (!Array.isArray(groups)) return [];

    return groups.flatMap((group): LinkGroup[] => {
        if (group === null || typeof group !== 'object') return [];
        const { key, label, items } = group as Record<string, unknown>;
        if (typeof key !== 'string' || typeof label !== 'string' || !Array.isArray(items)) return [];
        const kept = items.filter((item): item is LinkItem => item !== null && typeof item === 'object'
            && typeof (item as LinkItem).link === 'string' && typeof (item as LinkItem).title === 'string' && typeof (item as LinkItem).url === 'string');

        return kept.length === 0 ? [] : [{ key, label, items: kept }];
    });
}
