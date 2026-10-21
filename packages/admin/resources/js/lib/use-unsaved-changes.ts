import * as React from 'react';
import { router } from '@inertiajs/react';
import { askToLeave } from '@/lib/leave-confirm';
import { createUnloadGuard } from '@/lib/unload-guard';

/**
 * Guards a form with unsaved edits: asks before leaving the page (links and
 * the back button in the panel's own dialog, closing the tab in the
 * browser's) and saves on Ctrl/Cmd+S. Shared with
 * plugins as `useUnsavedChanges` in @sunrice/admin-sdk/ui.
 */
export function useUnsavedChanges(dirty: boolean, onSave?: () => void): void {
    const dirtyRef = React.useRef(dirty);
    dirtyRef.current = dirty;
    const saveRef = React.useRef(onSave);
    saveRef.current = onSave;

    React.useEffect(() => {
        // Not for a reload the app starts itself after a save (createUnloadGuard()).
        const guard = createUnloadGuard();
        const removeStart = router.on('start', (event) => guard.start(event.detail.visit));
        const removeError = router.on('error', () => guard.error());
        const removeNavigate = router.on('navigate', () => guard.navigate());
        const beforeUnload = (event: BeforeUnloadEvent) => {
            if (!guard.shouldWarn(dirtyRef.current)) return;
            event.preventDefault();
            event.returnValue = '';
        };
        // Only page changes (GET visits) leave the form; saves are posts and
        // puts. Prefetches and partial reloads of this page don't leave it, and
        // a visit already confirmed isn't asked about again.
        let confirmed = false;
        const removeBefore = router.on('before', (event) => {
            const visit = event.detail.visit;
            if (!dirtyRef.current || confirmed || visit.method !== 'get' || visit.prefetch || visit.only.length > 0) return;
            // Stopped, and made again if the person chooses to leave.
            event.preventDefault();
            askToLeave(() => {
                confirmed = true;
                router.visit(visit.url, {
                    method: visit.method, data: visit.data, replace: visit.replace,
                    preserveScroll: visit.preserveScroll, preserveState: visit.preserveState, headers: visit.headers,
                });
            });
        });
        // A confirmed visit that didn't leave (cancelled, failed): ask again next time.
        const removeFinish = router.on('finish', (event) => {
            confirmed = false;
            guard.finish(event.detail.visit);
        });
        const keydown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's' && saveRef.current) {
                event.preventDefault();
                saveRef.current();
            }
        };
        window.addEventListener('beforeunload', beforeUnload);
        window.addEventListener('keydown', keydown);

        return () => {
            removeBefore();
            removeFinish();
            removeStart();
            removeError();
            removeNavigate();
            window.removeEventListener('beforeunload', beforeUnload);
            window.removeEventListener('keydown', keydown);
        };
    }, []);
}
