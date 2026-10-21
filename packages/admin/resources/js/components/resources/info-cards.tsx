import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { useConfirm } from './confirm';
import type { InfoBlock, InfoCard } from './types';
import { t } from '@/lib/i18n';

export const formatDateTime = (iso: string | null): string => (iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '');

const tones: Record<string, string> = {
    info: 'border-border bg-muted/40',
    warning: 'border-amber-500/40 bg-amber-500/5',
    danger: 'border-destructive/40 bg-destructive/5',
};

function Block({ block }: { block: InfoBlock }) {
    switch (block.type) {
        case 'notice':
            return (
                <div className={cn('rounded-md border p-3 text-sm', tones[block.tone])}>
                    <p className={cn('font-medium', block.tone === 'danger' && 'text-destructive')}>{block.text}</p>
                    {block.detail && <p className="text-muted-foreground">{block.detail}</p>}
                </div>
            );
        case 'rows':
            return (
                <dl className="flex flex-col divide-y divide-border/60 text-sm">
                    {block.rows.map((row) => (
                        <div key={row.label} className="flex items-baseline justify-between gap-4 py-2 first:pt-0 last:pb-0">
                            <dt className="shrink-0 text-muted-foreground">{row.label}</dt>
                            <dd className="min-w-0 text-right break-words tabular-nums">{row.type === 'datetime' ? formatDateTime(row.value) : row.value}</dd>
                        </div>
                    ))}
                </dl>
            );
        case 'list':
            return <EventList heading={block.heading} empty={block.empty} items={block.items} />;
        default:
            return null;
    }
}

const SHOWN = 5;

/** A list of events: what and when on one line, its details under it; the first few, then "Show more". */
function EventList({ heading, empty, items }: { heading: string; empty: string; items: { label: string; meta: string | null; at: string | null }[] }) {
    const [all, setAll] = React.useState(false);
    const shown = all ? items : items.slice(0, SHOWN);

    return (
        <div className="flex flex-col gap-2">
            <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{heading}</h3>
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                <ul className="flex flex-col divide-y divide-border/60 text-sm">
                    {shown.map((item, i) => (
                        <li key={i} className="flex flex-col gap-0.5 py-2 first:pt-0">
                            <div className="flex items-baseline justify-between gap-3">
                                <span className="font-medium">{item.label}</span>
                                {item.at && <time dateTime={item.at} className="shrink-0 text-xs text-muted-foreground tabular-nums">{formatDateTime(item.at)}</time>}
                            </div>
                            {item.meta && <span className="text-xs break-words text-muted-foreground">{item.meta}</span>}
                        </li>
                    ))}
                </ul>
            )}
            {items.length > SHOWN && (
                <button type="button" className="self-start text-xs font-medium text-muted-foreground hover:text-foreground" onClick={() => setAll(!all)}>
                    {all ? t('Show fewer') : t('Show :count more', { count: items.length - SHOWN })}
                </button>
            )}
        </div>
    );
}

/** Cards of read-only information, as `Sunrice\Admin\Support\Card` describes them. */
export function InfoCards({ cards, className }: { cards: InfoCard[]; className?: string }) {
    const [confirm, confirmDialog] = useConfirm();
    const [busy, setBusy] = React.useState(false);
    if (cards.length === 0) return null;

    const press = (button: InfoCard['buttons'][number]) =>
        confirm(button.confirm, () => {
            router.post(button.url, {}, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
        });

    return (
        <div className={cn('flex flex-col gap-6', className)}>
            {confirmDialog}
            {cards.map((card, i) => (
                <Card key={i}>
                    <CardHeader>
                        <CardTitle>{card.title}</CardTitle>
                        {card.description && <CardDescription>{card.description}</CardDescription>}
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {card.blocks.map((block, j) => <Block key={j} block={block} />)}
                        {card.buttons.length > 0 && (
                            <div className="grid gap-2">
                                {card.buttons.map((button) => (
                                    <Button key={button.url} type="button" variant="outline" size="sm" className="w-full" disabled={busy} onClick={() => press(button)}>{button.label}</Button>
                                ))}
                            </div>
                        )}
                        {card.links.map((link) => (
                            <Link key={link.url} href={link.url} className="inline-flex items-center gap-1 text-sm font-medium underline-offset-4 hover:underline">
                                {link.label}<ArrowRight className="size-3.5" />
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
