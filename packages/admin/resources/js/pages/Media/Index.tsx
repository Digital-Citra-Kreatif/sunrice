import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft, ChevronRight, CircleAlert, Folder, FolderPlus, LoaderCircle, MoreHorizontal, RotateCcw, Search, Trash2, Upload as UploadIcon, X,
} from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import { InputError } from '@/components/app/input-error';
import { formatBytes } from '@/components/media/format';
import { FileIcon, MediaDetails } from '@/components/media/media-details';
import type { FolderInfo, MediaItem, MediaQuery, TreeFolder } from '@/components/media/types';
import { useUploads } from '@/components/media/use-uploads';
import { useConfirm } from '@/components/resources/confirm';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { adminUrl } from '@/lib/admin-url';
import { cn } from '@/lib/utils';

interface Props {
    query: MediaQuery;
    folder: FolderInfo | null;
    path: FolderInfo[];
    folders: FolderInfo[];
    tree: TreeFolder[];
    media: { data: MediaItem[]; total: number; currentPage: number; lastPage: number; prevUrl: string | null; nextUrl: string | null };
    can: { upload: boolean; update: boolean; delete: boolean };
    upload: { maxKb: number; accept: string };
    /** The public disk's storage link is missing (php artisan storage:link). */
    storageLinkMissing?: boolean;
}

const ALL = '__all';
const TOP = '__top';
const TYPES = [{ value: 'image', label: 'Images' }, { value: 'video', label: 'Videos' }, { value: 'audio', label: 'Audio' }, { value: 'document', label: 'Documents' }];

export default function MediaIndex({ query, folder, path, folders, tree, media, can, upload, storageLinkMissing = false }: Props) {
    const base = adminUrl('media');
    useBreadcrumbs(path.length === 0 ? [{ label: 'Media' }] : [{ label: 'Media', href: base }, ...path.map((f, i) => (i === path.length - 1 ? { label: f.name } : { label: f.name, href: `${base}?folder=${f.id}` }))]);
    const [confirm, confirmDialog] = useConfirm();
    const [search, setSearch] = React.useState(query.search);
    const [selected, setSelected] = React.useState<number[]>([]);
    const [openId, setOpenId] = React.useState<number | null>(null);
    const [folderDialog, setFolderDialog] = React.useState<{ folder: FolderInfo | null } | null>(null);
    const [moving, setMoving] = React.useState(false);
    const [dragging, setDragging] = React.useState(false);
    const fileInput = React.useRef<HTMLInputElement>(null);
    const { uploads, add, clear } = useUploads(folder?.id ?? null, upload);

    React.useEffect(() => setSelected([]), [media.data]);
    const open = media.data.find((item) => item.id === openId) ?? null;
    const canUpload = can.upload && !query.trash;

    const visit = (changes: Partial<MediaQuery>) => {
        const next = { ...query, ...changes };
        router.get(base, {
            ...(next.folder && !next.search && !next.trash ? { folder: next.folder } : {}),
            ...(next.search ? { search: next.search } : {}),
            ...(next.type ? { type: next.type } : {}),
            ...(next.trash ? { trash: 1 } : {}),
        }, { preserveState: true, preserveScroll: true });
    };
    const bulk = (action: 'move' | 'trash' | 'restore' | 'delete', extra: Record<string, unknown> = {}) =>
        router.post(`${base}/bulk`, { action, ids: selected, ...extra }, { preserveScroll: true, onSuccess: () => setSelected([]) });
    const toggle = (id: number, checked: boolean) => setSelected((ids) => (checked ? [...ids, id] : ids.filter((other) => other !== id)));

    const drop = (event: React.DragEvent) => {
        event.preventDefault();
        setDragging(false);
        if (canUpload && event.dataTransfer.files.length > 0) add(event.dataTransfer.files);
    };

    return (
        <div
            className="relative flex flex-col gap-4"
            onDragOver={(e) => { if (canUpload && e.dataTransfer.types.includes('Files')) { e.preventDefault(); setDragging(true); } }}
            onDragLeave={(e) => { if (e.currentTarget === e.target) setDragging(false); }}
            onDrop={drop}
        >
            <Head title={folder ? `${folder.name} · Media` : 'Media'} />
            {confirmDialog}

            <PageHeader
                title={query.trash ? 'Trash' : (folder?.name ?? 'Media')}
                actions={
                    <>
                        {can.update && !query.trash && !query.search && (
                            <Button variant="outline" onClick={() => setFolderDialog({ folder: null })}><FolderPlus />New folder</Button>
                        )}
                        {canUpload && (
                            <>
                                <input ref={fileInput} type="file" multiple accept={upload.accept} className="hidden" aria-label="Files to upload"
                                    onChange={(e) => { if (e.target.files) add(e.target.files); e.target.value = ''; }} />
                                <Button onClick={() => fileInput.current?.click()}><UploadIcon />Upload</Button>
                            </>
                        )}
                    </>
                }
            />

            {storageLinkMissing && (
                <div role="alert" className="rounded-md border border-amber-500/40 bg-amber-500/5 p-3 text-sm">
                    <p className="font-medium">Files can't be shown: the storage link is missing.</p>
                    <p className="text-muted-foreground">Run <code className="rounded bg-muted px-1">php artisan storage:link</code> on the server, so the public disk's files are served from <code className="rounded bg-muted px-1">public/storage</code>.</p>
                </div>
            )}

            <div className="flex flex-wrap items-center gap-2">
                <form className="relative w-full sm:w-72" onSubmit={(e) => { e.preventDefault(); visit({ search }); }}>
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input type="search" aria-label="Search media" placeholder="Search everywhere…" className="pl-8" value={search}
                        onChange={(e) => { setSearch(e.target.value); if (e.target.value === '' && query.search) visit({ search: '' }); }} />
                </form>
                <Select value={query.type ?? ALL} onValueChange={(value) => visit({ type: value === ALL ? null : (value as MediaQuery['type']) })}>
                    <SelectTrigger aria-label="Type" className="w-36"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>All types</SelectItem>
                        {TYPES.map((type) => <SelectItem key={type.value} value={type.value}>{type.label}</SelectItem>)}
                    </SelectContent>
                </Select>
                {can.delete && (
                    <Button variant={query.trash ? 'secondary' : 'ghost'} onClick={() => visit({ trash: !query.trash, folder: null })}>
                        <Trash2 />{query.trash ? 'Leave the trash' : 'Trash'}
                    </Button>
                )}
            </div>

            {uploads.length > 0 && (
                <div className="rounded-md border bg-card p-3 text-sm">
                    <div className="mb-2 flex items-center justify-between">
                        <span className="font-medium">Uploads</span>
                        <Button variant="ghost" size="icon" className="size-7" aria-label="Clear the finished uploads" onClick={clear}><X /></Button>
                    </div>
                    <ul className="grid gap-1.5">
                        {uploads.map((u) => (
                            <li key={u.key} className="flex items-center gap-2">
                                {u.status === 'failed' ? <CircleAlert className="size-4 shrink-0 text-destructive" /> : u.status === 'done' ? <span className="size-4 shrink-0 text-center">✓</span> : <LoaderCircle className="size-4 shrink-0 animate-spin" />}
                                <span className="min-w-0 flex-1 truncate">{u.name}</span>
                                {u.status === 'failed' ? <span className="text-destructive">{u.error}</span>
                                    : u.status === 'uploading' ? <span className="text-muted-foreground tabular-nums">{u.progress}%</span>
                                        : u.status === 'waiting' ? <span className="text-muted-foreground">Waiting</span> : null}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {selected.length > 0 && (
                <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/50 px-3 py-2 text-sm">
                    <span className="mr-auto">{selected.length} selected</span>
                    {query.trash ? (
                        <>
                            <Button size="sm" variant="outline" onClick={() => bulk('restore')}><RotateCcw />Restore</Button>
                            <Button size="sm" variant="destructive" onClick={() => confirm(`Delete ${selected.length} ${selected.length === 1 ? 'file' : 'files'} for good?`, () => bulk('delete'), true)}><Trash2 />Delete for good</Button>
                        </>
                    ) : (
                        <>
                            {can.update && <Button size="sm" variant="outline" onClick={() => setMoving(true)}><Folder />Move</Button>}
                            {can.delete && <Button size="sm" variant="outline" className="text-destructive" onClick={() => bulk('trash')}><Trash2 />Move to trash</Button>}
                        </>
                    )}
                    <Button size="sm" variant="ghost" onClick={() => setSelected([])}>Clear</Button>
                </div>
            )}

            {folders.length > 0 && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    {folders.map((f) => (
                        <div key={f.id} className="group flex items-center gap-2 rounded-lg border bg-card px-3 py-2.5 transition-colors duration-150 ease-out-quart hover:bg-muted/40">
                            <Link href={`${base}?folder=${f.id}`} className="flex min-w-0 flex-1 items-center gap-2 text-sm font-medium">
                                <Folder className="size-4 shrink-0 text-muted-foreground" /><span className="truncate">{f.name}</span>
                            </Link>
                            {can.update && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="icon" className="size-7" aria-label={`Actions for ${f.name}`}><MoreHorizontal /></Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem onSelect={() => setFolderDialog({ folder: f })}>Rename</DropdownMenuItem>
                                        <DropdownMenuItem variant="destructive" onSelect={() => confirm(`Delete the folder "${f.name}"? What's in it moves up here.`, () => router.delete(`${base}/folders/${f.id}`, { preserveScroll: true }), true)}>
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {media.data.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                    {query.trash ? 'The trash is empty.' : query.search || query.type ? 'Nothing matches.' : canUpload ? 'No files here yet. Drop files on this page or use Upload.' : 'No files here yet.'}
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                    {media.data.map((item, i) => {
                        const checked = selected.includes(item.id);
                        return (
                            <div key={item.id} style={{ '--i': i } as React.CSSProperties}
                                className={cn('sunrice-row-in group relative overflow-hidden rounded-lg border bg-card transition-[transform,box-shadow,border-color] duration-200 ease-out-quart hover:-translate-y-0.5 hover:border-border hover:shadow-card', checked && 'ring-2 ring-primary')}>
                                <button type="button" className="block w-full text-left" onClick={() => setOpenId(item.id)} aria-label={`Open ${item.title || item.filename}`}>
                                    <div className="flex aspect-square items-center justify-center bg-muted">
                                        {item.thumbnail
                                            ? <img src={item.thumbnail} alt="" loading="lazy" className="size-full object-cover" />
                                            : <FileIcon mimeType={item.mimeType} className="size-10 text-muted-foreground" />}
                                    </div>
                                    <div className="px-2 py-1.5">
                                        <p className="truncate text-sm">{item.title || item.filename}</p>
                                        <p className="text-xs text-muted-foreground">{formatBytes(item.size)}</p>
                                    </div>
                                </button>
                                {(can.update || can.delete) && (
                                    <Checkbox checked={checked} onCheckedChange={(value) => toggle(item.id, value === true)} aria-label={`Select ${item.title || item.filename}`}
                                        className={cn('absolute top-2 left-2 bg-background', !checked && selected.length === 0 && 'opacity-0 group-hover:opacity-100 focus-visible:opacity-100')} />
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {media.lastPage > 1 && (
                <div className="flex items-center justify-end gap-2 text-sm text-muted-foreground">
                    <span>Page {media.currentPage} of {media.lastPage} · {media.total} files</span>
                    <Button variant="outline" size="icon" className="size-8" disabled={!media.prevUrl} asChild={!!media.prevUrl} aria-label="Previous page">
                        {media.prevUrl ? <Link href={media.prevUrl} preserveScroll><ChevronLeft /></Link> : <ChevronLeft />}
                    </Button>
                    <Button variant="outline" size="icon" className="size-8" disabled={!media.nextUrl} asChild={!!media.nextUrl} aria-label="Next page">
                        {media.nextUrl ? <Link href={media.nextUrl} preserveScroll><ChevronRight /></Link> : <ChevronRight />}
                    </Button>
                </div>
            )}

            {dragging && (
                <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-lg border-2 border-dashed border-primary bg-background/80 text-lg font-medium">
                    Drop to upload{folder ? ` to ${folder.name}` : ''}
                </div>
            )}

            <MediaDetails item={open} can={can} accept={upload.accept} onClose={() => setOpenId(null)} />
            <FolderDialog state={folderDialog} parentId={folder?.id ?? null} onClose={() => setFolderDialog(null)} />
            <MoveDialog open={moving} tree={tree} onClose={() => setMoving(false)} onMove={(folderId) => { bulk('move', { folder_id: folderId }); setMoving(false); }} />
        </div>
    );
}

/** Create a folder here, or rename one. */
function FolderDialog({ state, parentId, onClose }: { state: { folder: FolderInfo | null } | null; parentId: number | null; onClose: () => void }) {
    const [name, setName] = React.useState('');
    const [error, setError] = React.useState<string | undefined>();
    const [processing, setProcessing] = React.useState(false);
    React.useEffect(() => { setName(state?.folder?.name ?? ''); setError(undefined); }, [state]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: (errors: Record<string, string>) => setError(errors.name),
        };
        if (state?.folder) {
            router.put(adminUrl(`media/folders/${state.folder.id}`), { name }, options);
        } else {
            router.post(adminUrl('media/folders'), { name, parent_id: parentId }, options);
        }
    };

    return (
        <Dialog open={state !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader><DialogTitle>{state?.folder ? 'Rename folder' : 'New folder'}</DialogTitle></DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="folder-name">Name</Label>
                        <Input id="folder-name" autoFocus required maxLength={100} value={name} onChange={(e) => setName(e.target.value)} />
                        <InputError message={error} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}>{state?.folder ? 'Rename' : 'Create'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Pick the folder to move the selected files to. */
function MoveDialog({ open, tree, onClose, onMove }: { open: boolean; tree: TreeFolder[]; onClose: () => void; onMove: (folderId: number | null) => void }) {
    const [target, setTarget] = React.useState(TOP);
    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader><DialogTitle>Move to folder</DialogTitle></DialogHeader>
                <Select value={target} onValueChange={setTarget}>
                    <SelectTrigger aria-label="Folder" className="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value={TOP}>Media (top level)</SelectItem>
                        {tree.map((f) => <SelectItem key={f.id} value={String(f.id)}><span style={{ paddingLeft: `${f.depth}rem` }}>{f.name}</span></SelectItem>)}
                    </SelectContent>
                </Select>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                    <Button type="button" onClick={() => onMove(target === TOP ? null : Number(target))}>Move</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
