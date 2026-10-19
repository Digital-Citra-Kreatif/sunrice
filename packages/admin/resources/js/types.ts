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

export interface SharedProps {
    sunrice: { brand: string; path: string; bundles: BundleInfo[] };
    auth: { user: { id: string | number; name: string | null; email: string | null } | null };
    navigation: NavGroup[];
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}
