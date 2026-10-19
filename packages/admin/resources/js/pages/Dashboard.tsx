import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

type Frame = {
    key: string;
    title: string;
    description: string | null;
    type: 'stats' | 'list';
    span: number;
    link: { label: string; url: string } | null;
};
type Stat = { label: string; value: string; url: string | null };
type Item = { title: string; meta: string | null; url: string | null };
type Content = { items: Array<Stat | Item>; empty: string };

type Props = SharedProps & { cards: Frame[]; content?: Record<string, Content> };

const spans: Record<number, string> = { 1: '', 2: 'md:col-span-2', 3: 'md:col-span-2 xl:col-span-3' };

/** An address on the panel's site opens without a reload; others as links. */
function To({ url, className, children }: { url: string | null; className?: string; children: ReactNode }) {
    if (url === null) {
        return <div className={className}>{children}</div>;
    }

    return url.startsWith('/') ? (
        <Link href={url} className={className}>
            {children}
        </Link>
    ) : (
        <a href={url} className={className} rel="noreferrer">
            {children}
        </a>
    );
}

function Stats({ items }: { items: Stat[] }) {
    return (
        <div className="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-3">
            {items.map((stat, index) => (
                <To key={index} url={stat.url} className={cn('rounded-md border p-3', stat.url && 'hover:bg-muted/60')}>
                    <div className="text-2xl font-semibold tabular-nums">{stat.value}</div>
                    <div className="mt-0.5 text-sm leading-snug text-muted-foreground">{stat.label}</div>
                </To>
            ))}
        </div>
    );
}

function List({ items }: { items: Item[] }) {
    return (
        <ul className="-mx-2 divide-y">
            {items.map((item, index) => (
                <li key={index}>
                    <To url={item.url} className={cn('flex items-baseline justify-between gap-3 rounded-md px-2 py-2', item.url && 'hover:bg-muted/60')}>
                        <span className="min-w-0 truncate text-sm">{item.title}</span>
                        {item.meta && <span className="shrink-0 text-xs text-muted-foreground">{item.meta}</span>}
                    </To>
                </li>
            ))}
        </ul>
    );
}

function Loading({ type }: { type: Frame['type'] }) {
    return type === 'stats' ? (
        <div className="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-3">
            {[0, 1, 2].map((i) => (
                <Skeleton key={i} className="h-[4.5rem]" />
            ))}
        </div>
    ) : (
        <div className="space-y-3">
            {[0, 1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-5" />
            ))}
        </div>
    );
}

function DashboardCard({ frame, content }: { frame: Frame; content: Content | undefined }) {
    return (
        <Card className={cn('gap-4', spans[frame.span])}>
            <CardHeader>
                <CardTitle>{frame.title}</CardTitle>
                {frame.description && <CardDescription>{frame.description}</CardDescription>}
            </CardHeader>
            <CardContent>
                {content === undefined ? (
                    <Loading type={frame.type} />
                ) : content.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{content.empty}</p>
                ) : frame.type === 'stats' ? (
                    <Stats items={content.items as Stat[]} />
                ) : (
                    <List items={content.items as Item[]} />
                )}
            </CardContent>
            {frame.link && (
                <CardFooter className="mt-auto">
                    <To url={frame.link.url} className="text-sm font-medium text-primary hover:underline">
                        {frame.link.label} →
                    </To>
                </CardFooter>
            )}
        </Card>
    );
}

export default function Dashboard() {
    const { auth, sunrice, cards, content } = usePage<Props>().props;
    useBreadcrumbs([{ label: 'Dashboard' }]);

    return (
        <>
            <Head title="Dashboard" />
            <h1 className="text-2xl font-medium tracking-tight">Welcome back{auth.user?.name ? `, ${auth.user.name}` : ''}</h1>
            <p className="mt-1 text-muted-foreground">Here is what's happening in {sunrice.brand}.</p>
            {cards.length > 0 && (
                <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Deferred data="content" fallback={cards.map((frame) => <DashboardCard key={frame.key} frame={frame} content={undefined} />)}>
                        {cards.map((frame) => (
                            <DashboardCard key={frame.key} frame={frame} content={content?.[frame.key]} />
                        ))}
                    </Deferred>
                </div>
            )}
        </>
    );
}
