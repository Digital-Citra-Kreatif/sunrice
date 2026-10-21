import type { ShowWhen } from './show-when';

/** Field::helpValues(): a placeholder in the help filled from another field, else a fallback with {name} as field values. */
export interface HelpValue {
    field: string;
    fallback: string | null;
}

/** A link on a form (Link): {name} in its address is that field's value; it may show only while a field is (or isn't) so. */
export interface FormLink {
    label: string;
    url: string;
    newTab: boolean;
    showWhen: (ShowWhen & { not?: boolean }) | null;
}

const text = (value: unknown): string => (typeof value === 'string' || typeof value === 'number' ? String(value) : '');

/** "/{handle}" with the form's values: "/blog". */
export function withValues(template: string, data: Record<string, unknown>): string {
    return template.replace(/\{([a-z_][a-z0-9_]*)\}/gi, (match, name: string) => (name in data ? text(data[name]) : match));
}

/** The help with its :placeholders filled from the form as it's edited (Field::helpValues()). */
export function liveHelp(help: string, values: Record<string, HelpValue> | undefined, data: Record<string, unknown>): string {
    if (!values) return help;

    return help.replace(/:([a-z_]+)/gi, (match, name: string) => {
        const value = values[name];
        if (!value) return match;
        const own = text(data[value.field]);

        return own !== '' ? own : withValues(value.fallback ?? '', data);
    });
}

/** Field::developerHelp()'s text with :value as the field's value, or the example while it's empty. */
export function developerText(textWithValue: string, value: unknown, example: string | null): string {
    const own = text(value);

    return textWithValue.replaceAll(':value', own !== '' ? own : (example ?? ''));
}

/** A text's `backtick` parts, for code: [["In a template: ", false], ["<x-form />", true]]. */
export function codeParts(source: string): [string, boolean][] {
    return source.split(/(`[^`]*`)/).filter((part) => part !== '').map((part) => (part.startsWith('`') && part.endsWith('`') && part.length > 1 ? [part.slice(1, -1), true] : [part, false]));
}

/** The links to show now, their addresses with the form's values ({name}, also URL-encoded as %7Bname%7D). */
export function visibleLinks(links: FormLink[] | undefined, data: Record<string, unknown>): FormLink[] {
    return (links ?? []).filter((link) => {
        if (!link.showWhen) return true;
        const other = data[link.showWhen.field];
        const filled = other !== null && other !== undefined && other !== false && other !== '' && !(Array.isArray(other) && other.length === 0);
        // As a field's showWhen (lib/show-when): filled in, or holding the value.
        const shown = link.showWhen.value === null ? filled : Array.isArray(other) ? other.map(String).includes(String(link.showWhen.value)) : String(other ?? '') === String(link.showWhen.value);
        return link.showWhen.not ? !shown : shown;
    }).map((link) => ({
        ...link,
        url: withValues(link.url.replace(/%7B([a-z_][a-z0-9_]*)%7D/gi, '{$1}'), Object.fromEntries(Object.entries(data).map(([key, value]) => [key, encodeURIComponent(text(value))]))),
    }));
}
