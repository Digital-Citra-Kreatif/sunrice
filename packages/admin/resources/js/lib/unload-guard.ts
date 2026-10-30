/** What the guard reads of an Inertia visit. */
export interface GuardedVisit {
    method: string;
    completed?: boolean;
    cancelled?: boolean;
    interrupted?: boolean;
}

/**
 * Whether closing or reloading the tab should warn about unsaved edits.
 * Not while a save (a non-GET visit) is under way or has just succeeded:
 * the app itself may then reload the page (a settings save that answers
 * with a full reload, like Branding's, so its fonts and colours apply).
 * A refused, cancelled or interrupted save, or the next page shown, ends that.
 *
 * Nor once the person has chosen "Leave without saving": the visit they
 * confirmed can still end in a full page load (Inertia reloads when the
 * admin's assets changed since the page opened), which would otherwise ask
 * them a second time in the browser's own dialog.
 */
export function createUnloadGuard() {
    let saving = false;
    let leaving = false;

    return {
        start(visit: GuardedVisit): void {
            if (visit.method.toLowerCase() !== 'get') saving = true;
        },
        /** The person chose to leave without saving. */
        leave(): void {
            leaving = true;
        },
        error(): void {
            saving = false;
            leaving = false;
        },
        finish(visit: GuardedVisit): void {
            if (visit.completed === false || visit.cancelled || visit.interrupted) saving = false;
            // Cancelled or interrupted: still here, so the edits are guarded again.
            if (visit.cancelled || visit.interrupted) leaving = false;
        },
        navigate(): void {
            saving = false;
            leaving = false;
        },
        shouldWarn(dirty: boolean): boolean {
            return dirty && !saving && !leaving;
        },
    };
}
