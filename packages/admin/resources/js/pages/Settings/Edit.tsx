import * as React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import type { FieldValue } from '@/components/resources/fields';
import { FormSections } from '@/components/resources/form-sections';
import { InfoCards } from '@/components/resources/info-cards';
import type { InfoCard, SectionInfo } from '@/components/resources/types';
import { Button } from '@/components/ui/button';
import { adminUrl } from '@/lib/admin-url';
import { savedValues } from '@/lib/settings-form';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';
import { useScrollFade } from '@/hooks/use-scroll-fade';
import { cn } from '@/lib/utils';

interface Props {
    section: { key: string; label: string; description: string | null };
    sections: { key: string; label: string }[];
    form: SectionInfo[];
    values: Record<string, FieldValue>;
    /** Secret fields: whether a value is saved (the form never shows it). */
    secrets: Record<string, boolean>;
    /** Read-only cards under the form (Settings::cards()). */
    cards?: InfoCard[];
}

/** Settings: the sections the user may edit, and the current one's form. */
export default function SettingsEdit(props: Props) {
    // A fresh form for each section.
    return <SettingsForm key={props.section.key} {...props} />;
}

function SettingsForm({ section, sections, form: schema, values, secrets, cards = [] }: Props) {
    useBreadcrumbs([{ label: 'Settings', href: adminUrl('settings') }, { label: section.label }]);
    const form = useForm<Record<string, FieldValue>>(values);

    // A saved secret may stay empty: say so instead of asking for it.
    const shown = React.useMemo(() => schema.map((s) => ({
        ...s,
        fields: s.fields.map((field) => (secrets[field.name]
            ? { ...field, required: false, placeholder: 'Saved. Leave empty to keep it.' }
            : field)),
    })), [schema, secrets]);

    const save = () => {
        if (form.processing) return;
        form.put(adminUrl(`settings/${section.key}`), {
            preserveScroll: true,
            // Start again from what was saved (typed secrets cleared): the new baseline.
            // (Not form.reset(...keys): with no secrets that resets every field to the old values.)
            onSuccess: (page) => {
                const saved = savedValues(page.props as Record<string, unknown>, form.data);
                form.setDefaults(saved);
                form.setData(saved);
            },
        });
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        save();
    };
    useUnsavedChanges(form.isDirty, save);
    const tabs = useScrollFade<HTMLElement>('x', '[aria-current="page"]', section.key);
    // A section of cards only (Settings → Mail) has nothing to save.
    const editable = schema.some((s) => s.fields.length > 0);

    return (
        <div className="flex flex-col gap-6 lg:flex-row">
            <Head title={`${section.label} settings`} />
            {sections.length > 1 && (
                <nav ref={tabs} aria-label="Settings sections" className="sunrice-scroll-fade-x -mx-1 flex shrink-0 snap-x snap-proximity scroll-px-1 gap-1 overflow-x-auto px-1 [scrollbar-width:none] lg:mx-0 lg:w-48 lg:flex-col lg:px-0">
                    {sections.map((s) => (
                        <Link key={s.key} href={adminUrl(`settings/${s.key}`)} aria-current={s.key === section.key ? 'page' : undefined}
                            className={cn('shrink-0 snap-start rounded-md px-3 py-2 text-sm whitespace-nowrap hover:bg-muted', s.key === section.key && 'bg-muted font-medium')}>
                            {s.label}
                        </Link>
                    ))}
                </nav>
            )}
            <form onSubmit={submit} className="flex max-w-3xl min-w-0 flex-1 flex-col gap-6">
                <PageHeader
                    title={section.label}
                    description={section.description}
                    actions={editable && (
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <LoaderCircle className="animate-spin" />}
                            Save
                        </Button>
                    )}
                />
                {editable && <FormSections sections={shown} data={form.data} errors={form.errors} onChange={(name, value) => form.setData(name, value)} />}
                <InfoCards cards={cards} />
            </form>
        </div>
    );
}
