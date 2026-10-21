/** The part of Inertia's router this needs (a fake one in the tests). */
export interface FlushingRouter {
    on(event: 'finish', callback: (event: { detail: { visit: { method: string; prefetch?: boolean } } }) => void): () => void;
    flushAll(): void;
}

/**
 * Drops every prefetched page once a write (a POST, PUT, PATCH or DELETE
 * visit) finishes. The sidebar's links prefetch on hover and Inertia keeps
 * those responses for a while, so a list prefetched before a create would
 * otherwise be shown without the new record when a link to it is clicked.
 * Registered once, at boot; returns the way to remove it.
 */
export function flushPrefetchAfterWrites(router: FlushingRouter): () => void {
    return router.on('finish', (event) => {
        if (event.detail.visit.method.toLowerCase() !== 'get') router.flushAll();
    });
}
