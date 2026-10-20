/** One row of a Pairs field. */
export interface Pair {
    key: string;
    value: string;
}

/** "en: English" lines as rows (a line without ": " is a key alone). */
export function parsePairs(text: string): Pair[] {
    return text.split(/\r?\n/).filter((line) => line.trim() !== '').map((line) => {
        const at = line.indexOf(':');
        return at === -1 ? { key: line.trim(), value: '' } : { key: line.slice(0, at).trim(), value: line.slice(at + 1).trim() };
    });
}

/** Rows as lines to keep; empty rows are left out. */
export function formatPairs(pairs: Pair[]): string {
    return pairs.filter((pair) => pair.key.trim() !== '' || pair.value.trim() !== '')
        .map((pair) => (pair.value.trim() === '' ? pair.key.trim() : `${pair.key.trim()}: ${pair.value.trim()}`)).join('\n');
}
