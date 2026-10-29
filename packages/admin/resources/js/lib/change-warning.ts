/** Treats empty and missing alike, and lists by their items. */
const comparable = (value: unknown): string => (value === null || value === undefined ? '' : typeof value === 'string' ? value : JSON.stringify(value));

/**
 * The warning a field shows (Field::warnWhenChanged()) for its value: once it
 * differs from the saved one, with ":old" and ":new" filled in; else null.
 * Without a saved value (creating) there's nothing to warn about.
 */
export function changeWarning(warning: unknown, value: unknown, saved: Record<string, unknown> | undefined, name: string): string | null {
    if (typeof warning !== 'string' || warning === '' || saved === undefined) return null;
    const old = comparable(saved[name]);
    const next = comparable(value);
    if (old === next) return null;

    return warning.replace(/:old\b/g, old).replace(/:new\b/g, next === '' ? '…' : next);
}

/**
 * A field's warning about the record as saved (Field::warning()): shown
 * while the field holds its saved value, as it may not hold for another;
 * null when creating, or once the value is changed.
 */
export function savedWarning<T extends { text: string }>(warning: T | null | undefined, value: unknown, saved: Record<string, unknown> | undefined, name: string): T | null {
    if (!warning || warning.text === '' || saved === undefined) return null;

    return comparable(saved[name]) === comparable(value) ? warning : null;
}
