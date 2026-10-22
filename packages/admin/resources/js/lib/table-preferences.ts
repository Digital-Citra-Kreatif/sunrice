/**
 * A table's saved choices (Preferences::table() on the server): the
 * visible columns' keys, in order, and the rows per page; null where the
 * user hasn't chosen.
 */
export interface TablePreferences {
    columns: string[] | null;
    perPage: number | null;
}

/** What the server sent (or nothing) as TablePreferences. */
export function normalizeTablePreferences(value: unknown): TablePreferences {
    const record = value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : {};
    const columns = Array.isArray(record.columns) ? record.columns.filter((column): column is string => typeof column === 'string') : null;
    const perPage = typeof record.perPage === 'number' && Number.isInteger(record.perPage) && record.perPage > 0 ? record.perPage : null;

    return { columns, perPage };
}

/**
 * The visible columns with `key` shown or hidden, in the order of `all`
 * (every column the table has).
 */
export function toggleColumn(visible: readonly string[], key: string, all: readonly string[]): string[] {
    const next = new Set(visible);
    if (next.has(key)) next.delete(key);
    else next.add(key);

    return all.filter((column) => next.has(column));
}
