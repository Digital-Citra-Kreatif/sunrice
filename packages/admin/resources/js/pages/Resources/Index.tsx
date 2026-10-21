import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, Columns3, Download, MoreHorizontal, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import { Cell } from '@/components/resources/cell';
import { forTargets, useConfirm } from '@/components/resources/confirm';
import { DateRangeFilter } from '@/components/resources/date-range-filter';
import type { ActionInfo, ColumnInfo, FilterInfo, ResourceInfo, Row } from '@/components/resources/types';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';
import { toggleColumn, type TablePreferences } from '@/lib/table-preferences';
import { useTablePreferences } from '@/lib/use-table-preferences';

interface Props {
    resource: ResourceInfo;
    columns: ColumnInfo[];
    filters: FilterInfo[];
    rowActions: ActionInfo[];
    bulkActions: ActionInfo[];
    /** trash: the model goes to the trash when deleted, and the user may look in it (?status=trashed). */
    can: { create: boolean; export: boolean; trash?: boolean };
    query: { search: string; filters: Record<string, string>; sort: string; status?: string };
    rows: {
        data: Row[];
        total: number;
        from: number | null;
        to: number | null;
        perPage: number;
        currentPage: number;
        lastPage: number;
        prevUrl: string | null;
        nextUrl: string | null;
    };
    perPageOptions: number[];
    /** The user's saved columns and page size for this table. */
    preferences: TablePreferences & { key: string };
}

const ALL = '__all';

export default function ResourceIndex({ resource, columns: allColumns, filters, rowActions, bulkActions, can, query, rows, perPageOptions, preferences: initialPreferences }: Props) {
    useBreadcrumbs([{ label: resource.label }]);
    const [confirm, confirmDialog] = useConfirm();
    const [search, setSearch] = React.useState(query.search);
    const [selected, setSelected] = React.useState<(string | number)[]>([]);
    const base = adminUrl(resource.path);
    const loading = useListLoading(base);
    const { preferences, setColumns, setPerPage } = useTablePreferences(initialPreferences.key, initialPreferences);
    // The first column (the record's link) is always shown.
    const visible = preferences.columns ?? allColumns.map((column) => column.name);
    const columns = allColumns.filter((column, index) => index === 0 || visible.includes(column.name));

    React.useEffect(() => setSelected([]), [rows.data]);

    /** Reload the list with changed query parameters (back to page 1). */
    const trashed = query.status === 'trashed';
    const visit = (changes: Partial<{ search: string; filters: Record<string, string>; sort: string; per_page: number; status: string }>) => {
        const params = { search: query.search, filters: query.filters, sort: query.sort, per_page: rows.perPage, status: query.status ?? '', ...changes };
        router.get(base, {
            ...(params.status ? { status: params.status } : {}),
            ...(params.search ? { search: params.search } : {}),
            ...(Object.keys(params.filters).length ? { filters: params.filters } : {}),
            sort: params.sort,
            per_page: params.per_page,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    // Search as you type, after a short pause.
    React.useEffect(() => {
        if (search === query.search) return;
        const timer = window.setTimeout(() => visit({ search }), 300);
        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const sortBy = (column: string) => {
        visit({ sort: query.sort === column ? `-${column}` : column });
    };

    const runRowAction = (row: Row, action: ActionInfo) =>
        confirm(action.confirm, (values) => action.fields.length === 0
            ? router.post(`${base}/${row.key}/actions/${action.key}`, {}, { preserveScroll: true })
            // With fields, the dialog stays open until the action is accepted.
            : new Promise<boolean>((resolve) => router.post(`${base}/${row.key}/actions/${action.key}`, values, {
                preserveScroll: true, onSuccess: () => resolve(true), onError: () => resolve(false),
            })), action.destructive, forTargets(action.fields, [row.id]));
    // A row action keyed "delete" replaces the built-in Delete in the row's menu.
    const customDelete = rowActions.some((action) => action.key === 'delete');
    const deleteRow = (row: Row) =>
        confirm(can.trash ? t('Move :title to the trash? You can restore it.', { title: row.title }) : t('Delete :title? This can\'t be undone.', { title: row.title }),
            () => router.delete(`${base}/${row.key}`, { preserveScroll: true }), true);
    const restoreRow = (row: Row) => router.post(`${base}/${row.key}/restore`, {}, { preserveScroll: true });
    const runBulk = (action: ActionInfo) =>
        confirm(action.confirm, (values) => action.fields.length === 0
            ? router.post(`${base}/bulk`, { action: action.key, ids: selected }, { preserveScroll: true })
            : new Promise<boolean>((resolve) => router.post(`${base}/bulk`, { ...values, action: action.key, ids: selected }, {
                preserveScroll: true, onSuccess: () => resolve(true), onError: () => resolve(false),
            })), action.destructive, forTargets(action.fields, selected));
    // In the trash: only Restore, for the selected rows too.
    const shownBulkActions: ActionInfo[] = trashed
        ? [{ key: 'restore', label: t('Restore'), confirm: null, destructive: false, url: false, newTab: false, fields: [] }]
        : bulkActions;

    const exportUrl = () => {
        const params = new URLSearchParams();
        if (query.search) params.set('search', query.search);
        Object.entries(query.filters).forEach(([name, value]) => params.set(`filters[${name}]`, value));
        params.set('sort', query.sort);
        return `${base}/export?${params.toString()}`;
    };

    const allSelected = rows.data.length > 0 && rows.data.every((row) => selected.includes(row.id));
    const hasRowMenu = rows.data.some((row) => row.can.update || row.can.delete || row.actions.length > 0);

    return (
        <div className="flex flex-col gap-6">
            <Head title={resource.label} />
            {confirmDialog}

            <PageHeader
                title={trashed ? t(':resource — trash', { resource: resource.label }) : resource.label}
                actions={
                    <>
                        {can.trash && (
                            <Button variant={trashed ? 'secondary' : 'ghost'} onClick={() => visit({ status: trashed ? '' : 'trashed' })}>
                                {trashed ? t('Back to the list') : <><Trash2 /> {t('Trash')}</>}
                            </Button>
                        )}
                        {can.export && !trashed && (
                            <Button variant="outline" asChild>
                                <a href={exportUrl()}><Download /> {t('Export')}</a>
                            </Button>
                        )}
                        {can.create && !trashed && (
                            <Button asChild>
                                <Link href={`${base}/create`}><Plus /> {t('New :resource', { resource: resource.singularLabel.toLowerCase() })}</Link>
                            </Button>
                        )}
                    </>
                }
            />

            <div className="flex flex-wrap items-center gap-2">
                <div className="relative w-full max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input className="pl-9" placeholder={t('Search…')} aria-label={t('Search')} value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
                {filters.map((filter) => filter.type === 'date_range' ? (
                    <DateRangeFilter key={filter.name} filter={filter} value={query.filters[filter.name]} onChange={(value) => {
                        const next = { ...query.filters };
                        if (value === '') delete next[filter.name];
                        else next[filter.name] = value;
                        visit({ filters: next });
                    }} />
                ) : (
                    <Select
                        key={filter.name}
                        value={query.filters[filter.name] ?? ALL}
                        onValueChange={(value) => {
                            const next = { ...query.filters };
                            if (value === ALL) delete next[filter.name];
                            else next[filter.name] = value;
                            visit({ filters: next });
                        }}
                    >
                        <SelectTrigger className="w-40" aria-label={filter.label}><SelectValue placeholder={filter.label} /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t(':filter: all', { filter: filter.label })}</SelectItem>
                            {filter.options.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                        </SelectContent>
                    </Select>
                ))}
                {allColumns.length > 1 && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" className="ml-auto"><Columns3 /> {t('Columns')}</Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>{t('Show columns')}</DropdownMenuLabel>
                            {allColumns.slice(1).map((column) => (
                                <DropdownMenuCheckboxItem key={column.name} checked={visible.includes(column.name)}
                                    onSelect={(event) => event.preventDefault()}
                                    onCheckedChange={() => void setColumns(toggleColumn(visible, column.name, allColumns.map((c) => c.name)))}>
                                    {column.label}
                                </DropdownMenuCheckboxItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
                {selected.length > 0 && shownBulkActions.length > 0 && (
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">{t(':count selected', { count: selected.length })}</span>
                        {shownBulkActions.map((action) => (
                            <Button key={action.key} variant={action.destructive ? 'destructive' : 'outline'} onClick={() => runBulk(action)}>{action.label}</Button>
                        ))}
                    </div>
                )}
            </div>

            <Card className="gap-0 overflow-hidden py-0">
                <Table>
                    <TableHeader className="bg-muted/60">
                        <TableRow className="hover:bg-transparent">
                            {shownBulkActions.length > 0 && (
                                <TableHead className="w-8">
                                    <Checkbox aria-label={t('Select all rows')} checked={allSelected}
                                        onCheckedChange={(checked) => setSelected(checked ? rows.data.map((row) => row.id) : [])} />
                                </TableHead>
                            )}
                            {columns.map((column) => (
                                <TableHead key={column.name}>
                                    {column.sortable ? (
                                        <button type="button" onClick={() => sortBy(column.name)}
                                            className="-ml-2 inline-flex h-8 items-center gap-1 rounded-md px-2 hover:bg-accent hover:text-accent-foreground">
                                            {column.label}
                                            {query.sort === column.name ? <ArrowUp className="size-3" />
                                                : query.sort === `-${column.name}` ? <ArrowDown className="size-3" />
                                                    : <ArrowUpDown className="size-3 opacity-40" />}
                                        </button>
                                    ) : column.label}
                                </TableHead>
                            ))}
                            {hasRowMenu && <TableHead className="w-px"><span className="sr-only">{t('Actions')}</span></TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {loading && Array.from({ length: Math.max(rows.data.length, 3) }, (_, i) => (
                            <TableRow key={`skeleton-${i}`} className="sunrice-row-in hover:bg-transparent" style={{ '--i': i } as React.CSSProperties} aria-hidden="true">
                                {shownBulkActions.length > 0 && <TableCell><Skeleton className="size-4" /></TableCell>}
                                {columns.map((column) => <TableCell key={column.name}><Skeleton className="h-4 w-[60%] max-w-48" /></TableCell>)}
                                {hasRowMenu && <TableCell />}
                            </TableRow>
                        ))}
                        {!loading && rows.data.map((row, i) => (
                            <TableRow key={row.id} className="sunrice-row-in" style={{ '--i': i } as React.CSSProperties} data-state={selected.includes(row.id) ? 'selected' : undefined}>
                                {shownBulkActions.length > 0 && (
                                    <TableCell>
                                        <Checkbox aria-label={t('Select :title', { title: row.title })} checked={selected.includes(row.id)}
                                            onCheckedChange={(checked) => setSelected((current) => checked ? [...current, row.id] : current.filter((id) => id !== row.id))} />
                                    </TableCell>
                                )}
                                {columns.map((column, index) => (
                                    <TableCell key={column.name}>
                                        <div className="max-w-80 truncate">
                                            {index === 0 && (row.url || row.can.update) && !trashed ? (
                                                <Link href={row.url ?? `${base}/${row.key}/edit`} className="font-medium underline-offset-4 hover:underline">
                                                    <Cell column={column} value={row.cells[column.name] ?? null} />
                                                </Link>
                                            ) : <Cell column={column} value={row.cells[column.name] ?? null} />}
                                        </div>
                                    </TableCell>
                                ))}
                                {hasRowMenu && (
                                    <TableCell className="w-px text-right">
                                        {trashed ? (
                                            row.can.delete && <Button variant="outline" size="sm" onClick={() => restoreRow(row)}><RotateCcw /> {t('Restore')}</Button>
                                        ) : (row.can.update || row.can.delete || row.actions.length > 0) && (
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-8" aria-label={t('Actions for :title', { title: row.title })}><MoreHorizontal /></Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    {row.url && <DropdownMenuItem asChild><Link href={row.url}>{t('Open')}</Link></DropdownMenuItem>}
                                                    {row.can.update && <DropdownMenuItem asChild><Link href={`${base}/${row.key}/edit`}>{row.url ? t('Settings') : t('Edit')}</Link></DropdownMenuItem>}
                                                    {rowActions.filter((action) => row.actions.includes(action.key)).map((action) => action.url ? (
                                                        <DropdownMenuItem key={action.key} asChild>
                                                            <a href={row.urls[action.key]} {...(action.newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>{action.label}</a>
                                                        </DropdownMenuItem>
                                                    ) : (
                                                        <DropdownMenuItem key={action.key} variant={action.destructive ? 'destructive' : 'default'} onSelect={() => runRowAction(row, action)}>
                                                            {action.label}
                                                        </DropdownMenuItem>
                                                    ))}
                                                    {row.can.delete && !customDelete && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem variant="destructive" onSelect={() => deleteRow(row)}>{can.trash ? t('Move to trash') : t('Delete')}</DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        )}
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                        {!loading && rows.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={columns.length + 2} className="h-24 text-center text-muted-foreground">
                                    {query.search || Object.keys(query.filters).length ? t('Nothing matches.') : trashed ? t('The trash is empty.') : t('No :resource yet.', { resource: resource.label.toLowerCase() })}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <div className="flex flex-wrap items-center justify-between gap-4 border-t px-6 py-3 text-sm text-muted-foreground">
                    <div className="flex items-center gap-4">
                        <span>{rows.total === 0 ? t('No rows') : t(':from–:to of :total', { from: rows.from, to: rows.to, total: rows.total })}</span>
                        {rows.total > perPageOptions[0]! && (
                            <Select value={String(rows.perPage)} onValueChange={(value) => { void setPerPage(Number(value)); visit({ per_page: Number(value) }); }}>
                                <SelectTrigger className="h-8 w-auto" aria-label={t('Rows per page')}><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {perPageOptions.map((option) => <SelectItem key={option} value={String(option)}>{t(':count per page', { count: option })}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        )}
                    </div>
                    {rows.lastPage > 1 && (
                        <div className="flex items-center gap-2">
                            <span>{t('Page :page of :pages', { page: rows.currentPage, pages: rows.lastPage })}</span>
                            <PageLink url={rows.prevUrl} label={t('Previous page')}><ChevronLeft /></PageLink>
                            <PageLink url={rows.nextUrl} label={t('Next page')}><ChevronRight /></PageLink>
                        </div>
                    )}
                </div>
            </Card>
        </div>
    );
}

/**
 * Whether a visit to this list is in flight (search, filters, sorting, paging),
 * after a short grace period so a quick answer never flashes placeholders.
 */
function useListLoading(base: string): boolean {
    const [loading, setLoading] = React.useState(false);

    React.useEffect(() => {
        let timer: number | undefined;
        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit;
            if (visit.method !== 'get' || !visit.url.href.startsWith(base)) return;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => setLoading(true), 150);
        });
        const offFinish = router.on('finish', () => {
            window.clearTimeout(timer);
            setLoading(false);
        });
        return () => { window.clearTimeout(timer); offStart(); offFinish(); };
    }, [base]);

    return loading;
}

function PageLink({ url, label, children }: { url: string | null; label: string; children: React.ReactNode }) {
    return url ? (
        <Button variant="outline" size="icon" className="size-8" asChild>
            <Link href={url} preserveScroll aria-label={label}>{children}</Link>
        </Button>
    ) : (
        <Button variant="outline" size="icon" className="size-8" disabled aria-label={label}>{children}</Button>
    );
}
