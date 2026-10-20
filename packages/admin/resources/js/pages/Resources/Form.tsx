import * as React from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Trash2 } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import { useConfirm } from '@/components/resources/confirm';
import type { FieldValue } from '@/components/resources/fields';
import { FormSections } from '@/components/resources/form-sections';
import { InfoCards } from '@/components/resources/info-cards';
import { ResourceKeyContext } from '@/components/resources/resource-context';
import type { InfoCard, ResourceInfo, SectionInfo } from '@/components/resources/types';
import { Button } from '@/components/ui/button';
import { adminUrl } from '@/lib/admin-url';
import { withSlugs } from '@/lib/slug-from';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

interface Props {
    resource: ResourceInfo;
    record: { id: string | number; title: string } | null;
    sections: SectionInfo[];
    values: Record<string, FieldValue>;
    can: { delete: boolean };
    /** Read-only cards beside the form (Resource::aside()). */
    aside?: InfoCard[];
}

export default function ResourceForm({ resource, record, sections, values, can, aside = [] }: Props) {
    const base = adminUrl(resource.key);
    const title = record ? record.title : `New ${resource.singularLabel.toLowerCase()}`;
    useBreadcrumbs([{ label: resource.label, href: base }, { label: title }]);
    const [confirm, confirmDialog] = useConfirm();
    const form = useForm<Record<string, FieldValue>>(values);
    // Creating: fields filled from another one (slugFrom) follow it until typed in.
    const typed = React.useRef(new Set<string>());
    const fields = React.useMemo(() => sections.flatMap((section) => section.fields), [sections]);
    const change = (name: string, value: FieldValue) => {
        if (record) {
            form.setData(name, value);
            return;
        }
        if (fields.some((field) => field.name === name && field.slugFrom)) typed.current.add(name);
        form.setData((data) => withSlugs(fields, data, name, value, typed.current) as Record<string, FieldValue>);
    };

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

    const page = (
        <form onSubmit={submit} className="flex max-w-5xl min-w-0 flex-1 flex-col gap-6">
            <Head title={title} />
            {confirmDialog}

            <PageHeader
                title={title}
                back={{ href: base, label: `Back to ${resource.label.toLowerCase()}` }}
                actions={
                    <>
                        {can.delete && (
                            <Button type="button" variant="outline" size="icon" className="text-destructive" aria-label={`Delete ${title}`} onClick={remove}>
                                <Trash2 />
                            </Button>
                        )}
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <LoaderCircle className="animate-spin" />}
                            {record ? 'Save' : `Create ${resource.singularLabel.toLowerCase()}`}
                        </Button>
                    </>
                }
            />

            <ResourceKeyContext value={resource.key}>
                <FormSections sections={sections} data={form.data} errors={form.errors} onChange={change} />
            </ResourceKeyContext>
        </form>
    );
    if (aside.length === 0) return page;

    return (
        <div className="flex flex-col gap-6 xl:flex-row xl:items-start">
            {page}
            {/* xl:mt-16: level with the first card, below the page header (h-10 + gap-6). */}
            <InfoCards cards={aside} className="xl:sticky xl:top-24 xl:mt-16 xl:w-80 xl:shrink-0" />
        </div>
    );
}
