/**
 * The Settings form's values after a save: what the server now holds (the
 * page's fresh `values` prop, secrets empty), so the form shows what was
 * saved, not what it held before. Falls back to what's on screen when the
 * response has no values.
 */
export function savedValues<T extends Record<string, unknown>>(props: Record<string, unknown>, onScreen: T): T {
    const values = props.values;
    if (values === null || typeof values !== 'object' || Array.isArray(values)) return onScreen;

    return { ...onScreen, ...(values as Partial<T>) };
}
