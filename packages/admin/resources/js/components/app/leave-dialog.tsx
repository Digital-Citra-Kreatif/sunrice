import * as React from 'react';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { isAskingToLeave, leave, stay, subscribeLeave } from '@/lib/leave-confirm';

/** The panel's "Leave without saving?" (useUnsavedChanges), rendered once in the admin layout. */
export function LeaveDialog() {
    const open = React.useSyncExternalStore(subscribeLeave, isAskingToLeave, () => false);

    return (
        <AlertDialog open={open} onOpenChange={(next: boolean) => { if (!next) stay(); }}>
            <AlertDialogContent onCloseAutoFocus={(event: Event) => {
                // Not back onto the link that was clicked: it would look like the current page.
                event.preventDefault();
                if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
            }}>
                <AlertDialogHeader>
                    <AlertDialogTitle>Leave without saving?</AlertDialogTitle>
                    <AlertDialogDescription>You have unsaved changes on this page. If you leave, they're lost.</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel onClick={stay}>Stay</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" onClick={leave}>Leave without saving</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
