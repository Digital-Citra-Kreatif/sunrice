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
