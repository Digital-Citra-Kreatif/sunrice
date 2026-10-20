import * as React from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

function subscribe(onChange: () => void): () => void {
    const media = window.matchMedia(QUERY);
    media.addEventListener('change', onChange);

    return () => media.removeEventListener('change', onChange);
}

/** Whether the person asked the OS for less motion (travel animations should be skipped). */
export function useReducedMotion(): boolean {
    return React.useSyncExternalStore(subscribe, () => window.matchMedia(QUERY).matches, () => false);
}
