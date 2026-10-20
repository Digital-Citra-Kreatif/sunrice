/** Helpers for the Help pages (Docs and Guide). */

export interface HelpHeading {
    id: string;
    text: string;
    level: number;
}

/**
 * Whether a click on a link inside a rendered help page should be an
 * Inertia visit: another page of the panel's Docs or Guide, on this site.
 * Same-page anchors, other sites, images opened in a new tab and the rest
 * of the panel go the browser's way.
 */
export function helpVisit(href: string, current: string, panelPath: string): string | null {
    let url: URL;
    let here: URL;
    try {
        here = new URL(current);
        url = new URL(href, here);
    } catch {
        return null;
    }
    if (url.origin !== here.origin) return null;
    if (url.pathname === here.pathname && url.search === here.search) return null;
    const base = panelPath.replace(/\/+$/, '');
    if (!/^\/(docs|guide)(\/|$)/.test(url.pathname.slice(base.length)) || !url.pathname.startsWith(`${base}/`)) return null;
    if (url.pathname.startsWith(`${base}/guide/images/`)) return null;

    return url.pathname + url.search + url.hash;
}

/**
 * The heading being read: the last one scrolled past the top of the
 * view (with some room), else the first.
 */
export function currentHeading(tops: { id: string; top: number }[], offset = 96): string | null {
    let current: string | null = tops[0]?.id ?? null;
    for (const { id, top } of tops) {
        if (top - offset <= 0) current = id;
        else break;
    }

    return current;
}
