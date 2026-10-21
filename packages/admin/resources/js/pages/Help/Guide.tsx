import { Head, router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { HelpReader, type HelpPageProps } from '@/components/app/help-reader';
import { PageHeader } from '@/components/app/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';

interface Props extends HelpPageProps {
    language: string;
    languages: { code: string; label: string }[];
    /** The page isn't written in the reader's language and is shown in this one. */
    shownIn: string | null;
}

/** Help → Guide: the user guide in the reader's language (Panel::guide()), remembered for the next pages. */
export default function HelpGuide({ language, languages, shownIn, ...props }: Props) {
    const text = { title: t('Guide'), intro: t('How to work in the panel: content, media, settings and your team.') };
    const labels = { pages: t('Guide pages'), onThisPage: t('On this page'), previous: t('Previous'), next: t('Next'), empty: t('No guide is installed.') };
    const base = adminUrl('guide');
    useBreadcrumbs([{ label: t('Help') }, { label: text.title, href: base }, ...(props.title ? [{ label: props.title }] : [])]);
    const shownLabel = languages.find((l) => l.code === shownIn)?.label ?? shownIn;

    return (
        <>
            <Head title={props.title ? `${props.title} · ${text.title}` : text.title} />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title={text.title}
                    description={text.intro}
                    actions={languages.length > 1 && (
                        <Select value={language} onValueChange={(lang) => router.visit(`${props.page ? `${base}/${props.page}` : base}?lang=${encodeURIComponent(lang)}`, { preserveScroll: true })}>
                            <SelectTrigger className="w-full sm:w-52" aria-label={t('Guide language')}>
                                <Languages className="size-4 text-muted-foreground" />
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {languages.map((l) => <SelectItem key={l.code} value={l.code}>{l.label}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    )}
                />
                <HelpReader
                    kind="guide"
                    {...props}
                    labels={labels}
                    notice={shownIn && (
                        <p className="mb-6 rounded-lg bg-accent-tint px-4 py-2.5 text-sm text-muted-foreground" role="note">{t('This page isn’t written in your language yet, so it’s shown in :language.', { language: shownLabel ?? '' })}</p>
                    )}
                />
            </div>
        </>
    );
}
