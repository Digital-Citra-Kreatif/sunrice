import * as React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Trash2 } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { useConfirm } from '@/components/resources/confirm';
import type { FieldValue } from '@/components/resources/fields';
import { FormSections } from '@/components/resources/form-sections';
import type { ResourceInfo, SectionInfo } from '@/components/resources/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { adminUrl } from '@/lib/admin-url';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

interface Props {
    resource: ResourceInfo;
    record: { id: string | number; title: string } | null;
    sections: SectionInfo[];
    values: Record<string, FieldValue>;
    /** Facts beside the form, when editing. */
    aside: { label: string; value: string }[];
    can: { delete: boolean };
}

export default function ResourceForm({ resource, record, sections, values, aside, can }: Props) {
    const base = adminUrl(resource.key);
    const title = record ? record.title : `New ${resource.singularLabel.toLowerCase()}`;
    useBreadcrumbs([{ label: resource.label, href: base }, { label: title }]);
    const [confirm, confirmDialog] = useConfirm();
    const form = useForm<Record<string, FieldValue>>(values);

    const save = () => {
        if (form.processing) return;
        if (record) {
            // Saved: what's on screen is the new baseline for "unsaved changes".
            form.put(`${base}/${record.id}`, { preserveScroll: true, onSuccess: () => form.setDefaults() });
        } else {
            form.post(base, { onSuccess: () => form.setDefaults() });
        }
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        save();
    };
    useUnsavedChanges(form.isDirty, save);

    const remove = () => record && confirm(`Delete ${record.title}? This can't be undone.`, () => router.delete(`${base}/${record.id}`), true);

    return (
        <form onSubmit={submit} className="flex max-w-3xl flex-col gap-6">
            <Head title={title} />
            {confirmDialog}

            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex min-w-0 items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={base} aria-label={`Back to ${resource.label.toLowerCase()}`}><ArrowLeft /></Link>
                    </Button>
                    <h1 className="truncate text-2xl font-medium tracking-tight">{title}</h1>
                </div>
                <div className="flex items-center gap-2">
                    {can.delete && (
                        <Button type="button" variant="outline" size="icon" className="text-destructive" aria-label={`Delete ${title}`} onClick={remove}>
                            <Trash2 />
                        </Button>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        {record ? 'Save' : `Create ${resource.singularLabel.toLowerCase()}`}
                    </Button>
                </div>
            </div>

            {aside.length > 0 && (
                <Card className="py-4">
                    <CardContent>
                        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            {aside.map((fact) => (
                                <div key={fact.label} className="min-w-0">
                                    <dt className="text-xs text-muted-foreground">{fact.label}</dt>
                                    <dd className="mt-0.5 text-sm break-words">{fact.value}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
            )}

            <FormSections sections={sections} data={form.data} errors={form.errors} onChange={(name, value) => form.setData(name, value)} />
        </form>
    );
}
