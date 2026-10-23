/** The languages the code editor highlights; anything else is edited as plain text. */
export const CODE_LANGUAGES = ['html', 'css', 'javascript', 'json', 'plain'] as const;

export type CodeLanguage = (typeof CODE_LANGUAGES)[number];

const ALIASES: Record<string, CodeLanguage> = { htm: 'html', js: 'javascript', mjs: 'javascript', text: 'plain', txt: 'plain' };

/** A language the editor knows, from a field's setting ("html", "JS"…); plain for anything else. */
export function codeLanguage(value: unknown): CodeLanguage {
    if (typeof value !== 'string') return 'plain';
    const name = value.trim().toLowerCase();

    return (CODE_LANGUAGES as readonly string[]).includes(name) ? name as CodeLanguage : ALIASES[name] ?? 'plain';
}

/**
 * How many lines the editor shows: it grows from `minLines` (4) to
 * `maxLines` (20), then scrolls. Whole numbers of at least one line, the
 * most never under the least.
 */
export function codeEditorLines(minLines?: number | null, maxLines?: number | null): { min: number; max: number } {
    const whole = (value: number | null | undefined, fallback: number) => (typeof value === 'number' && Number.isFinite(value) ? Math.max(1, Math.round(value)) : fallback);
    const min = whole(minLines, 4);

    return { min, max: Math.max(min, whole(maxLines, Math.max(min, 20))) };
}
