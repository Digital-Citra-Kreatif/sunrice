import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { InputError } from '@/components/app/input-error';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button, buttonVariants } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { FieldInput, type FieldValue } from './fields';
import type { FieldInfo } from './types';
import { t } from '@/lib/i18n';

/** What a dialog with fields runs: resolves to whether it succeeded (the dialog stays open otherwise). */
export type ConfirmRun = (values: Record<string, FieldValue>) => void | Promise<boolean>;

interface Pending {
    question: string;
    destructive: boolean;
    fields: FieldInfo[];
    run: ConfirmRun;
}

/**
 * An action's dialog fields for the records it runs on: a select with
 * `withoutTargets` (Select::withoutTargets()) leaves out their keys, so a
 * Delete dialog doesn't offer the users being deleted as "Give it to".
 */
export function forTargets(fields: FieldInfo[], keys: (string | number)[]): FieldInfo[] {
    const targets = keys.map(String);

    return fields.map((field) => (field.withoutTargets === true && Array.isArray(field.options)
        ? { ...field, options: (field.options as { value: string }[]).filter((option) => !targets.includes(String(option.value))) }
        : field));
}

/**
 * A confirm() that looks like the admin: `const [confirm, dialog] = useConfirm()`.
 * With fields, the dialog asks for them and gives their values to `run`;
 * it stays open while `run` resolves to false, showing the page's errors.
 */
export function useConfirm(): [(question: string | null, run: ConfirmRun, destructive?: boolean, fields?: FieldInfo[]) => void, React.ReactNode] {
    const [pending, setPending] = React.useState<Pending | null>(null);
    const [values, setValues] = React.useState<Record<string, FieldValue>>({});
    const [busy, setBusy] = React.useState(false);
    const errors = (usePage().props as { errors?: Record<string, string> }).errors ?? {};

    const confirm = React.useCallback((question: string | null, run: ConfirmRun, destructive = false, fields: FieldInfo[] = []) => {
        if (question === null && fields.length === 0) {
            void run({});
        } else {
            setValues(Object.fromEntries(fields.map((field) => [field.name, (field.default as FieldValue | undefined) ?? null])));
            setPending({ question: question ?? t('Are you sure?'), run, destructive, fields });
        }
    }, []);

    const submit = async () => {
        if (!pending) return;
        const result = pending.run(values);
        if (result instanceof Promise) {
            setBusy(true);
            const done = await result;
            setBusy(false);
            if (done) setPending(null);
        } else {
            setPending(null);
        }
    };

    const dialog = (
        <AlertDialog open={pending !== null} onOpenChange={(open) => !open && !busy && setPending(null)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{t('Are you sure?')}</AlertDialogTitle>
                    <AlertDialogDescription>{pending?.question}</AlertDialogDescription>
                </AlertDialogHeader>
                {pending && pending.fields.length > 0 && (
                    <div className="grid gap-4">
                        {pending.fields.map((field) => (
                            <div key={field.name} className="grid gap-2">
                                <Label htmlFor={`field-${field.name}`}>
                                    {field.label}
                                    {field.required && <span className="text-destructive" aria-hidden>*</span>}
                                </Label>
                                <FieldInput field={field} value={values[field.name] ?? null} invalid={!!errors[field.name]}
                                    onChange={(value) => setValues((current) => ({ ...current, [field.name]: value }))} />
                                {field.help && <p className="text-sm text-muted-foreground">{field.help}</p>}
                                <InputError message={errors[field.name]} />
                            </div>
                        ))}
                    </div>
                )}
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={busy}>{t('Cancel')}</AlertDialogCancel>
                    {pending && pending.fields.length > 0 ? (
                        <Button variant={pending.destructive ? 'destructive' : 'default'} disabled={busy} onClick={() => void submit()}>{t('Continue')}</Button>
                    ) : (
                        <AlertDialogAction
                            className={pending?.destructive ? buttonVariants({ variant: 'destructive' }) : undefined}
                            onClick={() => void submit()}
                        >
                            {t('Continue')}
                        </AlertDialogAction>
                    )}
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );

    return [confirm, dialog];
}
