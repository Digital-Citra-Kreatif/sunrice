import '../css/admin.css';
import type * as React from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import AppLayout from '@/layouts/AppLayout';
import { initializeAppearance } from '@/hooks/use-appearance';
import { trackPanelPath } from '@/lib/admin-url';
import { pluginPage, type PageComponent } from '@/lib/page-registry';
import { installRuntime } from '@/runtime';
import type { SharedProps } from '@/types';

initializeAppearance();
installRuntime();

// The panel's own pages, each in its own chunk.
const corePages = import.meta.glob<{ default: PageComponent }>('./pages/**/*.tsx');

let brand = 'Admin';

createInertiaApp({
    title: (title) => (title && title !== brand ? `${title} · ${brand}` : brand),
    resolve: async (name, page) => {
        const props = page?.props as Partial<SharedProps> | undefined;
        brand = props?.sunrice?.brand ?? brand;

        const core = corePages[`./pages/${name}.tsx`];
        const component = core ? (await core()).default : await pluginPage(name, props?.sunrice?.bundles ?? []);

        if (!component.bare && component.layout === undefined) {
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
