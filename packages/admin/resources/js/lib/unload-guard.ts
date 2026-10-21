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
 */
export function createUnloadGuard() {
    let saving = false;

    return {
        start(visit: GuardedVisit): void {
            if (visit.method.toLowerCase() !== 'get') saving = true;
        },
        error(): void {
            saving = false;
        },
        finish(visit: GuardedVisit): void {
            if (visit.completed === false || visit.cancelled || visit.interrupted) saving = false;
        },
        navigate(): void {
            saving = false;
        },
        shouldWarn(dirty: boolean): boolean {
            return dirty && !saving;
        },
    };
}
