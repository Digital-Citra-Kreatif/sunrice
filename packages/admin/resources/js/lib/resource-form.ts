/**
 * A resource form's identity: Inertia keeps a page's component state over
 * a visit to the same component, so without a key a record created here
 * would open its edit page with what was typed (a password among it).
 */
export function resourceFormKey(resource: { key: string }, record: { key: string } | null): string {
    return `${resource.key}:${record ? record.key : 'new'}`;
}

/**
 * The values the server sent back after a save, which become the form's
 * data and its baseline: a password typed for the save isn't kept (or
 * sent again with the next one). Falls back to what was on screen.
 */
export function savedValues<T extends Record<string, unknown>>(props: Record<string, unknown> | undefined, fallback: T): T {
    const values = props?.values;

    return values !== null && typeof values === 'object' && !Array.isArray(values) ? (values as T) : fallback;
}
