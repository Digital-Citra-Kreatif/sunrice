import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, History, Search, Trash2, X } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import { useConfirm } from '@/components/resources/confirm';
import type { FieldInfo } from '@/components/resources/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/admin-url';
import { queryParams } from '@/lib/query-params';

interface Row {
    id: number;
    at: string | null;
    source: string;
    via: string | null;
    userId: string | null;
    user: string | null;
    action: string;
    type: string;
    typeLabel: string;
    subjectId: string | null;
    subject: string | null;
    url: string | null;
    changed: string[];
    note: string | null;
    ip: string | null;
}

interface Query {
    search: string;
    source: string | null;
    type: string | null;
    action: string | null;
    perPage: number;
    subject: string | null;
    user: string | null;
    from: string | null;
    to: string | null;
}

interface Props {
    query: Query;
    types: { value: string; label: string }[];
    actions: string[];
    sources: string[];
    perPageOptions: number[];
    defaultPerPage: number;
    /** Set when the user may prune (sunrice.activity.prune): the configured days to keep. */
    prune: { keepDays: number | null } | null;
    rows: { data: Row[]; total: number; currentPage: number; lastPage: number; prevUrl: string | null; nextUrl: string | null };
}

const ALL = '__all';

const SOURCE_LABELS: Record<string, string> = { admin: 'Admin', ai: 'AI agent', system: 'System', security: 'Security' };

/** Who changed what, newest first. */
export default function ActivityIndex({ query, types, actions, sources, perPageOptions, defaultPerPage, prune, rows }: Props) {
    useBreadcrumbs([{ label: 'Activity log' }]);
    const [search, setSearch] = React.useState(query.search);
    const [confirm, confirmDialog] = useConfirm();
    const base = adminUrl('activity');

    const visit = (changes: Partial<Query>) => {
        const next: Query = { ...query, ...changes };
        router.get(base, queryParams(next, { perPage: defaultPerPage }, { perPage: 'per_page' }), { preserveState: true, preserveScroll: true, replace: true });
    };

    const pruneLog = () => {
        const days: FieldInfo = {
            kind: 'field', type: 'number', name: 'days', label: 'Keep the last … days', required: true, disabled: false,
            help: 'Older lines are deleted for good. Pruning is itself recorded, and alerts are sent.', placeholder: null, span: 1,
            default: prune?.keepDays ?? 365, min: 1,
        };
        confirm('Delete old activity log lines?', (values) => new Promise<boolean>((resolve) => router.post(`${base}/prune`, { days: values.days }, {
            preserveScroll: true, onSuccess: () => resolve(true), onError: () => resolve(false),
        })), true, [days]);
    };

    return (
        <div className="flex flex-col gap-4">
            <Head title="Activity log" />
            {confirmDialog}
            <PageHeader
                title="Activity log"
                actions={prune && <Button variant="outline" onClick={pruneLog}><Trash2 />Prune…</Button>}
            />

            <div className="flex flex-wrap items-center gap-2">
                <form className="relative w-full sm:w-64" onSubmit={(e) => { e.preventDefault(); visit({ search }); }}>
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input type="search" aria-label="Search the activity log" placeholder="Search…" className="pl-8" value={search}
                        onChange={(e) => { setSearch(e.target.value); if (e.target.value === '' && query.search) visit({ search: '' }); }} />
                </form>
                <Select value={query.source ?? ALL} onValueChange={(value) => visit({ source: value === ALL ? null : value })}>
                    <SelectTrigger aria-label="Source" className="w-36"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>All sources</SelectItem>
                        {sources.map((source) => <SelectItem key={source} value={source}>{SOURCE_LABELS[source] ?? source}</SelectItem>)}
                    </SelectContent>
                </Select>
                <Select value={query.type ?? ALL} onValueChange={(value) => visit({ type: value === ALL ? null : value, subject: null })}>
                    <SelectTrigger aria-label="Type" className="w-40"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Everything</SelectItem>
                        {types.map((type) => <SelectItem key={type.value} value={type.value}>{type.label}</SelectItem>)}
                    </SelectContent>
                </Select>
                <Select value={query.action ?? ALL} onValueChange={(value) => visit({ action: value === ALL ? null : value })}>
                    <SelectTrigger aria-label="Action" className="w-44"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Any action</SelectItem>
                        {actions.map((action) => <SelectItem key={action} value={action}>{action}</SelectItem>)}
                    </SelectContent>
                </Select>
                <Input type="date" aria-label="From" className="w-40" value={query.from ?? ''} onChange={(e) => visit({ from: e.target.value || null })} />
                <Input type="date" aria-label="To" className="w-40" value={query.to ?? ''} onChange={(e) => visit({ to: e.target.value || null })} />
                {(query.subject || query.user) && (
                    <Button variant="secondary" size="sm" onClick={() => visit({ subject: null, user: null })}>
                        {query.subject ? 'One item\'s history' : 'One person\'s activity'}<X />
                    </Button>
                )}
            </div>

            <Card className="overflow-hidden py-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-44">When</TableHead>
                            <TableHead className="w-48">Who</TableHead>
                            <TableHead>What</TableHead>
                            <TableHead>Details</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.data.length === 0 && (
                            <TableRow><TableCell colSpan={4} className="py-12 text-center text-muted-foreground">Nothing recorded{query.search || query.source || query.type || query.action || query.from || query.to ? ' that matches' : ' yet'}.</TableCell></TableRow>
                        )}
                        {rows.data.map((row, i) => (
                            <TableRow key={row.id} className="sunrice-row-in" style={{ '--i': i } as React.CSSProperties}>
                                <TableCell className="align-top whitespace-nowrap text-muted-foreground">{row.at ? new Date(row.at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : ''}</TableCell>
                                <TableCell className="align-top">
                                    {row.userId ? (
                                        <button type="button" className="text-left hover:underline" onClick={() => visit({ user: row.userId })}>{row.user}</button>
                                    ) : <span>{row.user ?? (row.source === 'system' ? 'System' : 'Someone not signed in')}</span>}
                                    <div className="mt-0.5 flex flex-wrap gap-1">
                                        {row.source !== 'admin' && <Badge variant={row.source === 'security' ? 'destructive' : 'secondary'}>{SOURCE_LABELS[row.source] ?? row.source}{row.via ? ` · ${row.via}` : ''}</Badge>}
                                    </div>
                                </TableCell>
                                <TableCell className="align-top">
                                    <span className="font-medium">{row.action}</span>{' '}
                                    <span className="text-muted-foreground">{row.typeLabel.toLowerCase()}</span>{' '}
                                    {row.url ? <Link href={row.url} className="underline-offset-4 hover:underline">{row.subject}</Link> : <span>{row.subject}</span>}
                                    {row.subjectId && (
                                        <Button variant="ghost" size="icon" className="ml-1 size-6 align-middle" aria-label={`History of ${row.subject ?? 'this item'}`}
                                            onClick={() => visit({ type: row.type, subject: row.subjectId })}>
                                            <History className="size-3.5" />
                                        </Button>
                                    )}
                                </TableCell>
                                <TableCell className="align-top text-sm text-muted-foreground">
                                    {row.changed.length > 0 && <span>{row.changed.join(', ')}</span>}
                                    {row.note && <span>{row.changed.length > 0 ? ' · ' : ''}{row.note}</span>}
                                    {row.ip && <span className="block text-xs">IP {row.ip}</span>}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>

            {rows.total > (perPageOptions[0] ?? 25) && (
                <div className="flex flex-wrap items-center justify-end gap-2 text-sm text-muted-foreground">
                    <Select value={String(query.perPage)} onValueChange={(value) => visit({ perPage: Number(value) })}>
                        <SelectTrigger aria-label="Lines per page" className="h-8 w-32"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {perPageOptions.map((n) => <SelectItem key={n} value={String(n)}>{n} per page</SelectItem>)}
                        </SelectContent>
                    </Select>
                    <span>Page {rows.currentPage} of {rows.lastPage} · {rows.total} lines</span>
                    <Button variant="outline" size="icon" className="size-8" disabled={!rows.prevUrl} asChild={!!rows.prevUrl} aria-label="Previous page">
                        {rows.prevUrl ? <Link href={rows.prevUrl} preserveScroll><ChevronLeft /></Link> : <ChevronLeft />}
                    </Button>
                    <Button variant="outline" size="icon" className="size-8" disabled={!rows.nextUrl} asChild={!!rows.nextUrl} aria-label="Next page">
                        {rows.nextUrl ? <Link href={rows.nextUrl} preserveScroll><ChevronRight /></Link> : <ChevronRight />}
                    </Button>
                </div>
            )}
        </div>
    );
}
