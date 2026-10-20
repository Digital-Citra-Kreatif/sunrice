import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { adminUrl } from '@/lib/admin-url';
import { currentHeading, helpVisit, type HelpHeading } from '@/lib/help';
import { useScrollFade } from '@/hooks/use-scroll-fade';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

export interface HelpPageLink {
    page: string;
    title: string;
}

export interface HelpSection {
    key: string;
    title: string;
    pages: HelpPageLink[];
}

export interface HelpPageProps {
    page: string | null;
    title: string | null;
    html: string;
    headings: HelpHeading[];
    sections: HelpSection[];
    previous: HelpPageLink | null;
    next: HelpPageLink | null;
}

interface Props extends HelpPageProps {
    /** "docs" or "guide": pages live at adminUrl(`${kind}/${page}`). */
    kind: 'docs' | 'guide';
    labels: { pages: string; onThisPage: string; previous: string; next: string; empty: string };
    /** Above the page's title: the language note. */
    notice?: React.ReactNode;
}

const eyebrow = 'px-2 text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase';

/**
 * A set of Markdown pages (the developer docs, the user guide): the page
 * list, the page (HTML rendered and sanitised by the server) and its
 * "On this page" headings, which follow the reading.
 */
export function HelpReader({ kind, page, title, html, headings, sections, previous, next, labels, notice }: Props) {
    const { sunrice } = usePage<SharedProps>().props;
    const [active, setActive] = React.useState<string | null>(headings[0]?.id ?? null);
    const href = (target: string) => adminUrl(`${kind}/${target}`);
    const pagesNav = useScrollFade<HTMLElement>('y', '[aria-current="page"]', page);
    const headingsNav = useScrollFade<HTMLElement>('y', undefined, page);

    // Links to other help pages are plain <a> tags in the HTML: visit them through Inertia.
    const onClick = (event: React.MouseEvent) => {
        const anchor = (event.target as HTMLElement).closest('a');
        if (!anchor || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || anchor.target === '_blank') return;
        const visit = helpVisit(anchor.href, window.location.href, sunrice.path);
        if (visit === null) return;
        event.preventDefault();
        router.visit(visit);
    };

    // Land on the anchor after visiting "page#section", else at the top.
    React.useEffect(() => {
        const id = decodeURIComponent(window.location.hash.slice(1));
        if (id) document.getElementById(id)?.scrollIntoView();
        else window.scrollTo(0, 0);
    }, [page]);

    // "On this page" marks the heading being read.
    React.useEffect(() => {
        if (headings.length === 0) return;
        let frame = 0;
        const update = () => {
            frame = 0;
            const tops = headings.flatMap((h) => {
                const element = document.getElementById(h.id);
                return element ? [{ id: h.id, top: element.getBoundingClientRect().top }] : [];
            });
            setActive(currentHeading(tops));
        };
        const onScroll = () => {
            if (frame === 0) frame = window.requestAnimationFrame(update);
        };
        update();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => {
            window.removeEventListener('scroll', onScroll);
            if (frame !== 0) window.cancelAnimationFrame(frame);
        };
    }, [headings]);

    if (page === null) {
        return <p className="rounded-xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">{labels.empty}</p>;
    }

    return (
        <div className="grid items-start gap-8 lg:grid-cols-[13rem_minmax(0,1fr)] xl:grid-cols-[13rem_minmax(0,1fr)_12rem]">
            {/* Phones: pick a page from a list instead of the long sidebar. */}
            <Select value={page} onValueChange={(target) => router.visit(href(target))}>
                <SelectTrigger className="w-full lg:hidden" aria-label={labels.pages}><SelectValue /></SelectTrigger>
                <SelectContent>
                    {sections.map((section) => (
                        <SelectGroup key={section.key}>
                            <SelectLabel>{section.title}</SelectLabel>
                            {section.pages.map((p) => <SelectItem key={p.page} value={p.page}>{p.title}</SelectItem>)}
                        </SelectGroup>
                    ))}
                </SelectContent>
            </Select>

            <nav ref={pagesNav} aria-label={labels.pages} className="sunrice-scroll-fade-y hidden flex-col gap-6 overscroll-contain lg:sticky lg:top-20 lg:flex lg:max-h-[calc(100svh-7rem)] lg:overflow-y-auto lg:py-1 lg:pb-8">
                {sections.map((section) => (
                    <div key={section.key} className="flex flex-col gap-0.5">
                        <p className={cn(eyebrow, 'mb-1')}>{section.title}</p>
                        {section.pages.map((p) => (
                            <Link
                                key={p.page}
                                href={href(p.page)}
                                preserveScroll={false}
                                className={cn(
                                    'rounded-md px-2 py-1.5 text-sm transition-colors duration-150 ease-out-quart hover:bg-muted/60 hover:text-foreground',
                                    p.page === page ? 'bg-accent-tint font-medium text-foreground' : 'text-muted-foreground',
                                )}
                                aria-current={p.page === page ? 'page' : undefined}
                            >
                                {p.title}
                            </Link>
                        ))}
                    </div>
                ))}
            </nav>

            <div className="min-w-0">
                <article className="rounded-xl bg-card px-5 py-7 shadow-card sm:px-10 sm:py-10">
                    {notice}
                    {title && <h1 className="sunrice-page-title mb-6 text-balance">{title}</h1>}
                    <div className="sunrice-docs" onClick={onClick} dangerouslySetInnerHTML={{ __html: html }} />
                </article>

                {(previous || next) && (
                    <nav aria-label={labels.pages} className="mt-4 grid gap-3 sm:grid-cols-2">
                        {previous ? (
                            <Link href={href(previous.page)} className="group flex flex-col gap-0.5 rounded-lg border bg-card px-4 py-3 transition-shadow duration-200 ease-out-quart hover:shadow-card">
                                <span className="flex items-center gap-1 text-xs text-muted-foreground"><ArrowLeft className="size-3.5 transition-transform group-hover:-translate-x-0.5" />{labels.previous}</span>
                                <span className="text-sm font-medium">{previous.title}</span>
                            </Link>
                        ) : <span />}
                        {next && (
                            <Link href={href(next.page)} className="group flex flex-col items-end gap-0.5 rounded-lg border bg-card px-4 py-3 text-right transition-shadow duration-200 ease-out-quart hover:shadow-card">
                                <span className="flex items-center gap-1 text-xs text-muted-foreground">{labels.next}<ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" /></span>
                                <span className="text-sm font-medium">{next.title}</span>
                            </Link>
                        )}
                    </nav>
                )}
            </div>

            {headings.length > 0 && (
                <nav ref={headingsNav} aria-label={labels.onThisPage} className="sunrice-scroll-fade-y hidden flex-col gap-0.5 overscroll-contain xl:sticky xl:top-20 xl:flex xl:max-h-[calc(100svh-7rem)] xl:overflow-y-auto xl:pb-8">
                    <p className={cn(eyebrow, 'mb-1')}>{labels.onThisPage}</p>
                    {headings.map((h) => (
                        <a
                            key={h.id}
                            href={`#${h.id}`}
                            aria-current={active === h.id ? 'location' : undefined}
                            className={cn(
                                'border-l-2 py-1 pr-2 text-[13px] leading-snug transition-colors duration-150 ease-out-quart hover:text-foreground',
                                h.level === 3 ? 'pl-5' : 'pl-3',
                                active === h.id ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground',
                            )}
                        >
                            {h.text}
                        </a>
                    ))}
                </nav>
            )}
        </div>
    );
}
