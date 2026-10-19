import * as React from 'react';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';

interface Pending {
    question: string;
    destructive: boolean;
    run: () => void;
}

/** A confirm() that looks like the admin: `const [confirm, dialog] = useConfirm()`. */
export function useConfirm(): [(question: string | null, run: () => void, destructive?: boolean) => void, React.ReactNode] {
    const [pending, setPending] = React.useState<Pending | null>(null);

    const confirm = React.useCallback((question: string | null, run: () => void, destructive = false) => {
        if (question === null) {
            run();
        } else {
            setPending({ question, run, destructive });
        }
    }, []);

    const dialog = (
        <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                    <AlertDialogDescription>{pending?.question}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        className={pending?.destructive ? buttonVariants({ variant: 'destructive' }) : undefined}
                        onClick={() => {
                            pending?.run();
                            setPending(null);
                        }}
                    >
                        Continue
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );

    return [confirm, dialog];
}
