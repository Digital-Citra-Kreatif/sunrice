import * as React from 'react';
import { ChevronLeft, Folder, ImagePlus, LoaderCircle, Search, Upload as UploadIcon, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { adminUrl } from '@/lib/admin-url';
import { fetchJson, RequestError, withQuery } from '@/lib/fetch-json';
import { cn } from '@/lib/utils';
import { uploadProblem } from './format';
import { FileIcon } from './media-details';
import type { FolderInfo, MediaItem } from './types';
import { t } from '@/lib/i18n';

interface Browse {
    folder: FolderInfo | null;
    path: FolderInfo[];
    folders: FolderInfo[];
    items: MediaItem[];
    nextPage: number | null;
    canUpload: boolean;
    upload: { maxKb: number; accept: string };
}

interface Props {
    id: string;
    value: number | number[] | null;
    multiple: boolean;
    max: number | null;
    types: string[];
    disabled: boolean;
    invalid: boolean;
    onChange: (value: number | number[] | null) => void;
}

/** A media field: the chosen files, and a dialog to choose or upload more. */
export function MediaPickerInput({ id, value, multiple, max, types, disabled, invalid, onChange }: Props) {
    const ids = React.useMemo(() => (Array.isArray(value) ? value : value ? [value] : []), [value]);
    const [chosen, setChosen] = React.useState<MediaItem[]>([]);
    const [open, setOpen] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    // Details of the chosen files, for their thumbnails.
    React.useEffect(() => {
        const missing = ids.filter((mediaId) => !chosen.some((item) => item.id === mediaId));
        if (missing.length === 0) return;
        fetchJson<{ items: MediaItem[] }>(withQuery(adminUrl('media/picker'), { ids: missing }))
            .then((data) => setChosen((list) => [...list, ...data.items]))
            .catch((e: Error) => setError(e.message));
    }, [ids]); // eslint-disable-line react-hooks/exhaustive-deps

    const shown = ids.map((mediaId) => ({ mediaId, item: chosen.find((item) => item.id === mediaId) ?? null }));
    const remove = (mediaId: number) => onChange(multiple ? ids.filter((other) => other !== mediaId) : null);
    const pick = (items: MediaItem[]) => {
        setChosen((list) => [...list, ...items.filter((item) => !list.some((other) => other.id === item.id))]);
        onChange(multiple ? [...ids, ...items.map((item) => item.id).filter((mediaId) => !ids.includes(mediaId))].slice(0, max ?? undefined) : (items[0]?.id ?? null));
        setOpen(false);
    };
    const full = multiple && max !== null && ids.length >= max;

    return (
        <div id={id} className={cn('grid gap-2', invalid && 'rounded-md ring-1 ring-destructive')}>
            {shown.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {shown.map(({ mediaId, item }) => (
                        <div key={mediaId} className="relative w-28 overflow-hidden rounded-md border bg-card">
                            <div className="flex aspect-square items-center justify-center bg-muted">
                                {item?.thumbnail ? <img src={item.thumbnail} alt={item.alt ?? ''} className="size-full object-cover" />
                                    : item ? <FileIcon mimeType={item.mimeType} className="size-8 text-muted-foreground" />
                                        : <span className="px-2 text-center text-xs text-muted-foreground">{t('Missing file')}</span>}
                            </div>
                            <p className="truncate px-1.5 py-1 text-xs">{item ? (item.title || item.filename) : `#${mediaId}`}</p>
                            {!disabled && (
                                <Button type="button" variant="secondary" size="icon" className="absolute top-1 right-1 size-6" aria-label={t('Remove')} onClick={() => remove(mediaId)}>
                                    <X className="size-3.5" />
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}
            {!disabled && !full && (
                <div>
                    <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)}>
                        <ImagePlus />{shown.length > 0 && !multiple ? t('Change') : t('Choose')}
                    </Button>
                </div>
            )}
            {error && <p className="text-sm text-destructive">{error}</p>}
            {open && (
                <PickerDialog multiple={multiple} remaining={max === null ? null : max - ids.length} types={types}
                    exclude={ids} onClose={() => setOpen(false)} onPick={pick} />
            )}
        </div>
    );
}

function PickerDialog({ multiple, remaining, types, exclude, onClose, onPick }: {
    multiple: boolean;
    remaining: number | null;
    types: string[];
    exclude: number[];
    onClose: () => void;
    onPick: (items: MediaItem[]) => void;
}) {
    const [folder, setFolder] = React.useState<number | null>(null);
    const [search, setSearch] = React.useState('');
    const [query, setQuery] = React.useState('');
    const [data, setData] = React.useState<Browse | null>(null);
    const [items, setItems] = React.useState<MediaItem[]>([]);
    const [selected, setSelected] = React.useState<MediaItem[]>([]);
    const [loading, setLoading] = React.useState(false);
    const [uploading, setUploading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);
    const fileInput = React.useRef<HTMLInputElement>(null);

    const load = React.useCallback(async (page: number) => {
        setLoading(true);
        setError(null);
        try {
            const next = await fetchJson<Browse>(withQuery(adminUrl('media/picker'), { folder, search: query, types, page }));
            setData(next);
            setItems((list) => (page === 1 ? next.items : [...list, ...next.items]));
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setLoading(false);
        }
    }, [folder, query, types]);

    React.useEffect(() => { void load(1); }, [load]);

    const toggle = (item: MediaItem) => {
        if (!multiple) {
            setSelected([item]);
            return;
        }
        setSelected((list) => (list.some((other) => other.id === item.id)
            ? list.filter((other) => other.id !== item.id)
            : remaining !== null && list.length >= remaining ? list : [...list, item]));
    };

    const upload = async (files: FileList | null) => {
        if (!files || !data) return;
        setUploading(true);
        setError(null);
        const added: MediaItem[] = [];
        for (const file of Array.from(files)) {
            const problem = uploadProblem(file, data.upload.maxKb, data.upload.accept, t);
            if (problem) {
                setError(`${file.name}: ${problem}`);
                continue;
            }
            const body = new FormData();
            body.append('file', file);
            if (folder) body.append('folder_id', String(folder));
            try {
                added.push(await fetchJson<MediaItem>(adminUrl('media'), { method: 'POST', body }));
            } catch (e) {
                setError(`${file.name}: ${e instanceof RequestError ? e.message : t('The upload failed.')}`);
            }
        }
        setUploading(false);
        setItems((list) => [...added, ...list]);
        added.forEach((item) => toggle(item));
    };

    const typeAccept = data?.upload.accept;

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{multiple ? t('Choose files') : t('Choose a file')}</DialogTitle>
                    <DialogDescription>{data?.canUpload ? t('From the media library, or upload new ones.') : t('From the media library.')}</DialogDescription>
                </DialogHeader>
                <div className="flex flex-wrap items-center gap-2">
                    {data?.folder && !query && (
                        <Button type="button" variant="ghost" size="sm" onClick={() => setFolder(data.path.at(-2)?.id ?? null)}>
                            <ChevronLeft />{data.path.at(-2)?.name ?? t('Media')}
                        </Button>
                    )}
                    <form className="relative min-w-48 flex-1" onSubmit={(e) => { e.preventDefault(); setQuery(search.trim()); }}>
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input type="search" aria-label={t('Search media')} placeholder={t('Search everywhere…')} className="pl-8" value={search}
                            onChange={(e) => { setSearch(e.target.value); if (e.target.value === '') setQuery(''); }} />
                    </form>
                    {data?.canUpload && (
                        <>
                            <input ref={fileInput} type="file" multiple={multiple} accept={typeAccept} className="hidden" aria-label={t('Files to upload')}
                                onChange={(e) => { void upload(e.target.files); e.target.value = ''; }} />
                            <Button type="button" variant="outline" disabled={uploading} onClick={() => fileInput.current?.click()}>
                                {uploading ? <LoaderCircle className="animate-spin" /> : <UploadIcon />}{t('Upload')}
                            </Button>
                        </>
                    )}
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}

                <div className="-mx-1 min-h-48 flex-1 overflow-y-auto px-1">
                    {data && data.folders.length > 0 && (
                        <div className="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {data.folders.map((f) => (
                                <button key={f.id} type="button" onClick={() => setFolder(f.id)}
                                    className="flex items-center gap-2 rounded-md border px-3 py-2 text-left text-sm hover:bg-muted/50">
                                    <Folder className="size-4 shrink-0 text-muted-foreground" /><span className="truncate">{f.name}</span>
                                </button>
                            ))}
                        </div>
                    )}
                    {items.length === 0 && !loading ? (
                        <p className="py-12 text-center text-sm text-muted-foreground">{query ? t('Nothing matches.') : t('No files here.')}</p>
                    ) : (
                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                            {items.map((item) => {
                                const isSelected = selected.some((other) => other.id === item.id);
                                const taken = exclude.includes(item.id);
                                return (
                                    <button key={item.id} type="button" disabled={taken} onClick={() => toggle(item)} aria-pressed={isSelected}
                                        aria-label={isSelected ? t('Unselect :name', { name: item.title || item.filename }) : t('Select :name', { name: item.title || item.filename })}
                                        className={cn('overflow-hidden rounded-md border text-left disabled:opacity-40', isSelected && 'ring-2 ring-primary')}>
                                        <div className="flex aspect-square items-center justify-center bg-muted">
                                            {item.thumbnail ? <img src={item.thumbnail} alt="" loading="lazy" className="size-full object-cover" />
                                                : <FileIcon mimeType={item.mimeType} className="size-8 text-muted-foreground" />}
                                        </div>
                                        <p className="truncate px-1.5 py-1 text-xs">{item.title || item.filename}</p>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                    {data?.nextPage && (
                        <div className="mt-3 text-center">
                            <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load(data.nextPage!)}>{t('Load more')}</Button>
                        </div>
                    )}
                    {loading && items.length === 0 && <LoaderCircle className="mx-auto mt-8 animate-spin text-muted-foreground" />}
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>{t('Cancel')}</Button>
                    <Button type="button" disabled={selected.length === 0} onClick={() => onPick(selected)}>
                        {multiple && selected.length > 1 ? t('Choose :count', { count: selected.length }) : t('Choose')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
