import * as React from 'react';
import { EditorContent, useEditor, useEditorState, type Editor } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import {
    AlignCenter, AlignLeft, AlignRight, Bold, Code, Heading2, Heading3, ImagePlus, Italic, Link2, List, ListOrdered, Minus, Quote, Redo2,
    StretchHorizontal, Strikethrough, Trash2, TriangleAlert, Underline, Undo2,
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
import { IMAGE_WIDTHS, imageClass, imageStylesOf, isStyleClass, parseImageClass, toggleStyle, type ImageAlignment, type ImageStyle, type ImageWidth } from '@/lib/image-presets';
import type { RichTextEditorProps } from './rich-text';
import { t } from '@/lib/i18n';

/**
 * Images keep their media library id, so the server knows which files a text
 * uses, and their width and alignment presets and image styles as classes
 * (lib/image-presets.ts): `sunrice-img sunrice-img-w-50 sunrice-img-left rounded`.
 */
const LibraryImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            'data-media-id': { default: null },
            // Both presets and the styles render as one class attribute (from `size`'s renderHTML, which sees every attribute).
            size: {
                default: null,
                parseHTML: (element: HTMLElement) => parseImageClass(element.getAttribute('class')).width,
                renderHTML: (attributes: Record<string, unknown>) => {
                    const className = imageClass({ width: attributes.size, align: attributes.align, styles: attributes.styles });
                    return className === null ? {} : { class: className };
                },
            },
            align: {
                default: null,
                parseHTML: (element: HTMLElement) => parseImageClass(element.getAttribute('class')).align,
                renderHTML: () => ({}),
            },
            styles: {
                default: null,
                parseHTML: (element: HTMLElement) => {
                    const { styles } = parseImageClass(element.getAttribute('class'));
                    return styles.length === 0 ? null : styles;
                },
                renderHTML: () => ({}),
            },
        };
    },
});

/**
 * The rich text editor (TipTap): the formatting the servers keep when they
 * clean the HTML (headings, lists, quotes, code, links, library images).
 * Loaded on demand through RichTextEditor in rich-text.tsx.
 */
export function RichTextEditor({ id, value, onChange, invalid = false, labelledBy, images = true, imageStyles, disabled = false }: RichTextEditorProps) {
    const styles = React.useMemo(() => imageStylesOf(imageStyles), [imageStyles]);
    const [dialog, setDialog] = React.useState<'link' | 'image' | null>(null);
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3, 4] },
                // No rel="nofollow" (TipTap's default) on the site's own links: the servers set rel="noopener noreferrer" themselves.
                link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: null, target: null } } }),
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
            {editor && !disabled && <ImageMenu editor={editor} styles={styles} />}
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

const IMAGE_MENU = 'sunriceImageMenu';

function alignments(): { value: ImageAlignment; label: string; icon: React.ReactNode }[] {
    return [
        { value: 'left', label: t('Left, text wraps'), icon: <AlignLeft /> },
        { value: 'center', label: t('Centre'), icon: <AlignCenter /> },
        { value: 'right', label: t('Right, text wraps'), icon: <AlignRight /> },
        { value: 'full', label: t('Full width'), icon: <StretchHorizontal /> },
    ];
}

/**
 * The selected image's own toolbar: width and alignment presets, the
 * configured styles (toggles, several at once: they're independent looks
 * such as rounded corners and a shadow), its alt text and removing it.
 * Tab reaches it from the text; Esc goes back.
 */
function ImageMenu({ editor, styles }: { editor: Editor; styles: ImageStyle[] }) {
    const image = useEditorState({
        editor,
        selector: ({ editor: e }) => {
            if (!e.isActive('image')) return null;
            const attributes = e.getAttributes('image');
            return {
                size: (typeof attributes.size === 'string' ? attributes.size : null) as ImageWidth | null,
                align: (typeof attributes.align === 'string' ? attributes.align : null) as ImageAlignment | null,
                alt: typeof attributes.alt === 'string' ? attributes.alt : '',
                styles: Array.isArray(attributes.styles) ? attributes.styles.filter(isStyleClass) : [],
            };
        },
    });
    const set = (attributes: Record<string, string | string[] | null>) => editor.chain().updateAttributes('image', attributes).run();
    const order = styles.map((style) => style.class);
    const back = () => editor.commands.focus();
    const noAlt = image !== null && image.alt.trim() === '';
    // Tab from the text goes straight to the first button: the menu plugin makes its wrapper focusable when it starts (after BubbleMenu's own effect, before this one).
    const wrapper = React.useRef<HTMLDivElement | null>(null);
    React.useEffect(() => { if (wrapper.current) wrapper.current.tabIndex = -1; }, [editor]);
    // The menu stays within the editor: no wider than its text area (the groups wrap onto more rows), and moved along to stay over it.
    const [area, setArea] = React.useState<{ element: HTMLElement; width: number } | null>(null);
    React.useEffect(() => {
        const element = editor.isDestroyed ? null : editor.view.dom.parentElement;
        if (!element) return;
        const measure = () => setArea({ element, width: element.clientWidth });
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(element);
        return () => observer.disconnect();
    }, [editor]);
    const options = React.useMemo(
        () => ({ placement: 'bottom' as const, offset: 8, flip: true, shift: { padding: MENU_INSET, ...(area ? { boundary: area.element } : {}) } }),
        [area?.element],
    );

    return (
        <BubbleMenu editor={editor} pluginKey={IMAGE_MENU} options={options}
            shouldShow={({ editor: e, view, element }) => e.isEditable && e.isActive('image') && (view.hasFocus() || element.contains(document.activeElement))}
            ref={wrapper} className="z-20">
            {image && (
                <div role="toolbar" aria-label={t('Image options')}
                    className="max-w-[calc(100vw-2rem)] rounded-lg border bg-popover p-1 text-popover-foreground shadow-md"
                    style={area ? { maxWidth: Math.max(area.width - 2 * MENU_INSET, 160) } : undefined}
                    onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); back(); } }}
                    onBlur={(e: React.FocusEvent) => {
                        // Leaving the menu for somewhere other than the text hides it (the text's own blur does the rest).
                        const next = e.relatedTarget;
                        if (next instanceof Node && (e.currentTarget.contains(next) || editor.view.dom.contains(next))) return;
                        editor.view.dispatch(editor.state.tr.setMeta(IMAGE_MENU, 'hide'));
                    }}>
                    <MenuRows>
                    <div role="group" aria-label={t('Width')} className="flex gap-0.5">
                        {IMAGE_WIDTHS.map((width) => (
                            <MenuButton key={width} label={t('Width :percent%', { percent: width })} active={image.size === width}
                                onClick={() => set({ size: image.size === width ? null : width })}>
                                <span className="px-1 text-xs font-medium tabular-nums">{width}%</span>
                            </MenuButton>
                        ))}
                    </div>
                    <div role="group" aria-label={t('Alignment')} className={cn('flex gap-0.5', MENU_SEPARATED)}>
                        {alignments().map((option) => (
                            <MenuButton key={option.value} label={option.label} active={image.align === option.value}
                                onClick={() => set({ align: image.align === option.value ? null : option.value })}>
                                {option.icon}
                            </MenuButton>
                        ))}
                    </div>
                    {styles.length > 0 && (
                        <div role="group" aria-label={t('Style')} className={cn('flex flex-wrap items-center gap-0.5', MENU_SEPARATED)}>
                            <span aria-hidden className="px-1 text-xs font-medium text-muted-foreground">{t('Style')}</span>
                            {styles.map((style) => {
                                const on = image.styles.includes(style.class);
                                return (
                                    <MenuButton key={style.class} label={style.label} active={on}
                                        onClick={() => { const next = toggleStyle(image.styles, style.class, order); set({ styles: next.length === 0 ? null : next }); }}>
                                        <span className="px-1 text-xs font-medium">{style.label}</span>
                                    </MenuButton>
                                );
                            })}
                        </div>
                    )}
                    {/* The alt text and removing the image stay on one row. */}
                    <div className={cn('flex min-w-0 items-center', MENU_SEPARATED)}>
                        <AltInput value={image.alt} warn={noAlt} onChange={(alt) => set({ alt })} onEnter={back} />
                        <div className={MENU_SEPARATED}>
                            <MenuButton label={t('Remove image')} onClick={() => editor.chain().focus().deleteSelection().run()}><Trash2 /></MenuButton>
                        </div>
                    </div>
                    </MenuRows>
                </div>
            )}
        </BubbleMenu>
    );
}

function AltInput({ value, warn, onChange, onEnter, className }: { value: string; warn: boolean; onChange: (alt: string) => void; onEnter: () => void; className?: string }) {
    const id = React.useId();

    return (
        <div className={cn('flex min-w-0 flex-wrap items-center gap-1.5 px-1', className)}>
            <label htmlFor={id} className="text-xs font-medium text-muted-foreground">{t('Alt text')}</label>
            <Input id={id} value={value} className={cn('h-8 w-36 min-w-0 shrink text-sm', warn && 'border-amber-500/70')} placeholder={t('Describe the image')}
                aria-describedby={warn ? `${id}-warning` : undefined}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => onChange(e.target.value)}
                onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter') { e.preventDefault(); onEnter(); } }} />
            {warn && (
                <span id={`${id}-warning`} className="flex items-center gap-1 text-xs text-amber-800 dark:text-amber-300">
                    <TriangleAlert className="size-3.5" aria-hidden /> {t('No alt text')}
                </span>
            )}
        </div>
    );
}

function MenuButton({ label, active, onClick, children }: { label: string; active?: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <Button type="button" variant={active ? 'secondary' : 'ghost'} size="icon" className="size-8 w-auto min-w-8" aria-label={label} title={label}
            aria-pressed={active === undefined ? undefined : active} onClick={onClick}
            // A click keeps the image selected and the text focused.
            onMouseDown={(e: React.MouseEvent) => e.preventDefault()}>
            {children}
        </Button>
    );
}

/** How far the image menu keeps from the editor's sides. */
const MENU_INSET = 4;

/**
 * A group after the first: a thin line before it, in the 13px between groups. A group starting a row puts its line
 * in the 13px MenuRows pulls the row out by, which is clipped, so wrapped rows never start or end with a line.
 */
const MENU_SEPARATED = "relative pl-[13px] before:absolute before:top-1/2 before:left-1.5 before:h-5 before:w-px before:-translate-y-1/2 before:bg-border before:content-['']";

/** The image menu's groups, wrapping onto more rows when the editor is narrow. */
function MenuRows({ children }: { children: React.ReactNode }) {
    return (
        <div className="[clip-path:inset(-0.5rem_-0.5rem_-0.5rem_0)]">
            <div className="-ml-[13px] flex flex-wrap items-center gap-y-1 [&>:first-child]:pl-[13px]">{children}</div>
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
            // One node with every attribute: setImage() leaves the selection after the image, so a later updateAttributes() missed it.
            editor.chain().focus().insertContent({ type: 'image', attrs: { src: item.url, alt: item.alt ?? item.title ?? '', title: item.title ?? null, 'data-media-id': String(item.id) } }).run();
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
