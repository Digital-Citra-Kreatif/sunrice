import * as React from 'react';
import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, CircleAlert, Sparkles, TrendingDown, TrendingUp } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { navIcon } from '@/components/app/nav-icon';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { adminUrl } from '@/lib/admin-url';
import {
    formatStat, greeting, isExternal, quickLinks, relativeTime, spanClass, widgetHref,
    type CardsData, type ListData, type ListRow, type StatData, type Tone, type WidgetDefinition, type WidgetResult,
} from '@/lib/dashboard';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Props {
    widgets: WidgetDefinition[];
}

const BADGE: Record<Tone, 'outline' | 'success' | 'warning' | 'destructive'> = { neutral: 'outline', success: 'success', warning: 'warning', danger: 'destructive' };

/** The dashboard: a greeting, then the widgets the panel and its plugins registered, their data arriving after the page. */
export default function Dashboard({ widgets }: Props) {
    const { auth, sunrice, navigation } = usePage<SharedProps>().props;
    useBreadcrumbs([{ label: 'Dashboard' }]);
    const now = React.useMemo(() => new Date(), []);
    const name = auth.user?.name?.trim().split(/\s+/)[0];

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6">
                <section className="sunrice-atmosphere relative isolate overflow-hidden rounded-xl px-6 py-7 shadow-card sm:px-8 sm:py-9">
                    <div aria-hidden="true" className="pointer-events-none absolute inset-y-0 right-0 -z-10 hidden w-80 sm:block">
                        <div className="sunrice-orbit -top-20 -right-16" />
                        <div className="sunrice-orb -top-12 right-10 size-36! opacity-90" />
                        <div className="sunrice-orb right-56 bottom-6 size-7! opacity-80" />
                    </div>
                    <p className="text-xs font-medium tracking-[0.14em] uppercase opacity-60">
                        {sunrice.brand} · <time dateTime={now.toISOString()}>{now.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' })}</time>
                    </p>
                    <h1 className="sunrice-page-title mt-2">{greeting(now)}{name ? `, ${name}` : ''}</h1>
                    <p className="mt-1 max-w-xl text-sm leading-relaxed opacity-70">Here's what's happening.</p>
                    <QuickLinks links={quickLinks(navigation)} />
                </section>

                {widgets.length === 0 ? (
                    <Card className="items-center gap-2 py-14 text-center">
                        <span className="grid size-10 place-items-center rounded-full bg-muted text-muted-foreground"><Sparkles className="size-5" /></span>
                        <p className="text-sm font-medium">Nothing to show yet</p>
                        <p className="max-w-sm text-[13px] text-muted-foreground">Widgets appear here as you get access to content and tools.</p>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {widgets.map((widget, i) => (
                            <div key={widget.key} className={cn('sunrice-row-in min-w-0', spanClass(widget.span))} style={{ '--i': i } as React.CSSProperties}>
                                <Widget widget={widget} now={now} />
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function QuickLinks({ links }: { links: ReturnType<typeof quickLinks> }) {
    if (links.length === 0) return null;

    return (
        <nav aria-label="Quick links" className="mt-6 flex flex-wrap gap-2">
            {links.map((link) => {
                const Icon = navIcon(link.icon);

                return (
                    <Link
                        key={link.key}
                        href={adminUrl(link.url)}
                        prefetch
                        className="inline-flex items-center gap-1.5 rounded-full bg-card/60 px-3 py-1.5 text-[13px] font-medium text-foreground ring-1 ring-border/60 backdrop-blur transition-[background-color,transform] duration-150 ease-out-quart hover:bg-card focus-visible:ring-4 focus-visible:ring-ring-glow focus-visible:outline-none active:scale-[0.98]"
                    >
                        <Icon className="size-3.5 text-muted-foreground" />
                        {link.label}
                    </Link>
                );
            })}
        </nav>
    );
}

/** A link inside the panel (an Inertia visit), outside it, or no link. */
function SmartLink({ url, className, children, ...rest }: { url: string | null; className?: string; children: React.ReactNode; 'aria-label'?: string }) {
    if (url === null) return <div className={className}>{children}</div>;
    const href = widgetHref(url, adminUrl);

    return isExternal(href)
        ? <a href={href} className={className} target="_blank" rel="noopener noreferrer" {...rest}>{children}</a>
        : <Link href={href} className={className} {...rest}>{children}</Link>;
}

function Widget({ widget, now }: { widget: WidgetDefinition; now: Date }) {
    const props = usePage().props as Record<string, unknown>;
    const body = (
        <Deferred data={widget.prop} fallback={<WidgetSkeleton type={widget.type} />} rescue={<Unavailable />}>
            {() => {
                const result = props[widget.prop] as WidgetResult | undefined;
                if (!result || !result.ok) return <Unavailable />;
                if (widget.type === 'stat') return <StatBody widget={widget} data={result.data as StatData} />;
                if (widget.type === 'cards') return <CardsBody widget={widget} data={result.data as CardsData} />;

                return <ListBody widget={widget} data={result.data as ListData} now={now} />;
            }}
        </Deferred>
    );

    if (widget.type === 'stat') {
        return (
            <Card className="group relative h-full gap-3 px-5 py-5">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-[13px] font-medium text-muted-foreground">
                        {widget.link ? (
                            <SmartLink url={widget.link.url} className="after:absolute after:inset-0 after:rounded-lg focus-visible:outline-none">{widget.title}</SmartLink>
                        ) : widget.title}
                    </h2>
                    <WidgetIcon name={widget.icon} />
                </div>
                {body}
                {widget.link && <ArrowUpRight aria-hidden="true" className="absolute right-4 bottom-4 size-4 text-muted-foreground/0 transition-colors duration-200 group-hover:text-muted-foreground" />}
            </Card>
        );
    }

    return (
        <Card className="h-full gap-0 overflow-hidden py-0">
            <header className="flex items-start justify-between gap-3 px-5 pt-5 pb-3">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <WidgetIcon name={widget.icon} />
                        <h2 className="truncate text-[15px] font-semibold tracking-tight">{widget.title}</h2>
                        <Deferred data={widget.prop} fallback={<></>}>
                            {() => {
                                const result = props[widget.prop] as WidgetResult<ListData> | undefined;

                                return result?.ok && typeof result.data.count === 'number' && result.data.count > 0
                                    ? <Badge variant="secondary">{formatStat(result.data.count)}</Badge>
                                    : null;
                            }}
                        </Deferred>
                    </div>
                    {widget.description && <p className="mt-1 text-[13px] text-muted-foreground">{widget.description}</p>}
                </div>
                {widget.link && (
                    <SmartLink url={widget.link.url} className="inline-flex shrink-0 items-center gap-1 rounded-md text-[13px] text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-4 focus-visible:ring-ring-glow focus-visible:outline-none">
                        {widget.link.label ?? 'See all'}
                        <ArrowRight className="size-3.5" />
                    </SmartLink>
                )}
            </header>
            <div className="flex-1 border-t border-border/60">{body}</div>
        </Card>
    );
}

function WidgetIcon({ name }: { name: string | null }) {
    if (!name) return null;
    const Icon = navIcon(name);

    return <Icon aria-hidden="true" className="size-4 shrink-0 text-muted-foreground/80" />;
}

function StatBody({ widget, data }: { widget: WidgetDefinition; data: StatData }) {
    const Trend = data.trend === 'down' ? TrendingDown : TrendingUp;

    return (
        <div className="sunrice-row-in">
            <p className="text-3xl font-semibold tracking-tight tabular-nums" aria-label={`${widget.title}: ${formatStat(data.value)}`}>{formatStat(data.value)}</p>
            {(data.delta || data.description) && (
                <p className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    {data.delta && (
                        <span className={cn('inline-flex items-center gap-1 font-medium tabular-nums', data.trend === 'up' && 'text-emerald-700 dark:text-emerald-400', data.trend === 'down' && 'text-rose-700 dark:text-rose-400')}>
                            {data.trend && <Trend className="size-3.5" />}
                            {data.delta}
                        </span>
                    )}
                    {data.description && <span>{data.description}</span>}
                </p>
            )}
        </div>
    );
}

function ListBody({ widget, data, now }: { widget: WidgetDefinition; data: ListData; now: Date }) {
    if (data.rows.length === 0) return <Empty text={widget.empty ?? 'Nothing here yet.'} icon={widget.icon} />;

    return (
        <ul className="divide-y divide-border/60">
            {data.rows.map((row, i) => (
                <li key={`${i}-${row.title}`} className="sunrice-row-in" style={{ '--i': i } as React.CSSProperties}>
                    <Row row={row} now={now} />
                </li>
            ))}
        </ul>
    );
}

function Row({ row, now }: { row: ListRow; now: Date }) {
    const when = relativeTime(row.at, now);

    return (
        <SmartLink url={row.url} className={cn('flex items-center gap-3 px-5 py-2.5', row.url !== null && 'transition-colors duration-150 hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none')}>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{row.title}</p>
                {row.meta && <p className="truncate text-xs text-muted-foreground">{row.meta}</p>}
            </div>
            {row.badge && <Badge variant={BADGE[row.tone]}>{row.badge}</Badge>}
            {when && <time dateTime={row.at ?? undefined} className="w-24 shrink-0 text-right text-xs text-muted-foreground tabular-nums max-sm:hidden">{when}</time>}
        </SmartLink>
    );
}

function CardsBody({ widget, data }: { widget: WidgetDefinition; data: CardsData }) {
    if (data.cards.length === 0) return <Empty text={widget.empty ?? 'Nothing here yet.'} icon={widget.icon} />;

    return (
        <div className="grid gap-3 p-4 sm:grid-cols-2">
            {data.cards.map((card, i) => {
                const Icon = navIcon(card.icon ?? undefined);

                return (
                    <SmartLink
                        key={`${i}-${card.title}`}
                        url={card.url}
                        className={cn('sunrice-row-in flex gap-3 rounded-lg border border-border/70 p-3.5', card.url !== null && 'transition-[border-color,box-shadow] duration-200 ease-out-quart hover:border-border hover:shadow-card')}
                    >
                        {card.icon && <Icon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-muted-foreground" />}
                        <div className="min-w-0">
                            <p className="text-sm font-medium">{card.title}</p>
                            {card.text && <p className="mt-0.5 text-[13px] leading-relaxed text-muted-foreground">{card.text}</p>}
                        </div>
                    </SmartLink>
                );
            })}
        </div>
    );
}

function Empty({ text, icon }: { text: string; icon: string | null }) {
    const Icon = navIcon(icon ?? undefined);

    return (
        <div className="flex flex-col items-center gap-2 px-5 py-10 text-center">
            <span className="grid size-9 place-items-center rounded-full bg-muted text-muted-foreground"><Icon className="size-4" /></span>
            <p className="text-[13px] text-muted-foreground">{text}</p>
        </div>
    );
}

function Unavailable() {
    return (
        <div role="status" className="flex items-center gap-2 px-5 py-6 text-[13px] text-muted-foreground">
            <CircleAlert className="size-4 shrink-0" />
            This couldn't be loaded right now.
        </div>
    );
}

function WidgetSkeleton({ type }: { type: WidgetDefinition['type'] }) {
    if (type === 'stat') {
        return (
            <div aria-hidden="true" className="space-y-2.5">
                <Skeleton className="h-8 w-24" />
                <Skeleton className="h-3 w-36" />
            </div>
        );
    }

    return (
        <div aria-hidden="true" className="divide-y divide-border/60">
            {[0, 1, 2, 3].map((i) => (
                <div key={i} className="flex items-center gap-3 px-5 py-3">
                    <div className="flex-1 space-y-1.5">
                        <Skeleton className="h-3.5" style={{ width: `${70 - i * 12}%` }} />
                        <Skeleton className="h-2.5 w-24" />
                    </div>
                    <Skeleton className="h-3 w-14" />
                </div>
            ))}
        </div>
    );
}
