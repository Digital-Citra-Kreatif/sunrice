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

/** The values of some keys in "key: value" lines (a Pairs field with fixed keys): the text after the first colon, as typed (only leading spaces dropped). */
export function fixedValues(text: string, keys: string[]): Record<string, string> {
    const values: Record<string, string> = Object.fromEntries(keys.map((key) => [key, '']));
    for (const line of text.split(/\r?\n/)) {
        const at = line.indexOf(':');
        const key = (at === -1 ? line : line.slice(0, at)).trim();
        if (key in values && values[key] === '') values[key] = at === -1 ? '' : line.slice(at + 1).replace(/^\s+/, '');
    }

    return values;
}

/** The lines with one key's value set (empty: its line removed), the other lines kept; a line break in the value becomes a space. */
export function withFixedValue(text: string, key: string, value: string): string {
    const clean = value.replace(/\s*\r?\n\s*/g, ' ');
    const lines = text.split(/\r?\n/).filter((line) => line.trim() !== '');
    const index = lines.findIndex((line) => (line.indexOf(':') === -1 ? line : line.slice(0, line.indexOf(':'))).trim() === key);
    const next = clean.trim() === '' ? null : `${key}: ${clean.trimStart()}`;
    if (index === -1) return next === null ? lines.join('\n') : [...lines, next].join('\n');

    return lines.flatMap((line, i) => (i === index ? (next === null ? [] : [next]) : [line])).join('\n');
}
