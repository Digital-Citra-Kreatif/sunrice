import * as React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, TriangleAlert } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import { SettingsNav, type SettingsNavEntry } from '@/components/app/settings-nav';
import type { FieldValue } from '@/components/resources/fields';
import { FormSections } from '@/components/resources/form-sections';
import { InfoCards } from '@/components/resources/info-cards';
import type { InfoCard, SectionInfo } from '@/components/resources/types';
import { Button } from '@/components/ui/button';
import { adminUrl } from '@/lib/admin-url';
import { savedValues } from '@/lib/settings-form';
import { t } from '@/lib/i18n';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

interface Props {
    section: { key: string; label: string; description: string | null; warning?: string | null };
    /** The Settings menu: the sections and the pages among them (Panel::settingsNavigation()). */
    sections: SettingsNavEntry[];
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
    useBreadcrumbs([{ label: t('Settings'), href: adminUrl('settings') }, { label: section.label }]);
    const form = useForm<Record<string, FieldValue>>(values);

    // A saved secret may stay empty: say so instead of asking for it.
    const shown = React.useMemo(() => schema.map((s) => ({
        ...s,
        fields: s.fields.map((field) => (secrets[field.name]
            ? { ...field, required: false, placeholder: t('Saved. Leave empty to keep it.') }
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
    // A section of cards only (Settings → Mail) has nothing to save.
    const editable = schema.some((s) => s.fields.length > 0);

    return (
        <div className="flex flex-col gap-6 lg:flex-row">
            <Head title={t(':section settings', { section: section.label })} />
            <SettingsNav items={sections} current={section.key} />
            <form onSubmit={submit} className="flex max-w-5xl min-w-0 flex-1 flex-col gap-6">
                <PageHeader
                    title={section.label}
                    description={section.description}
                    actions={editable && (
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <LoaderCircle className="animate-spin" />}
                            {t('Save')}
                        </Button>
                    )}
                />
                {section.warning && (
                    // Settings::warning(): what can go wrong here, before anything is changed.
                    <div role="note" className="flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm leading-relaxed whitespace-pre-line text-amber-900 dark:text-amber-200">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                        <p>{section.warning}</p>
                    </div>
                )}
                {editable && <FormSections sections={shown} data={form.data} errors={form.errors} storageKey={`sunrice:settings:${section.key}`} onChange={(name, value) => form.setData(name, value)} />}
                <InfoCards cards={cards} />
            </form>
        </div>
    );
}
