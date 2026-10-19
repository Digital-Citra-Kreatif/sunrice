/**
 * Which plugin bundle provides a page. Pure, so it can be tested without
 * a browser (tests/Frontend/bundles.test.ts).
 */

export interface PageBundle {
    id: string;
    module: string | null;
    styles: string[];
    pages: string[];
}

/** The bundle whose page prefix matches the page name (the longest match wins). */
export function bundleFor<B extends PageBundle>(name: string, bundles: readonly B[]): B | undefined {
    let found: B | undefined;
    let length = -1;
    for (const bundle of bundles) {
        for (const prefix of bundle.pages) {
            if (name.startsWith(prefix) && prefix.length > length) {
                found = bundle;
                length = prefix.length;
            }
        }
    }

    return found;
}

/** "orders" → "/admin/orders" for a panel at /admin; absolute URLs pass through. */
export function joinPath(base: string, path = ''): string {
    if (/^[a-z][a-z0-9+.-]*:\/\//i.test(path) || path.startsWith('//')) {
        return path;
    }
    const root = `/${base.replace(/^\/+|\/+$/g, '')}`;
    const rest = path.replace(/^\/+/, '');

    return rest === '' ? root : `${root === '/' ? '' : root}/${rest}`;
}
