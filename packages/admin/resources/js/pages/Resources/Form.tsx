import * as React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Trash2 } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { useConfirm } from '@/components/resources/confirm';
import type { FieldValue } from '@/components/resources/fields';
import { FormSections } from '@/components/resources/form-sections';
import type { ResourceInfo, SectionInfo } from '@/components/resources/types';
import { Button } from '@/components/ui/button';
import { adminUrl } from '@/lib/admin-url';

interface Props {
    resource: ResourceInfo;
    record: { id: string | number; title: string } | null;
    sections: SectionInfo[];
    values: Record<string, FieldValue>;
    can: { delete: boolean };
}

export default function ResourceForm({ resource, record, sections, values, can }: Props) {
    const base = adminUrl(resource.key);
    const title = record ? record.title : `New ${resource.singularLabel.toLowerCase()}`;
    useBreadcrumbs([{ label: resource.label, href: base }, { label: title }]);
    const [confirm, confirmDialog] = useConfirm();
    const form = useForm<Record<string, FieldValue>>(values);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (record) {
            form.put(`${base}/${record.id}`, { preserveScroll: true });
        } else {
            form.post(base);
        }
    };

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

            <FormSections sections={sections} data={form.data} errors={form.errors} onChange={(name, value) => form.setData(name, value)} />
        </form>
    );
}
