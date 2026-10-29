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

/** What the top of the sidebar shows (Support\Branding::display()). */
export type BrandDisplay = 'logo_name' | 'logo' | 'icon_name' | 'name';

/** The parts of the branding (`sunrice.branding`) that decide the sidebar's header. */
export interface BrandImages {
    logo: string | null;
    logoWide?: boolean;
    /** Resolved on the server; missing in page data from before it was shared. */
    display?: BrandDisplay;
    /** The square mark: the browser icon, else a square logo; null for the lettermark. */
    mark?: string | null;
}

/** The sidebar header's parts. An image of null is the lettermark. */
export interface BrandHeader {
    display: BrandDisplay;
    /** The logo at its own proportions, alone (`logo`). */
    wordmark: string | null;
    /** Whether a square image (or the lettermark) sits beside the name. */
    showMark: boolean;
    /** That square image beside the name; null for the lettermark. */
    mark: string | null;
    showName: boolean;
    /** The collapsed sidebar's square: the same as the favicon (never a wide logo); null for the lettermark. */
    collapsedMark: string | null;
}

/**
 * The sidebar header for the branding: the server's `display` and `mark`
 * laid out. "Logo and name" puts the logo beside the name unless it is
 * wide, then the mark; "Icon and name" the mark; the collapsed sidebar
 * always the mark, as the browser tab.
 */
export function brandHeader(branding: BrandImages): BrandHeader {
    const squareLogo = branding.logo && !branding.logoWide ? branding.logo : null;
    // Page data without the fields: today's rule, and the square logo as the mark.
    const display: BrandDisplay = branding.display ?? (branding.logo && branding.logoWide ? 'logo' : 'logo_name');
    const mark = branding.mark === undefined ? squareLogo : branding.mark;
    const wordmark = display === 'logo' ? branding.logo : null;

    return {
        display,
        wordmark,
        showMark: display === 'logo_name' || display === 'icon_name',
        mark: display === 'logo_name' ? (squareLogo ?? mark) : mark,
        showName: wordmark === null,
        collapsedMark: mark,
    };
}

/** The Branding form's values the sidebar preview needs (BrandingPreviewController's query); nothing else is sent. */
export function brandPreviewQuery(data: Record<string, unknown>): Record<string, string | number | null> {
    const text = (value: unknown) => (typeof value === 'string' ? value : null);
    const id = (value: unknown) => (typeof value === 'number' && Number.isInteger(value) ? value : null);

    return { name: text(data.name), tagline: text(data.tagline), logo: id(data.logo), favicon: id(data.favicon), color: text(data.color) };
}
