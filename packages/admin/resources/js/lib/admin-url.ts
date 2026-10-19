import { router } from '@inertiajs/react';
import { joinPath } from '@/lib/bundles';
import type { SharedProps } from '@/types';

let base = '/admin';

/** Keep the panel path from the page data (set on the first page and every visit). */
export function trackPanelPath(initial: string | undefined): void {
    if (initial) base = initial;
    router.on('navigate', (event) => {
        const path = (event.detail.page.props as Partial<SharedProps>).sunrice?.path;
        if (path) base = path;
    });
}

/** adminUrl('shop/orders') → '/admin/shop/orders'. */
export function adminUrl(path = ''): string {
    return joinPath(base, path);
}
