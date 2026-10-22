/** What's typed in a slug input, kept to lowercase letters, digits and the separator ("Contact-Person " → "contact_person_"), the end left as typed. */
export function slugInput(text: string, separator = '-'): string {
    return text
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, separator)
        .replace(new RegExp(`^${escape(separator)}+`), '');
}

function escape(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
