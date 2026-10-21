import * as React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import type { FieldValue } from '@/components/resources/fields';
import { FormSections } from '@/components/resources/form-sections';
import type { FieldInfo, SectionInfo } from '@/components/resources/types';
import { Button } from '@/components/ui/button';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

interface Props {
    values: { name: string | null; email: string | null };
}

const field = (name: string, label: string, type: string, extra: Partial<FieldInfo> = {}): FieldInfo => ({
    kind: 'field', type, name, label, required: false, disabled: false, help: null, placeholder: null, span: 1, ...extra,
});

const sections = (): SectionInfo[] => [
    {
        kind: 'section', title: t('Your account'), description: null, columns: 2,
        fields: [field('name', t('Name'), 'text', { required: true }), field('email', t('Email'), 'text', { required: true, inputType: 'email' })],
    },
    {
        kind: 'section', title: t('Password'), description: t('Leave it empty to keep your current password.'), columns: 1,
        fields: [
            field('password', t('New password'), 'password', { confirmed: true }),
            field('current_password', t('Current password'), 'password', { help: t('Needed to change your email address or password.') }),
        ],
    },
];

/** Your account: the signed-in user's own name, email address and password. */
export default function AccountEdit({ values }: Props) {
    useBreadcrumbs([{ label: t('Your account') }]);
    const initial = { name: values.name ?? '', email: values.email ?? '', password: '', password_confirmation: '', current_password: '' };
    const form = useForm<Record<string, FieldValue>>(initial);

    const save = () => {
        if (form.processing) return;
        form.put(adminUrl('account'), {
            preserveScroll: true,
            // Passwords are never kept on screen; the saved name and email are the new baseline.
            onSuccess: () => {
                const saved = { ...form.data, password: '', password_confirmation: '', current_password: '' };
                form.setDefaults(saved);
                form.setData(saved);
            },
        });
    };
    useUnsavedChanges(form.isDirty, save);

    return (
        <form onSubmit={(event) => { event.preventDefault(); save(); }} className="flex max-w-3xl min-w-0 flex-col gap-6">
            <Head title={t('Your account')} />
            <PageHeader
                title={t('Your account')}
                description={t('Your name, email address and password. Your roles are changed by a site manager.')}
                actions={(
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        {t('Save')}
                    </Button>
                )}
            />
            <FormSections sections={sections()} data={form.data} errors={form.errors} onChange={(name, value) => form.setData(name, value)} />
        </form>
    );
}
