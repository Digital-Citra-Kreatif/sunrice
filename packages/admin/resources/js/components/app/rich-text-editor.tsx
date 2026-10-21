import * as React from 'react';
import { EditorContent, useEditor, useEditorState, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import {
    Bold, Code, Heading2, Heading3, ImagePlus, Italic, Link2, List, ListOrdered, Minus, Quote, Redo2, Strikethrough, Underline, Undo2,
} from 'lucide-react';
import { MediaPickerInput } from '@/components/media/media-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { adminUrl } from '@/lib/admin-url';
import { isSafeUrl } from '@/lib/safe-url';
import { cn } from '@/lib/utils';
import type { RichTextEditorProps } from './rich-text';
import { t } from '@/lib/i18n';

/** Images keep their media library id, so the server knows which files a text uses. */
const LibraryImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            'data-media-id': { default: null },
        };
    },
});

/**
 * The rich text editor (TipTap): the formatting the servers keep when they
 * clean the HTML (headings, lists, quotes, code, links, library images).
 * Loaded on demand through RichTextEditor in rich-text.tsx.
 */
export function RichTextEditor({ id, value, onChange, invalid = false, labelledBy, images = true, disabled = false }: RichTextEditorProps) {
    const [dialog, setDialog] = React.useState<'link' | 'image' | null>(null);
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3, 4] }, link: { openOnClick: false, autolink: true } }),
            LibraryImage,
        ],
        content: value,
        editable: !disabled,
        editorProps: {
            attributes: {
                id,
                class: 'sunrice-prose',
                role: 'textbox',
                'aria-multiline': 'true',
                ...(labelledBy ? { 'aria-labelledby': labelledBy } : {}),
                ...(invalid ? { 'aria-invalid': 'true' } : {}),
            },
        },
        onUpdate: ({ editor: e }) => onChange(e.isEmpty ? null : e.getHTML()),
    });

    return (
        <div className={cn('rounded-lg border border-input/80 bg-card shadow-xs transition-[border-color,box-shadow] duration-150 ease-out-quart focus-within:border-ring/70 focus-within:ring-4 focus-within:ring-ring-glow dark:bg-input/20', invalid && 'border-destructive focus-within:ring-destructive/20', disabled && 'opacity-60')}>
            {editor && !disabled && <Toolbar editor={editor} onLink={() => setDialog('link')} onImage={images ? () => setDialog('image') : null} />}
            <EditorContent editor={editor} />
            {editor && <LinkDialog editor={editor} open={dialog === 'link'} onClose={() => setDialog(null)} />}
            {editor && <ImageDialog editor={editor} open={dialog === 'image'} onClose={() => setDialog(null)} />}
        </div>
    );
}

function Toolbar({ editor, onLink, onImage }: { editor: Editor; onLink: () => void; onImage: (() => void) | null }) {
    const state = useEditorState({
        editor,
        selector: ({ editor: e }) => ({
            bold: e.isActive('bold'), italic: e.isActive('italic'), underline: e.isActive('underline'), strike: e.isActive('strike'),
            code: e.isActive('code'), h2: e.isActive('heading', { level: 2 }), h3: e.isActive('heading', { level: 3 }),
            bullet: e.isActive('bulletList'), ordered: e.isActive('orderedList'), quote: e.isActive('blockquote'), link: e.isActive('link'),
            undo: e.can().undo(), redo: e.can().redo(),
        }),
    });
    const chain = () => editor.chain().focus();

    return (
        <div role="toolbar" aria-label={t('Formatting')} className="flex flex-wrap gap-0.5 border-b p-1">
            <Tool label={t('Bold')} active={state.bold} onClick={() => chain().toggleBold().run()}><Bold /></Tool>
            <Tool label={t('Italic')} active={state.italic} onClick={() => chain().toggleItalic().run()}><Italic /></Tool>
            <Tool label={t('Underline')} active={state.underline} onClick={() => chain().toggleUnderline().run()}><Underline /></Tool>
            <Tool label={t('Strikethrough')} active={state.strike} onClick={() => chain().toggleStrike().run()}><Strikethrough /></Tool>
            <Tool label={t('Code')} active={state.code} onClick={() => chain().toggleCode().run()}><Code /></Tool>
            <Separator />
            <Tool label={t('Heading')} active={state.h2} onClick={() => chain().toggleHeading({ level: 2 }).run()}><Heading2 /></Tool>
            <Tool label={t('Subheading')} active={state.h3} onClick={() => chain().toggleHeading({ level: 3 }).run()}><Heading3 /></Tool>
            <Tool label={t('Bulleted list')} active={state.bullet} onClick={() => chain().toggleBulletList().run()}><List /></Tool>
            <Tool label={t('Numbered list')} active={state.ordered} onClick={() => chain().toggleOrderedList().run()}><ListOrdered /></Tool>
            <Tool label={t('Quote')} active={state.quote} onClick={() => chain().toggleBlockquote().run()}><Quote /></Tool>
            <Tool label={t('Divider')} onClick={() => chain().setHorizontalRule().run()}><Minus /></Tool>
            <Separator />
            <Tool label={t('Link')} active={state.link} onClick={onLink}><Link2 /></Tool>
            {onImage && <Tool label={t('Image from the media library')} onClick={onImage}><ImagePlus /></Tool>}
            <Separator />
            <Tool label={t('Undo')} disabled={!state.undo} onClick={() => chain().undo().run()}><Undo2 /></Tool>
            <Tool label={t('Redo')} disabled={!state.redo} onClick={() => chain().redo().run()}><Redo2 /></Tool>
        </div>
    );
}

function Separator() {
    return <span aria-hidden className="mx-1 my-1 w-px bg-border" />;
}

function Tool({ label, active, disabled, onClick, children }: { label: string; active?: boolean; disabled?: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button type="button" variant={active ? 'secondary' : 'ghost'} size="icon" className="size-8" aria-label={label}
                    aria-pressed={active === undefined ? undefined : active} disabled={disabled} onClick={onClick}
                    // A mouse click keeps the text's focus and selection, so typing goes on where it was.
                    onMouseDown={(e: React.MouseEvent) => e.preventDefault()}>
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

function LinkDialog({ editor, open, onClose }: { editor: Editor; open: boolean; onClose: () => void }) {
    const [url, setUrl] = React.useState('');
    const [newTab, setNewTab] = React.useState(false);
    React.useEffect(() => {
        if (!open) return;
        const attributes = editor.getAttributes('link');
        setUrl(typeof attributes.href === 'string' ? attributes.href : '');
        setNewTab(attributes.target === '_blank');
    }, [open, editor]);
    const safe = url.trim() === '' || isSafeUrl(url.trim());

    const apply = () => {
        const href = url.trim();
        const chain = editor.chain().focus().extendMarkRange('link');
        (href === '' ? chain.unsetLink() : chain.setLink({ href, target: newTab ? '_blank' : null })).run();
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={(next: boolean) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Link')}</DialogTitle>
                    <DialogDescription>{t('A web address, a path on this site (/about), mailto: or tel:. Empty removes the link.')}</DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-3">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="rich-link-url">{t('Address')}</Label>
                        <Input id="rich-link-url" value={url} placeholder="https://…" aria-invalid={!safe}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setUrl(e.target.value)}
                            onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter') { e.preventDefault(); if (safe) apply(); } }} />
                        {!safe && <p className="text-sm text-destructive">{t('This address could run code; use https://, a path, mailto: or tel:.')}</p>}
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={newTab} onCheckedChange={(checked) => setNewTab(checked === true)} /> {t('Open in a new tab')}
                    </label>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>{t('Cancel')}</Button>
                    <Button type="button" disabled={!safe} onClick={apply}>{t('Apply')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

interface PickedMedia { id: number; url: string; alt: string | null; title: string | null; width: number | null; height: number | null }

function ImageDialog({ editor, open, onClose }: { editor: Editor; open: boolean; onClose: () => void }) {
    const [id, setId] = React.useState<number | null>(null);
    const [error, setError] = React.useState<string | null>(null);
    React.useEffect(() => { if (open) { setId(null); setError(null); } }, [open]);

    const insert = async () => {
        if (id === null) return;
        try {
            const response = await fetch(`${adminUrl('media/picker')}?ids[]=${id}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const item = ((await response.json()) as { items?: PickedMedia[] }).items?.[0];
            if (!response.ok || !item) throw new Error('missing');
            editor.chain().focus().setImage({ src: item.url, alt: item.alt ?? item.title ?? '', title: item.title ?? undefined }).updateAttributes('image', { 'data-media-id': String(item.id) }).run();
            onClose();
        } catch {
            setError(t('That file could not be loaded. Try again, or choose another.'));
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next: boolean) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Image')}</DialogTitle>
                    <DialogDescription>{t('Choose an image from the media library. Its alt text goes with it.')}</DialogDescription>
                </DialogHeader>
                <MediaPickerInput id="rich-image" value={id} multiple={false} max={null} types={['image']} disabled={false} invalid={error !== null}
                    onChange={(value) => setId(typeof value === 'number' ? value : null)} />
                {error && <p className="text-sm text-destructive">{error}</p>}
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>{t('Cancel')}</Button>
                    <Button type="button" disabled={id === null} onClick={() => void insert()}>{t('Insert')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
