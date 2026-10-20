import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { useConfirm } from './confirm';
import type { InfoBlock, InfoCard } from './types';

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
                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    {block.rows.map((row) => (
                        <React.Fragment key={row.label}>
                            <dt className="text-muted-foreground">{row.label}</dt>
                            <dd className="min-w-0 break-words tabular-nums">{row.type === 'datetime' ? formatDateTime(row.value) : row.value}</dd>
                        </React.Fragment>
                    ))}
                </dl>
            );
        case 'list':
            return (
                <div className="flex flex-col gap-2">
                    <h3 className="text-sm font-medium">{block.heading}</h3>
                    {block.items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{block.empty}</p>
                    ) : (
                        <ul className="divide-y rounded-md border text-sm">
                            {block.items.map((item, i) => (
                                <li key={i} className="flex flex-wrap items-baseline gap-x-2 gap-y-1 px-3 py-2">
                                    <Badge variant="secondary">{item.label}</Badge>
                                    {item.at && <span className="text-muted-foreground tabular-nums">{formatDateTime(item.at)}</span>}
                                    {item.meta && <span className="text-muted-foreground">{item.meta}</span>}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            );
        default:
            return null;
    }
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
                            <div className="flex flex-wrap gap-2">
                                {card.buttons.map((button) => (
                                    <Button key={button.url} type="button" variant="outline" size="sm" disabled={busy} onClick={() => press(button)}>{button.label}</Button>
                                ))}
                            </div>
                        )}
                        {card.links.map((link) => (
                            <Link key={link.url} href={link.url} className="text-sm text-muted-foreground underline-offset-4 hover:underline">{link.label}</Link>
                        ))}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
