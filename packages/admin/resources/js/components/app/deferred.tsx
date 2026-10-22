import * as React from 'react';

const loaders: (() => Promise<unknown>)[] = [];

/**
 * A component whose code loads when it first shows, not with the panel's
 * main script: what the runtime gives plugins (window.Sunrice) is there
 * at once, while the date picker, media picker and drag and drop load with
 * the first page that uses them. `fallback` shows meanwhile; once loaded,
 * it renders straight away.
 */
export function deferred<M, K extends keyof M>(load: () => Promise<M>, name: K, fallback: React.ReactNode = null): M[K] {
    type Component = React.ComponentType<Record<string, unknown>>;
    let loaded: Component | null = null;
    const preload = () => load().then((module) => (loaded = module[name] as Component));
    loaders.push(preload);
    // Loaded already: a thenable that resolves at once, so React renders it without suspending.
    const Lazy = React.lazy(() => (loaded !== null
        ? ({ then: (resolve: (module: { default: Component }) => void) => resolve({ default: loaded as Component }) } as unknown as Promise<{ default: Component }>)
        : preload().then((component) => ({ default: component }))));
    function Deferred(props: Record<string, unknown>) {
        return (
            <React.Suspense fallback={fallback}>
                <Lazy {...props} />
            </React.Suspense>
        );
    }
    Deferred.displayName = String(name);

    return Deferred as M[K];
}

/** Loads every deferred component now (server rendering, tests). */
export function preloadDeferred(): Promise<unknown> {
    return Promise.all(loaders.map((load) => load()));
}
