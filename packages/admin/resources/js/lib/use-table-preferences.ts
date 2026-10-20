import * as React from 'react';
import { adminUrl } from '@/lib/admin-url';
import { fetchJson } from '@/lib/fetch-json';
import { normalizeTablePreferences, type TablePreferences } from '@/lib/table-preferences';

export type { TablePreferences } from '@/lib/table-preferences';

export interface TablePreferencesState {
    preferences: TablePreferences;
    /** Saves the visible columns (null: the table's default). */
    setColumns: (columns: string[] | null) => Promise<void>;
    /** Saves the rows per page (null: the table's default). */
    setPerPage: (perPage: number | null) => Promise<void>;
}

/**
 * The signed-in user's saved columns and page size for a table, kept on
 * the server per user (`admin/preferences/{key}`). Pass what the page's
 * props already say as `initial` (the server reads the same preference);
 * without it the hook fetches it. Shared with plugins as
 * `useTablePreferences` in @sunrice/admin-sdk/ui.
 */
export function useTablePreferences(key: string, initial?: Partial<TablePreferences> | null): TablePreferencesState {
    const [preferences, setPreferences] = React.useState<TablePreferences>(() => normalizeTablePreferences(initial));
    const current = React.useRef(preferences);
    current.current = preferences;
    const url = adminUrl(`preferences/${encodeURIComponent(key)}`);

    React.useEffect(() => {
        if (initial !== undefined) return;
        const controller = new AbortController();
        fetchJson<{ value: unknown }>(url, { signal: controller.signal })
            .then((response) => setPreferences(normalizeTablePreferences(response.value)))
            .catch(() => undefined);
        return () => controller.abort();
        // Fetched once per key.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url]);

    const save = React.useCallback(async (changes: Partial<TablePreferences>) => {
        const next = { ...current.current, ...changes };
        current.current = next;
        setPreferences(next);
        await fetchJson(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ value: next }),
        }).catch(() => undefined);
    }, [url]);

    return {
        preferences,
        setColumns: React.useCallback((columns: string[] | null) => save({ columns }), [save]),
        setPerPage: React.useCallback((perPage: number | null) => save({ perPage }), [save]),
    };
}
