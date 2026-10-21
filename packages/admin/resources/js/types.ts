/** Page data every admin page receives (see HandleInertiaRequests). */

export interface NavLink {
    key: string;
    label: string;
    url: string;
    icon: string;
}

export interface NavItem extends NavLink {
    children: NavLink[];
}

export interface NavGroup {
    group: string | null;
    items: NavItem[];
}

export interface BundleInfo {
    id: string;
    module: string | null;
    styles: string[];
    pages: string[];
}

/** Settings → Branding with the panel's defaults filled in (Support\Branding::toArray()). */
export interface Branding {
    name: string;
    tagline: string;
    /** The logo image's URL, or null for the lettermark. */
    logo: string | null;
    /** The logo is wider than square (a wordmark): shown instead of the name. */
    logoWide?: boolean;
    /** The name's first letter, shown without a logo. */
    lettermark: string;
    color: string | null;
    login: {
        layout: 'split' | 'centered';
        eyebrow: string;
        headline: string;
        text: string;
        footer: string;
        image: string | null;
    };
}

/** The success toast's button (flashed as "success_action"): Undo and the like. */
export interface FlashAction { label: string; url: string; method: 'get' | 'post'; data: Record<string, unknown> }

export interface SharedProps {
    /** locale: the panel's language; locales: those a user may pick (code => name). */
    sunrice: { brand: string; branding: Branding; path: string; bundles: BundleInfo[]; commandSearches?: { id: string; url: string; minLength: number }[]; locale: string; locales: Record<string, string> };
    auth: { user: { id: string | number; name: string | null; email: string | null } | null };
    navigation: NavGroup[];
    /** `id` is new for each message shown, so the same text twice toasts twice. */
    flash: { id: string | null; success: string | null; error: string | null; action?: FlashAction | null };
    [key: string]: unknown;
}
