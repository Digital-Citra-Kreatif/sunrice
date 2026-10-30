import * as React from 'react';
import { router } from '@inertiajs/react';
import { askToLeave } from '@/lib/leave-confirm';
import { createUnloadGuard } from '@/lib/unload-guard';

/**
 * Guards a form with unsaved edits: asks before leaving the page (links and
 * the back button in the panel's own dialog, closing the tab in the
 * browser's) and saves on Ctrl/Cmd+S. Shared with
 * plugins as `useUnsavedChanges` in @imajiner/admin-sdk/ui.
 */
export function useUnsavedChanges(dirty: boolean, onSave?: () => void): void {
    const dirtyRef = React.useRef(dirty);
    dirtyRef.current = dirty;
    const saveRef = React.useRef(onSave);
    saveRef.current = onSave;

    React.useEffect(() => {
        // Not for a reload the app starts itself after a save (createUnloadGuard()).
        const guard = createUnloadGuard();
        // The person chose to leave: the visit isn't asked about again (see below).
        let confirmed = false;
        const removeStart = router.on('start', (event) => guard.start(event.detail.visit));
        const removeError = router.on('error', () => guard.error());
        const removeNavigate = router.on('navigate', () => {
            confirmed = false;
            guard.navigate();
        });
        const beforeUnload = (event: BeforeUnloadEvent) => {
            if (!guard.shouldWarn(dirtyRef.current)) return;
            event.preventDefault();
            event.returnValue = '';
        };
        // Only page changes (GET visits) leave the form; saves are posts and
        // puts. Prefetches and partial reloads of this page don't leave it, and
        // a visit already confirmed isn't asked about again.
        const removeBefore = router.on('before', (event) => {
            const visit = event.detail.visit;
            if (!dirtyRef.current || confirmed || visit.method !== 'get' || visit.prefetch || visit.only.length > 0) return;
            // Stopped, and made again if the person chooses to leave.
            event.preventDefault();
            askToLeave(() => {
                confirmed = true;
                guard.leave();
                router.visit(visit.url, {
                    method: visit.method, data: visit.data, replace: visit.replace,
                    preserveScroll: visit.preserveScroll, preserveState: visit.preserveState, headers: visit.headers,
                });
            });
        });
        // A confirmed visit that didn't leave (cancelled, failed): ask again next time.
        // One that the server redirects (another visit) or answers with a full
        // page load is still leaving: not asked again, in the panel or the browser.
        const removeFinish = router.on('finish', (event) => {
            const visit = event.detail.visit;
            if (visit.cancelled || visit.interrupted) confirmed = false;
            guard.finish(visit);
        });
        // Or one that failed (an error page, no connection): still here.
        const stillHere = () => {
            confirmed = false;
            guard.error();
        };
        const removeHttpException = router.on('httpException', stillHere);
        const removeNetworkError = router.on('networkError', stillHere);
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
            removeHttpException();
            removeNetworkError();
            removeStart();
            removeError();
            removeNavigate();
            window.removeEventListener('beforeunload', beforeUnload);
            window.removeEventListener('keydown', keydown);
        };
    }, []);
}
