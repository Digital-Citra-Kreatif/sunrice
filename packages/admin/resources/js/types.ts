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

export interface SharedProps {
    sunrice: { brand: string; branding: Branding; path: string; bundles: BundleInfo[] };
    auth: { user: { id: string | number; name: string | null; email: string | null } | null };
    navigation: NavGroup[];
    /** `id` is new for each message shown, so the same text twice toasts twice. */
    flash: { id: string | null; success: string | null; error: string | null };
    [key: string]: unknown;
}
