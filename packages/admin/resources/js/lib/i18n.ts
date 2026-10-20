/**
 * The panel's words in the user's language: t('Save') → 'Simpan'. The
 * English text is the key (Laravel JSON translations, lang/{code}.json in
 * each package); the page carries the words for the panel's language in
 * window.sunriceTranslations (PanelLocale::dictionary()). Placeholders are
 * Laravel's: t('Delete :title?', { title }) — :Title and :TITLE capitalise.
 */
export type Replacements = Record<string, string | number | null | undefined>;

function words(): Record<string, string> {
    const dictionary = (globalThis as { sunriceTranslations?: unknown }).sunriceTranslations;

    return dictionary !== null && typeof dictionary === 'object' ? dictionary as Record<string, string> : {};
}

export function t(text: string, replace: Replacements = {}): string {
    const said = words()[text];
    let out = typeof said === 'string' && said !== '' ? said : text;
    // Longest names first, so :name doesn't eat :names.
    for (const name of Object.keys(replace).sort((a, b) => b.length - a.length)) {
        const value = String(replace[name] ?? '');
        out = out
            .replaceAll(`:${name.toUpperCase()}`, value.toUpperCase())
            .replaceAll(`:${name.charAt(0).toUpperCase()}${name.slice(1)}`, value.charAt(0).toUpperCase() + value.slice(1))
            .replaceAll(`:${name}`, value);
    }

    return out;
}
