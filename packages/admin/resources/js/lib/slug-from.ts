/** "Main menu" → "main_menu" (or "main-menu"): what a handle or an address segment may hold. */
export function slugify(text: string, separator: '_' | '-' = '_'): string {
    return text
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, separator)
        // A handle starts with a letter; both lose separators at the ends.
        .replace(separator === '_' ? /^[_0-9]+|_+$/g : /^-+|-+$/g, '')
        .slice(0, 64);
}

interface SluggedField { name: string; slugFrom?: unknown; slugSeparator?: unknown }

/**
 * The values after `name` changed to `value`: fields filled from it
 * (TextInput::slugFrom()) follow, unless someone typed in them already.
 */
export function withSlugs<T>(fields: SluggedField[], data: Record<string, T | string>, name: string, value: T, typed: ReadonlySet<string>): Record<string, T | string> {
    const next: Record<string, T | string> = { ...data, [name]: value };
    for (const field of fields) {
        if (field.slugFrom === name && !typed.has(field.name)) {
            next[field.name] = slugify(typeof value === 'string' ? value : '', field.slugSeparator === '-' ? '-' : '_');
        }
    }

    return next;
}
