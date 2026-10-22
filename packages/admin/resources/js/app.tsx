import '../css/admin.css';
import type * as React from 'react';
import { config, createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { initializeAppearance } from '@/hooks/use-appearance';
import { pageTitle } from '@/lib/branding';
import { trackPanelPath } from '@/lib/admin-url';
import { pluginPage, type PageComponent } from '@/lib/page-registry';
import { flushPrefetchAfterWrites } from '@/lib/prefetch-flush';
import type { SharedProps } from '@/types';

initializeAppearance();
// After a save, links don't show pages prefetched before it.
flushPrefetchAfterWrites(router);

// Page to page, the old page fades as the new one rises (admin.css ::view-transition-*),
// unless the person asked for less motion. Filters and pagination keep their state and stay put.
const viewTransitions = typeof document !== 'undefined' && 'startViewTransition' in document && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
config.set('visitOptions', (_href, options) => (viewTransitions && !options.preserveState && (options.method ?? 'get') === 'get' ? { viewTransition: true } : {}));

// The panel's own pages, each in its own chunk; the layout (sidebar, menus, command palette) in its
// own too, loaded with the first page that has it, so the main script stays small (a sign-in page needs neither).
const corePages = import.meta.glob<{ default: PageComponent }>('./pages/**/*.tsx');
const appLayout = () => import('@/layouts/AppLayout').then((module) => module.default);

let brand = 'Admin';

createInertiaApp({
    title: (title) => pageTitle(title, brand),
    resolve: async (name, page) => {
        const props = page?.props as Partial<SharedProps> | undefined;
        brand = props?.sunrice?.brand ?? brand;

        const core = corePages[`./pages/${name}.tsx`];
        const layout = appLayout(); // alongside the page, not after it
        const component = core ? (await core()).default : await pluginPage(name, props?.sunrice?.bundles ?? []);

        if (!component.bare && component.layout === undefined) {
            const AppLayout = await layout;
            component.layout = (child: React.ReactNode) => <AppLayout>{child}</AppLayout>;
        }

        return component;
    },
    setup({ el, App, props }) {
        trackPanelPath((props.initialPage.props as Partial<SharedProps>).sunrice?.path);
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#71717a' },
});
