import * as React from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'sunrice-appearance';

const prefersDark = () => typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;

function readAppearance(): Appearance {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
}

export function applyAppearance(appearance: Appearance) {
    const dark = appearance === 'dark' || (appearance === 'system' && prefersDark());
    document.documentElement.classList.toggle('dark', dark);
}

/** Keep the document in sync with the OS theme while "system" is selected. */
export function initializeAppearance() {
    applyAppearance(readAppearance());
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyAppearance(readAppearance()));
}

export function useAppearance() {
    const [appearance, setAppearance] = React.useState<Appearance>(readAppearance);

    const updateAppearance = React.useCallback((value: Appearance) => {
        setAppearance(value);
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch {
            // Storage unavailable: the choice still applies to this page view.
        }
        applyAppearance(value);
    }, []);

    const resolved: 'light' | 'dark' = appearance === 'system' ? (prefersDark() ? 'dark' : 'light') : appearance;

    return { appearance, resolved, updateAppearance } as const;
}
