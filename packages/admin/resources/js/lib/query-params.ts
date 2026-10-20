/**
 * The query string of a list's state: values that are empty (null, '')
 * or the same as their default are left out, and keys can be renamed
 * (`perPage` → `per_page`).
 *
 *     queryParams({ search: '', perPage: 100 }, { perPage: 50 }, { perPage: 'per_page' }) // { per_page: 100 }
 */
export function queryParams(
    state: object,
    defaults: Record<string, string | number | boolean | null> = {},
    names: Record<string, string> = {},
): Record<string, string | number> {
    const params: Record<string, string | number> = {};
    for (const [key, value] of Object.entries(state) as [string, unknown][]) {
        if (value === null || value === undefined || value === '' || value === false || value === defaults[key]) continue;
        if (typeof value !== 'string' && typeof value !== 'number' && value !== true) continue;
        params[names[key] ?? key] = value === true ? 1 : value;
    }

    return params;
}
