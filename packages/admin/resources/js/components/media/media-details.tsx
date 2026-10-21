import * as React from 'react';
import { router, useForm } from '@inertiajs/react';
import { Copy, FileText, Music, RotateCcw, Trash2, Upload as UploadIcon, Video } from 'lucide-react';
import { toast } from 'sonner';
import { InputError } from '@/components/app/input-error';
import { useConfirm } from '@/components/resources/confirm';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { adminUrl } from '@/lib/admin-url';
import { copyText } from '@/lib/clipboard';
import { fetchJson } from '@/lib/fetch-json';
import { formatBytes, mediaKind } from './format';
import type { MediaItem, MediaUsageInfo } from './types';
import { panelLocale, t } from '@/lib/i18n';

interface Props {
    item: MediaItem | null;
    can: { update: boolean; delete: boolean };
    accept: string;
    /** The content's other languages: alt text and caption in each. */
    locales?: { code: string; name: string }[];
    onClose: () => void;
}

/** A file's preview, details and actions, in a side sheet. */
export function MediaDetails({ item, can, accept, locales = [], onClose }: Props) {
    return (
        <Sheet open={item !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                {item && <Details key={item.id} item={item} can={can} accept={accept} locales={locales} onClose={onClose} />}
            </SheetContent>
        </Sheet>
    );
}

function Details({ item, can, accept, locales = [], onClose }: Props & { item: MediaItem }) {
    const [confirm, confirmDialog] = useConfirm();
    const replaceInput = React.useRef<HTMLInputElement>(null);
    const form = useForm({
        title: item.title ?? '', alt: item.alt ?? '', caption: item.caption ?? '',
        translations: Object.fromEntries(locales.map(({ code }) => [code, { alt: item.translations?.[code]?.alt ?? '', caption: item.translations?.[code]?.caption ?? '' }])),
    });
    const setTranslation = (code: string, key: 'alt' | 'caption', value: string) =>
        form.setData('translations', { ...form.data.translations, [code]: { alt: '', caption: '', ...form.data.translations[code], [key]: value } });
    const url = adminUrl(`media/${item.id}`);
    const kind = mediaKind(item.mimeType);
    const editable = can.update && !item.trashed;
    const [usages, setUsages] = React.useState<MediaUsageInfo[]>([]);
    React.useEffect(() => {
        if (!item.usages) return;
        fetchJson<{ usages: MediaUsageInfo[] }>(`${url}/usages`).then((r) => setUsages(r.usages)).catch(() => setUsages([]));
    }, [item.usages, url]);
    const usedList = usages.slice(0, 5).map((u) => u.label).join(', ');
    const usedBy = usages.length === 0 ? '' : ` ${usages.length > 5 ? t('It\'s used by :list and :count more.', { list: usedList, count: usages.length - 5 }) : t('It\'s used by :list.', { list: usedList })}`;
    const trash = () => {
        const go = () => router.delete(url, { preserveScroll: true, onSuccess: onClose });
        if (usages.length > 0) {
            confirm(`${t('Move :name to the trash?', { name: item.filename })}${usedBy} ${t('You can restore it.')}`, go, true);
        } else {
            go();
        }
    };

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(url, { preserveScroll: true, preserveState: true });
    };
    const replace = (file: File | undefined) => file && router.post(`${url}/replace`, { file }, { forceFormData: true, preserveScroll: true, preserveState: true });
    const copy = () =>
        copyText(new URL(item.url, window.location.href).toString())
            .then(() => toast.success(t('Link copied.')))
            .catch(() => toast.error(t('Couldn\'t copy the link.')));

    return (
        <>
            {confirmDialog}
            <SheetHeader>
                <SheetTitle className="truncate pr-6">{item.title || item.filename}</SheetTitle>
                <SheetDescription className="truncate">{item.filename}</SheetDescription>
            </SheetHeader>
            <div className="flex flex-col gap-5 px-4 pb-6">
                <div className="flex aspect-video items-center justify-center overflow-hidden rounded-md border bg-muted">
                    {kind === 'image' && <img src={item.url} alt={item.alt ?? ''} className="max-h-full max-w-full object-contain" />}
                    {kind === 'video' && <video src={item.url} controls className="max-h-full max-w-full" />}
                    {kind === 'audio' && (
                        <div className="flex w-full flex-col items-center gap-3 p-4"><Music className="size-10 text-muted-foreground" /><audio src={item.url} controls className="w-full" /></div>
                    )}
                    {kind === 'document' && <FileText className="size-12 text-muted-foreground" />}
                </div>

                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt className="text-muted-foreground">{t('Type')}</dt><dd className="truncate">{item.mimeType}</dd>
                    <dt className="text-muted-foreground">{t('Size')}</dt><dd>{formatBytes(item.size)}</dd>
                    {item.width && item.height && (<><dt className="text-muted-foreground">{t('Dimensions')}</dt><dd>{item.width} × {item.height}</dd></>)}
                    {item.createdAt && (<><dt className="text-muted-foreground">{t('Uploaded')}</dt><dd>{new Date(item.createdAt).toLocaleString(panelLocale())}</dd></>)}
                </dl>

                {usages.length > 0 && (
                    <div className="grid gap-1 text-sm">
                        <p className="font-medium">{t('Used by')}</p>
                        <ul className="grid gap-1">
                            {usages.map((u, i) => (
                                <li key={i} className="truncate">
                                    {u.url ? <a href={u.url} className="underline-offset-4 hover:underline">{u.label}</a> : u.label}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={copy}><Copy />{t('Copy link')}</Button>
                    {kind === 'video' || kind === 'audio' || kind === 'document' ? (
                        <Button variant="outline" size="sm" asChild><a href={item.url} target="_blank" rel="noreferrer"><FileText />{t('Open')}</a></Button>
                    ) : null}
                    {editable && (
                        <>
                            <input ref={replaceInput} type="file" accept={accept} className="hidden" onChange={(e) => replace(e.target.files?.[0])} />
                            <Button type="button" variant="outline" size="sm" onClick={() => replaceInput.current?.click()}><UploadIcon />{t('Replace file')}</Button>
                        </>
                    )}
                </div>

                {editable ? (
                    <form onSubmit={save} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="media-title">{t('Title')}</Label>
                            <Input id="media-title" value={form.data.title} maxLength={250} onChange={(e) => form.setData('title', e.target.value)} />
                            <InputError message={form.errors.title} />
                        </div>
                        {kind === 'image' && (
                            <div className="grid gap-2">
                                <Label htmlFor="media-alt">{t('Alternative text')}</Label>
                                <Input id="media-alt" value={form.data.alt} maxLength={250} onChange={(e) => form.setData('alt', e.target.value)} />
                                <p className="text-sm text-muted-foreground">{t('What the image shows, for people who can\'t see it.')}</p>
                                <InputError message={form.errors.alt} />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="media-caption">{t('Caption')}</Label>
                            <Textarea id="media-caption" rows={3} value={form.data.caption} maxLength={2000} onChange={(e) => form.setData('caption', e.target.value)} />
                            <InputError message={form.errors.caption} />
                        </div>
                        {locales.map(({ code, name }) => (
                            <fieldset key={code} className="grid gap-3 rounded-md border p-3">
                                <legend className="px-1 text-sm font-medium">In {name}</legend>
                                {kind === 'image' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor={`media-alt-${code}`}>{t('Alternative text (:language)', { language: name })}</Label>
                                        <Input id={`media-alt-${code}`} value={form.data.translations[code]?.alt ?? ''} maxLength={250} placeholder={form.data.alt}
                                            onChange={(e) => setTranslation(code, 'alt', e.target.value)} />
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor={`media-caption-${code}`}>Caption ({name})</Label>
                                    <Textarea id={`media-caption-${code}`} rows={2} value={form.data.translations[code]?.caption ?? ''} maxLength={2000} placeholder={form.data.caption}
                                        onChange={(e) => setTranslation(code, 'caption', e.target.value)} />
                                </div>
                                <p className="text-sm text-muted-foreground">Empty: as above.</p>
                            </fieldset>
                        ))}
                        <div className="flex justify-between gap-2">
                            {can.delete ? (
                                <Button type="button" variant="outline" className="text-destructive" onClick={trash}>
                                    <Trash2 />{t('Move to trash')}
                                </Button>
                            ) : <span />}
                            <Button type="submit" disabled={form.processing || !form.isDirty}>{t('Save')}</Button>
                        </div>
                    </form>
                ) : item.trashed && can.delete ? (
                    <div className="flex justify-between gap-2">
                        <Button type="button" variant="outline" onClick={() => router.post(`${url}/restore`, {}, { preserveScroll: true, onSuccess: onClose })}><RotateCcw />{t('Restore')}</Button>
                        <Button type="button" variant="destructive" onClick={() => confirm(`${t('Delete :name for good?', { name: item.filename })}${usedBy || ` ${t('Pages using it will show a broken link.')}`}`, () => router.delete(`${url}/force`, { data: { force: usages.length > 0 }, preserveScroll: true, onSuccess: onClose }), true)}>
                            <Trash2 />{t('Delete for good')}
                        </Button>
                    </div>
                ) : null}
            </div>
        </>
    );
}

/** The icon for a file that has no thumbnail. */
export function FileIcon({ mimeType, className }: { mimeType: string; className?: string }) {
    const kind = mediaKind(mimeType);
    const Icon = kind === 'video' ? Video : kind === 'audio' ? Music : FileText;
    return <Icon className={className} />;
}
