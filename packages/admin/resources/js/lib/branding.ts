/** Helpers for the panel's branding (Settings → Branding) and the user menu. Pure, so node tests can import them. */

/** Up to two initials for an avatar: "Rina Wijaya" → "RW", "budi@example.com" → "BE". */
export function initials(name: string | null | undefined, email?: string | null): string {
    const source = (name ?? '').trim() || (email ?? '').trim();
    const letters = source
        .split(/[\s@._-]+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => Array.from(part)[0]!.toUpperCase());

    return letters.join('') || '?';
}

/** Whether the value is a colour as #rrggbb. */
export function isHexColor(value: string): boolean {
    return /^#[0-9a-f]{6}$/i.test(value);
}

/** WCAG relative luminance of #rrggbb, 0 (black) to 1 (white); the same maths as Branding::luminance(). */
export function luminance(hex: string): number {
    const channel = (offset: number) => {
        const c = parseInt(hex.slice(1 + offset, 3 + offset), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel(0) + 0.7152 * channel(2) + 0.0722 * channel(4);
}

/** Black or white text, whichever reads better on `hex` (as Branding::readableOn()). */
export function readableOn(hex: string): '#ffffff' | '#111111' {
    const l = luminance(hex);

    return 1.05 / (l + 0.05) >= (l + 0.05) / 0.05 ? '#ffffff' : '#111111';
}

/** What's typed into a colour field: trimmed, and six hex digits get their "#". */
export function normalizeHex(value: string): string {
    const trimmed = value.trim();

    return /^[0-9a-f]{6}$/i.test(trimmed) ? `#${trimmed}` : trimmed;
}

/** The document title: "Users · Acme", or the title alone when it names the panel already ("Log in to Acme"). */
export function pageTitle(title: string | undefined, brand: string): string {
    if (!title || title === brand) return brand;

    return title.includes(brand) ? title : `${title} · ${brand}`;
}
