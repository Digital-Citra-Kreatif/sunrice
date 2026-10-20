import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, Download, MoreHorizontal, Plus, Search } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import { Cell } from '@/components/resources/cell';
import { useConfirm } from '@/components/resources/confirm';
import type { ActionInfo, ColumnInfo, FilterInfo, ResourceInfo, Row } from '@/components/resources/types';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/admin-url';

interface Props {
    resource: ResourceInfo;
    columns: ColumnInfo[];
    filters: FilterInfo[];
    rowActions: ActionInfo[];
    bulkActions: ActionInfo[];
    can: { create: boolean; export: boolean };
    query: { search: string; filters: Record<string, string>; sort: string };
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
}

const ALL = '__all';

export default function ResourceIndex({ resource, columns, filters, rowActions, bulkActions, can, query, rows, perPageOptions }: Props) {
    useBreadcrumbs([{ label: resource.label }]);
    const [confirm, confirmDialog] = useConfirm();
    const [search, setSearch] = React.useState(query.search);
    const [selected, setSelected] = React.useState<(string | number)[]>([]);
    const base = adminUrl(resource.key);
    const loading = useListLoading(base);

    React.useEffect(() => setSelected([]), [rows.data]);

    /** Reload the list with changed query parameters (back to page 1). */
    const visit = (changes: Partial<{ search: string; filters: Record<string, string>; sort: string; per_page: number }>) => {
        const params = { search: query.search, filters: query.filters, sort: query.sort, per_page: rows.perPage, ...changes };
        router.get(base, {
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
        confirm(action.confirm, () => router.post(`${base}/${row.id}/actions/${action.key}`, {}, { preserveScroll: true }), action.destructive);
    const deleteRow = (row: Row) =>
        confirm(`Delete ${row.title}? This can't be undone.`, () => router.delete(`${base}/${row.id}`, { preserveScroll: true }), true);
    const runBulk = (action: ActionInfo) =>
        confirm(action.confirm, () => router.post(`${base}/bulk/${action.key}`, { ids: selected }, { preserveScroll: true }), action.destructive);

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
                title={resource.label}
                actions={
                    <>
                        {can.export && (
                            <Button variant="outline" asChild>
                                <a href={exportUrl()}><Download /> Export</a>
                            </Button>
                        )}
                        {can.create && (
                            <Button asChild>
                                <Link href={`${base}/create`}><Plus /> New {resource.singularLabel.toLowerCase()}</Link>
                            </Button>
                        )}
                    </>
                }
            />

            <div className="flex flex-wrap items-center gap-2">
                <div className="relative w-full max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input className="pl-9" placeholder="Search…" aria-label="Search" value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
                {filters.map((filter) => (
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
                            <SelectItem value={ALL}>{filter.label}: all</SelectItem>
                            {filter.options.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                        </SelectContent>
                    </Select>
                ))}
                {selected.length > 0 && bulkActions.length > 0 && (
                    <div className="ml-auto flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">{selected.length} selected</span>
                        {bulkActions.map((action) => (
                            <Button key={action.key} variant={action.destructive ? 'destructive' : 'outline'} onClick={() => runBulk(action)}>{action.label}</Button>
                        ))}
                    </div>
                )}
            </div>

            <Card className="gap-0 overflow-hidden py-0">
                <Table>
                    <TableHeader className="bg-muted/60">
                        <TableRow className="hover:bg-transparent">
                            {bulkActions.length > 0 && (
                                <TableHead className="w-8">
                                    <Checkbox aria-label="Select all rows" checked={allSelected}
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
                            {hasRowMenu && <TableHead className="w-px"><span className="sr-only">Actions</span></TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {loading && Array.from({ length: Math.max(rows.data.length, 3) }, (_, i) => (
                            <TableRow key={`skeleton-${i}`} className="sunrice-row-in hover:bg-transparent" style={{ '--i': i } as React.CSSProperties} aria-hidden="true">
                                {bulkActions.length > 0 && <TableCell><Skeleton className="size-4" /></TableCell>}
                                {columns.map((column) => <TableCell key={column.name}><Skeleton className="h-4 w-[60%] max-w-48" /></TableCell>)}
                                {hasRowMenu && <TableCell />}
                            </TableRow>
                        ))}
                        {!loading && rows.data.map((row, i) => (
                            <TableRow key={row.id} className="sunrice-row-in" style={{ '--i': i } as React.CSSProperties} data-state={selected.includes(row.id) ? 'selected' : undefined}>
                                {bulkActions.length > 0 && (
                                    <TableCell>
                                        <Checkbox aria-label={`Select ${row.title}`} checked={selected.includes(row.id)}
                                            onCheckedChange={(checked) => setSelected((current) => checked ? [...current, row.id] : current.filter((id) => id !== row.id))} />
                                    </TableCell>
                                )}
                                {columns.map((column, index) => (
                                    <TableCell key={column.name}>
                                        <div className="max-w-80 truncate">
                                            {index === 0 && row.can.update ? (
                                                <Link href={`${base}/${row.id}/edit`} className="font-medium underline-offset-4 hover:underline">
                                                    <Cell column={column} value={row.cells[column.name] ?? null} />
                                                </Link>
                                            ) : <Cell column={column} value={row.cells[column.name] ?? null} />}
                                        </div>
                                    </TableCell>
                                ))}
                                {hasRowMenu && (
                                    <TableCell className="w-px text-right">
                                        {(row.can.update || row.can.delete || row.actions.length > 0) && (
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-8" aria-label={`Actions for ${row.title}`}><MoreHorizontal /></Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    {row.can.update && <DropdownMenuItem asChild><Link href={`${base}/${row.id}/edit`}>Edit</Link></DropdownMenuItem>}
                                                    {rowActions.filter((action) => row.actions.includes(action.key)).map((action) => (
                                                        <DropdownMenuItem key={action.key} variant={action.destructive ? 'destructive' : 'default'} onSelect={() => runRowAction(row, action)}>
                                                            {action.label}
                                                        </DropdownMenuItem>
                                                    ))}
                                                    {row.can.delete && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem variant="destructive" onSelect={() => deleteRow(row)}>Delete</DropdownMenuItem>
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
                                    {query.search || Object.keys(query.filters).length ? 'Nothing matches.' : `No ${resource.label.toLowerCase()} yet.`}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <div className="flex flex-wrap items-center justify-between gap-4 border-t px-6 py-3 text-sm text-muted-foreground">
                    <div className="flex items-center gap-4">
                        <span>{rows.total === 0 ? 'No rows' : `${rows.from}–${rows.to} of ${rows.total}`}</span>
                        {rows.total > perPageOptions[0]! && (
                            <Select value={String(rows.perPage)} onValueChange={(value) => visit({ per_page: Number(value) })}>
                                <SelectTrigger className="h-8 w-28" aria-label="Rows per page"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {perPageOptions.map((option) => <SelectItem key={option} value={String(option)}>{option} per page</SelectItem>)}
                                </SelectContent>
                            </Select>
                        )}
                    </div>
                    {rows.lastPage > 1 && (
                        <div className="flex items-center gap-2">
                            <span>Page {rows.currentPage} of {rows.lastPage}</span>
                            <PageLink url={rows.prevUrl} label="Previous page"><ChevronLeft /></PageLink>
                            <PageLink url={rows.nextUrl} label="Next page"><ChevronRight /></PageLink>
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
