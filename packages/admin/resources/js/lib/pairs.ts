/** One row of a Pairs field: a key, its value, and the optional third column. */
export interface Pair {
    key: string;
    value: string;
    extra?: string;
}

/** "en: English | en" lines as rows (a line without ": " is a key alone; the part after " | " is the third column). */
export function parsePairs(text: string): Pair[] {
    return text.split(/\r?\n/).filter((line) => line.trim() !== '').map((line) => {
        const bar = line.indexOf('|');
        const main = bar === -1 ? line : line.slice(0, bar);
        const extra = bar === -1 ? '' : line.slice(bar + 1).trim();
        const at = main.indexOf(':');
        const pair = at === -1 ? { key: main.trim(), value: '' } : { key: main.slice(0, at).trim(), value: main.slice(at + 1).trim() };
        return extra === '' ? pair : { ...pair, extra };
    });
}

/** Rows as lines to keep; empty rows are left out. */
export function formatPairs(pairs: Pair[]): string {
    return pairs.filter((pair) => pair.key.trim() !== '' || pair.value.trim() !== '')
        .map((pair) => {
            const line = pair.value.trim() === '' ? pair.key.trim() : `${pair.key.trim()}: ${pair.value.trim()}`;
            return pair.extra?.trim() ? `${line} | ${pair.extra.trim()}` : line;
        }).join('\n');
}
