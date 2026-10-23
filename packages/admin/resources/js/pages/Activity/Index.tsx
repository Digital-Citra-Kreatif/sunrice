import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Columns3, Search, Trash2, X } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { DatePicker } from '@/components/app/date-picker';
import { PageHeader } from '@/components/app/page-header';
import { useConfirm } from '@/components/resources/confirm';
import type { FieldInfo } from '@/components/resources/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/admin-url';
import { panelLocale, t } from '@/lib/i18n';
import { queryParams } from '@/lib/query-params';
import { toggleColumn, type TablePreferences } from '@/lib/table-preferences';
import { useTablePreferences } from '@/lib/use-table-preferences';

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
    /** About their own account ("Rina logged in"): the subject isn't named again. */
    self: boolean;
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
    /** An address, or the start of one ("192.168."). */
    ip: string | null;
}

interface Props {
    query: Query;
    types: { value: string; label: string }[];
    actions: string[];
    /** Who: everyone in the log, by name. */
    people: { value: string; label: string }[];
    sources: string[];
    perPageOptions: number[];
    defaultPerPage: number;
    /** Set when the user may prune (sunrice.activity.prune): the configured days to keep. */
    prune: { keepDays: number | null } | null;
    rows: { data: Row[]; total: number; currentPage: number; lastPage: number; prevUrl: string | null; nextUrl: string | null };
    /** The user's shown columns. */
    preferences?: TablePreferences & { key: string };
}

/** The columns one may hide (When always shows). */
const COLUMNS = ['who', 'what', 'details', 'ip'] as const;

const ALL = '__all';

/** A source's name in the user's language; an unknown one as it is. */
function sourceLabel(source: string): string {
    switch (source) {
        case 'admin': return t('Admin');
        case 'ai': return t('AI agent');
        case 'system': return t('System');
        case 'security': return t('Security');
        default: return source;
    }
}

/** Who changed what, newest first. */
export default function ActivityIndex({ query, types, actions, people = [], sources, perPageOptions, defaultPerPage, prune, rows, preferences: initialPreferences }: Props) {
    useBreadcrumbs([{ label: t('Activity log') }]);
    const [search, setSearch] = React.useState(query.search);
    const [confirm, confirmDialog] = useConfirm();
    const base = adminUrl('activity');
    const { preferences, setColumns } = useTablePreferences(initialPreferences?.key ?? 'table.activity', initialPreferences ?? null);
    const shown = preferences.columns ?? [...COLUMNS];
    const show = (column: (typeof COLUMNS)[number]) => shown.includes(column);
    const columnLabel: Record<(typeof COLUMNS)[number], string> = { who: t('Who'), what: t('What'), details: t('Details'), ip: t('IP address') };
    const filtered = Boolean(query.search || query.source || query.type || query.action || query.from || query.to || query.ip || query.user);
    // Showing one item's history: which one, from its lines.
    const first = rows.data[0];
    const historyOf = query.subject && first ? `${first.typeLabel.toLowerCase()} ${first.subject ?? ''}`.trim() : null;

    const visit = (changes: Partial<Query>) => {
        const next: Query = { ...query, ...changes };
        router.get(base, queryParams(next, { perPage: defaultPerPage }, { perPage: 'per_page' }), { preserveState: true, preserveScroll: true, replace: true });
    };

    const pruneLog = () => {
        const days: FieldInfo = {
            kind: 'field', type: 'number', name: 'days', label: t('Keep the last … days'), required: true, disabled: false,
            help: t('Older lines are permanently deleted. Pruning is itself recorded, and alerts are sent.'), placeholder: null, span: 1,
            default: prune?.keepDays ?? 365, min: 1,
        };
        confirm(t('Delete old activity log lines?'), (values) => new Promise<boolean>((resolve) => router.post(`${base}/prune`, { days: values.days }, {
            preserveScroll: true, onSuccess: () => resolve(true), onError: () => resolve(false),
        })), true, [days]);
    };

    return (
        <div className="flex flex-col gap-4">
            <Head title={t('Activity log')} />
            {confirmDialog}
            <PageHeader
                title={t('Activity log')}
                actions={prune && <Button variant="outline" onClick={pruneLog}><Trash2 />{t('Prune…')}</Button>}
            />

            <div className="flex flex-wrap items-center gap-2">
                <form className="relative w-full sm:w-64" onSubmit={(e) => { e.preventDefault(); visit({ search }); }}>
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input type="search" aria-label={t('Search the activity log')} placeholder={t('Search names, actions or IP…')} className="pl-8" value={search}
                        onChange={(e) => { setSearch(e.target.value); if (e.target.value === '' && query.search) visit({ search: '' }); }} />
                </form>
                <Select value={query.source ?? ALL} onValueChange={(value) => visit({ source: value === ALL ? null : value })}>
                    <SelectTrigger aria-label={t('Source')} className="w-36"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{t('All sources')}</SelectItem>
                        {sources.map((source) => <SelectItem key={source} value={source}>{sourceLabel(source)}</SelectItem>)}
                    </SelectContent>
                </Select>
                <Select value={query.user ?? ALL} onValueChange={(value) => visit({ user: value === ALL ? null : value })}>
                    <SelectTrigger aria-label={t('Who')} className="w-40"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{t('Anyone')}</SelectItem>
                        {query.user && !people.some((person) => person.value === query.user) && <SelectItem value={query.user}>{t('One person')}</SelectItem>}
                        {people.map((person) => <SelectItem key={person.value} value={person.value}>{person.label}</SelectItem>)}
                    </SelectContent>
                </Select>
                <Select value={query.type ?? ALL} onValueChange={(value) => visit({ type: value === ALL ? null : value, subject: null })}>
                    <SelectTrigger aria-label={t('Type')} className="w-40"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{t('Everything')}</SelectItem>
                        {types.map((type) => <SelectItem key={type.value} value={type.value}>{type.label}</SelectItem>)}
                    </SelectContent>
                </Select>
                <Select value={query.action ?? ALL} onValueChange={(value) => visit({ action: value === ALL ? null : value })}>
                    <SelectTrigger aria-label={t('Action')} className="w-44"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{t('Any action')}</SelectItem>
                        {actions.map((action) => <SelectItem key={action} value={action}>{action}</SelectItem>)}
                    </SelectContent>
                </Select>
                <div className="flex items-center gap-2">
                    <DatePicker className="w-36" placeholder={t('From')} value={query.from} onChange={(from) => visit({ from })} />
                    <DatePicker className="w-36" placeholder={t('To')} value={query.to} onChange={(to) => visit({ to })} />
                </div>
                {query.ip && (
                    <Button variant="secondary" size="sm" onClick={() => visit({ ip: null })} aria-label={t('Clear the IP filter')}>
                        {t('IP :ip', { ip: query.ip })}<X />
                    </Button>
                )}
                {query.subject && (
                    <Button variant="secondary" size="sm" onClick={() => visit({ subject: null })}>
                        {historyOf ? t('History of :item', { item: historyOf }) : t('History of one item')}<X />
                    </Button>
                )}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="ml-auto"><Columns3 /> {t('Columns')}</Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>{t('Show columns')}</DropdownMenuLabel>
                        {COLUMNS.map((column) => (
                            <DropdownMenuCheckboxItem key={column} checked={show(column)} onSelect={(event) => event.preventDefault()}
                                onCheckedChange={() => void setColumns(toggleColumn(shown, column, [...COLUMNS]))}>
                                {columnLabel[column]}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Card className="overflow-hidden py-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-44">{t('When')}</TableHead>
                            {show('who') && <TableHead className="w-48">{t('Who')}</TableHead>}
                            {show('what') && <TableHead>{t('What')}</TableHead>}
                            {show('details') && <TableHead>{t('Details')}</TableHead>}
                            {show('ip') && <TableHead className="w-36">{t('IP address')}</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.data.length === 0 && (
                            <TableRow><TableCell colSpan={1 + shown.length} className="py-12 text-center text-muted-foreground">{filtered ? t('Nothing recorded that matches.') : t('Nothing recorded yet.')}</TableCell></TableRow>
                        )}
                        {rows.data.map((row, i) => (
                            <TableRow key={row.id} className="sunrice-row-in" style={{ '--i': i } as React.CSSProperties}>
                                <TableCell className="align-top whitespace-nowrap text-muted-foreground">{row.at ? new Date(row.at).toLocaleString(panelLocale(), { dateStyle: 'medium', timeStyle: 'short' }) : ''}</TableCell>
                                {show('who') && <TableCell className="align-top">
                                    {row.userId ? (
                                        <button type="button" className="text-left hover:underline" onClick={() => visit({ user: row.userId })}>{row.user}</button>
                                    ) : <span>{row.user ?? (row.source === 'system' ? t('System') : t('Someone not signed in'))}</span>}
                                    <div className="mt-0.5 flex flex-wrap gap-1">
                                        {row.source !== 'admin' && <Badge variant={row.source === 'security' ? 'warning' : 'secondary'}>{sourceLabel(row.source)}{row.via ? ` · ${row.via}` : ''}</Badge>}
                                    </div>
                                </TableCell>}
                                {show('what') && <TableCell className="align-top">
                                    <span className="font-medium">{row.action}</span>{' '}
                                    {!row.self && (
                                        <>
                                            <span className="text-muted-foreground">{row.type === 'account' ? t('for') : row.typeLabel.toLowerCase()}</span>{' '}
                                            {row.url ? <Link href={row.url} className="underline-offset-4 hover:underline">{row.subject}</Link> : <span>{row.subject}</span>}
                                        </>
                                    )}
                                </TableCell>}
                                {show('details') && <TableCell className="align-top text-sm text-muted-foreground">
                                    {row.changed.length > 0 && <span>{row.changed.join(', ')}</span>}
                                    {row.note && <span>{row.changed.length > 0 ? ' · ' : ''}{row.note}</span>}
                                </TableCell>}
                                {show('ip') && <TableCell className="align-top font-mono text-xs whitespace-nowrap text-muted-foreground">
                                    {row.ip && (query.ip === row.ip ? <span>{row.ip}</span> : (
                                        <button type="button" className="hover:text-foreground hover:underline" title={t('Show only what came from :ip', { ip: row.ip })}
                                            onClick={() => visit({ ip: row.ip })}>{row.ip}</button>
                                    ))}
                                </TableCell>}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>

            {rows.total > (perPageOptions[0] ?? 25) && (
                <div className="flex flex-wrap items-center justify-end gap-2 text-sm text-muted-foreground">
                    <Select value={String(query.perPage)} onValueChange={(value) => visit({ perPage: Number(value) })}>
                        <SelectTrigger aria-label={t('Rows per page')} className="h-8 w-auto"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {perPageOptions.map((n) => <SelectItem key={n} value={String(n)}>{t(':count per page', { count: n })}</SelectItem>)}
                        </SelectContent>
                    </Select>
                    <span>{t('Page :page of :pages · :count lines', { page: rows.currentPage, pages: rows.lastPage, count: rows.total })}</span>
                    <Button variant="outline" size="icon" className="size-8" disabled={!rows.prevUrl} asChild={!!rows.prevUrl} aria-label={t('Previous page')}>
                        {rows.prevUrl ? <Link href={rows.prevUrl} preserveScroll><ChevronLeft /></Link> : <ChevronLeft />}
                    </Button>
                    <Button variant="outline" size="icon" className="size-8" disabled={!rows.nextUrl} asChild={!!rows.nextUrl} aria-label={t('Next page')}>
                        {rows.nextUrl ? <Link href={rows.nextUrl} preserveScroll><ChevronRight /></Link> : <ChevronRight />}
                    </Button>
                </div>
            )}
        </div>
    );
}
