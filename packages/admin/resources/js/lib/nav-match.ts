import type { NavGroup, NavItem, NavLink } from '@/types';

export interface NavMatch {
    group: NavGroup;
    /** The sidebar item: the link itself, or the parent of the matched submenu link. */
    item: NavItem;
    /** The matched link: `item` itself, or one of its submenu's links. */
    link: NavLink;
}

/**
 * The navigation link whose address is the longest prefix of `path`
 * (submenu links included). The panel's root only matches itself.
 * `href` turns a link's url into an address ('users' → '/admin/users').
 */
export function matchNavigation(navigation: NavGroup[], path: string, href: (url: string) => string): NavMatch | null {
    const current = path.split('?')[0]?.replace(/\/+$/, '') || '/';
    let best: NavMatch | null = null;
    let bestLength = -1;
    const consider = (group: NavGroup, item: NavItem, link: NavLink) => {
        const address = href(link.url).replace(/\/+$/, '') || '/';
        const matches = current === address || (link.url !== '' && current.startsWith(`${address}/`));
        if (matches && address.length > bestLength) {
            best = { group, item, link };
            bestLength = address.length;
        }
    };
    for (const group of navigation) {
        for (const item of group.items) {
            consider(group, item, item);
            item.children.forEach((child) => consider(group, item, child));
        }
    }

    return best;
}

/** Breadcrumbs from the navigation, for pages that declare none: the parent item, then the matched link. */
export function navigationCrumbs(navigation: NavGroup[], path: string, href: (url: string) => string): { label: string; href?: string }[] {
    const match = matchNavigation(navigation, path, href);
    if (match === null) return [];
    const crumbs = match.link === match.item ? [] : [{ label: match.item.label, href: href(match.item.url) }];

    return [...crumbs, { label: match.link.label, href: href(match.link.url) }];
}
