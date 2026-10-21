import type { ReactNode } from 'react';

/** An item of a page header's "⋮" menu: a link (`href`) or an action (`onSelect`). */
export interface PageHeaderMenuItem {
    label: ReactNode;
    /** An address: a panel page (an Inertia visit), or with `external`, another site in a new tab; with `download`, a file. */
    href?: string;
    onSelect?: () => void;
    icon?: ReactNode;
    external?: boolean;
    download?: boolean;
    /** Items with the same group sit together under its heading, in the order their groups first appear. */
    group?: string;
    destructive?: boolean;
}

/** Items by their group ('' for none), in the order the groups first appear. */
export function menuGroups(items: PageHeaderMenuItem[]): [string, PageHeaderMenuItem[]][] {
    const groups = new Map<string, PageHeaderMenuItem[]>();
    for (const item of items) {
        const key = item.group ?? '';
        groups.set(key, [...(groups.get(key) ?? []), item]);
    }

    return [...groups];
}
