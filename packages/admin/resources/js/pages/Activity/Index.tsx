import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, History, Search, X } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/admin-url';

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
    subject: string | null;
    user: string | null;
    from: string | null;
    to: string | null;
}

interface Props {
    query: Query;
    types: { value: string; label: string }[];
    sources: string[];
    rows: { data: Row[]; total: number; currentPage: number; lastPage: number; prevUrl: string | null; nextUrl: string | null };
}

const ALL = '__all';
const SOURCE_LABELS: Record<string, string> = { admin: 'Admin', ai: 'AI agent', system: 'System', security: 'Security' };

/** Who changed what, newest first. */
export default function ActivityIndex({ query, types, sources, rows }: Props) {
    useBreadcrumbs([{ label: 'Activity log' }]);
    const [search, setSearch] = React.useState(query.search);
    const base = adminUrl('activity');

    const visit = (changes: Partial<Query>) => {
        const next: Query = { ...query, ...changes };
        const params = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== null && value !== ''));
        router.get(base, params, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <div className="flex flex-col gap-4">
            <Head title="Activity log" />
            <PageHeader title="Activity log" />

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
                            <TableRow><TableCell colSpan={4} className="py-12 text-center text-muted-foreground">Nothing recorded{query.search || query.source || query.type || query.from || query.to ? ' that matches' : ' yet'}.</TableCell></TableRow>
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

            {rows.lastPage > 1 && (
                <div className="flex items-center justify-end gap-2 text-sm text-muted-foreground">
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
