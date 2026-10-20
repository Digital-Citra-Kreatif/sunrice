/** Where the sidebar remembers which of its groups are folded away. */
export const FOLDED_KEY = 'sunrice:sidebar:folded';

/** The folded groups' names from what was stored; anything else counts as none. */
export function parseFolded(stored: string | null): string[] {
    try {
        const value: unknown = JSON.parse(stored ?? '[]');
        return Array.isArray(value) ? value.filter((name): name is string => typeof name === 'string') : [];
    } catch {
        return [];
    }
}

/** The folded groups after one is opened or folded. */
export function withFolded(folded: string[], group: string, open: boolean): string[] {
    const others = folded.filter((name) => name !== group);
    return open ? others : [...others, group];
}
