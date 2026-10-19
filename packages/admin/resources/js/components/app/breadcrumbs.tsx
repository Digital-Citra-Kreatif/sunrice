import * as React from 'react';

export interface Crumb {
    label: string;
    href?: string;
}

type Store = { crumbs: Crumb[] | null; set: (crumbs: Crumb[] | null) => void };

const BreadcrumbContext = React.createContext<Store | null>(null);

/** Holds a page-declared breadcrumb trail for the header. */
export function BreadcrumbProvider({ children }: { children: React.ReactNode }) {
    const [crumbs, set] = React.useState<Crumb[] | null>(null);
    const value = React.useMemo(() => ({ crumbs, set }), [crumbs]);

    return <BreadcrumbContext.Provider value={value}>{children}</BreadcrumbContext.Provider>;
}

export function useBreadcrumbOverride(): Crumb[] | null {
    return React.useContext(BreadcrumbContext)?.crumbs ?? null;
}

/**
 * Declare the breadcrumb trail for pages the sidebar does not list
 * (e.g. an entry editor). By default the header derives it from the
 * navigation item matching the current URL.
 */
export function useBreadcrumbs(crumbs: Crumb[]) {
    const store = React.useContext(BreadcrumbContext);
    const key = JSON.stringify(crumbs);

    React.useEffect(() => {
        store?.set(JSON.parse(key) as Crumb[]);
        return () => store?.set(null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);
}
