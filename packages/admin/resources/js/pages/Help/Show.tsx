import { Head, Link } from '@inertiajs/react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { Card, CardContent } from '@/components/ui/card';
import { adminUrl } from '@/lib/admin-url';
import { cn } from '@/lib/utils';

interface Section {
    key: string;
    title: string;
    pages: { slug: string; title: string }[];
}

interface Props {
    sections: Section[];
    section: string;
    page: string;
    /** The page, from the guide's Markdown (no HTML of its own). */
    html: string;
}

export default function HelpShow({ sections, section, page, html }: Props) {
    const title = sections.find((s) => s.key === section)?.pages.find((p) => p.slug === page)?.title ?? 'Help';
    useBreadcrumbs([{ label: 'Help', href: adminUrl('help') }, { label: title }]);

    return (
        <div className="grid gap-6 lg:grid-cols-[14rem_1fr]">
            <Head title={title} />
            <nav aria-label="Help pages" className="flex flex-col gap-5">
                {sections.map((s) => (
                    <div key={s.key}>
                        <p className="mb-1.5 px-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">{s.title}</p>
                        <ul className="grid gap-0.5">
                            {s.pages.map((p) => (
                                <li key={p.slug}>
                                    <Link href={adminUrl(`help/${s.key}/${p.slug}`)}
                                        className={cn('block rounded-md px-2 py-1.5 text-sm hover:bg-muted', s.key === section && p.slug === page && 'bg-muted font-medium')}>
                                        {p.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>
            <Card className="min-w-0">
                <CardContent>
                    <article className="help-article max-w-prose" dangerouslySetInnerHTML={{ __html: html }} />
                </CardContent>
            </Card>
        </div>
    );
}
