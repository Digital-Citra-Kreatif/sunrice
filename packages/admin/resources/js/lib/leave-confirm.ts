/*
 * "Leave without saving?" in the panel's own dialog instead of the
 * browser's confirm(): useUnsavedChanges() stops the visit and asks here;
 * the dialog (components/app/leave-dialog.tsx, in the admin layout) calls
 * leave() to go on, or stay() to keep editing.
 */

let pending: (() => void) | null = null;
const listeners = new Set<() => void>();
const notify = () => listeners.forEach((listener) => listener());

/** Asks before a visit; `go` makes it when the person chooses to leave. */
export function askToLeave(go: () => void): void {
    pending = go;
    notify();
}

/** Whether the dialog is asking. */
export function isAskingToLeave(): boolean {
    return pending !== null;
}

export function subscribeLeave(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** Leave without saving: the stopped visit goes ahead. */
export function leave(): void {
    const go = pending;
    pending = null;
    notify();
    go?.();
}

/** Stay on the page, the edits as they are. */
export function stay(): void {
    pending = null;
    notify();
}
