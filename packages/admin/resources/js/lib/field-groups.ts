/** A form field as far as grouping goes: its Field::group() title, if any. */
interface Grouped {
    group?: unknown;
}

/**
 * A section's fields (those shown) in runs by their Field::group() title
 * (null: none), in order: fields next to each other with the same title
 * make one group, under its heading.
 */
export function fieldGroups<T extends Grouped>(fields: T[]): { title: string | null; fields: T[] }[] {
    const groups: { title: string | null; fields: T[] }[] = [];
    for (const field of fields) {
        const title = typeof field.group === 'string' && field.group !== '' ? field.group : null;
        const last = groups[groups.length - 1];
        if (last && last.title === title) last.fields.push(field);
        else groups.push({ title, fields: [field] });
    }

    return groups;
}
