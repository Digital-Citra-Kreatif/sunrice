/**
 * A folded card's one-line summary: the first few filled-in values inside
 * it, joined with " · " ("Jl. Senopati 26 · Jakarta Selatan · 12190").
 * Text comes without its markup, lists count their items, nested values
 * (a group's) are read in order, and yes/no and empty values are left out.
 */
export function cardSummary(values: readonly unknown[], max = 3): string {
    const parts: string[] = [];
    const add = (value: unknown): void => {
        if (parts.length >= max || value === null || value === undefined || typeof value === 'boolean') return;
        if (typeof value === 'number') {
            parts.push(String(value));
        } else if (typeof value === 'string') {
            const text = value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            if (text !== '') parts.push(text.length > 60 ? `${text.slice(0, 57)}…` : text);
        } else if (Array.isArray(value)) {
            if (value.length > 0) parts.push(value.length === 1 ? '1 item' : `${value.length} items`);
        } else if (typeof value === 'object') {
            Object.values(value as Record<string, unknown>).forEach(add);
        }
    };
    values.forEach(add);

    return parts.join(' · ');
}
