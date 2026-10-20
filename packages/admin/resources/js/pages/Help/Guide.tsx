import { Head, router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { HelpReader, type HelpPageProps } from '@/components/app/help-reader';
import { PageHeader } from '@/components/app/page-header';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { adminUrl } from '@/lib/admin-url';

interface Props extends HelpPageProps {
    language: string;
    languages: { code: string; label: string }[];
    /** The page isn't written in the reader's language and is shown in this one. */
    shownIn: string | null;
}

interface Text { title: string; intro: string; pages: string; onThisPage: string; previous: string; next: string; language: string; empty: string; shownIn: string }

const ENGLISH: Text = {
    title: 'Guide', intro: 'How to work in the panel: content, media, settings and your team.', pages: 'Guide pages', onThisPage: 'On this page',
    previous: 'Previous', next: 'Next', language: 'Guide language', empty: 'No guide is installed.', shownIn: 'This page isn’t written in your language yet, so it’s shown in',
};

const TEXT: Record<string, Text> = {
    en: ENGLISH,
    id: {
        title: 'Panduan', intro: 'Cara bekerja di panel: konten, media, pengaturan, dan tim Anda.', pages: 'Halaman panduan', onThisPage: 'Di halaman ini',
        previous: 'Sebelumnya', next: 'Berikutnya', language: 'Bahasa panduan', empty: 'Belum ada panduan yang terpasang.', shownIn: 'Halaman ini belum tersedia dalam bahasa Anda, jadi ditampilkan dalam',
    },
};

/** Help → Guide: the user guide in the reader's language (Panel::guide()), remembered for the next pages. */
export default function HelpGuide({ language, languages, shownIn, ...props }: Props) {
    const text = TEXT[language] ?? ENGLISH;
    const base = adminUrl('guide');
    useBreadcrumbs([{ label: 'Help' }, { label: text.title, href: base }, ...(props.title ? [{ label: props.title }] : [])]);
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
                            <SelectTrigger className="w-full sm:w-52" aria-label={text.language}>
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
                    labels={text}
                    notice={shownIn && (
                        <p className="mb-6 rounded-lg bg-accent-tint px-4 py-2.5 text-sm text-muted-foreground" role="note">{text.shownIn} {shownLabel}.</p>
                    )}
                />
            </div>
        </>
    );
}
