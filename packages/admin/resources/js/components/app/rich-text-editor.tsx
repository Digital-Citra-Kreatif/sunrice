import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { EditorContent, Extension, getMarkRange, useEditor, useEditorState, type Editor } from '@tiptap/react';
import { TextSelection } from '@tiptap/pm/state';
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { BubbleMenu } from '@tiptap/react/menus';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import {
    AlignCenter, AlignLeft, AlignRight, ArrowRight, Bold, Code, ExternalLink, Heading2, Heading3, ImagePlus, Italic, Link2, List, ListOrdered, LoaderCircle, Minus, Quote, Redo2,
    SquareArrowOutUpRight, StretchHorizontal, Strikethrough, Trash2, TriangleAlert, Underline, Undo2, Unlink, X,
} from 'lucide-react';
import { MediaPickerInput } from '@/components/media/media-picker';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { adminUrl } from '@/lib/admin-url';
import { isSafeUrl } from '@/lib/safe-url';
import { defaultNewTab, parseDataLink, relAttribute, relTokens, type LinkItem, type RelOption } from '@/lib/rich-link';
import { LinkAttributes } from '@/components/app/link-attributes';
import { LinkSourcePicker } from '@/components/app/link-source-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { SharedProps } from '@/types';
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
 * What a link points at on the site, kept as data-link="entry:12"
 * (Panel::richTextLinkSource()): the CMS gives it the item's current URL
 * when pages show it. Its rel (nofollow, sponsored, ugc) round-trips as the
 * link's own attribute; the servers add noopener noreferrer.
 */
const LinkTarget = Extension.create({
    name: 'sunriceLinkTarget',
    addGlobalAttributes() {
        return [{
            types: ['link'],
            attributes: {
                'data-link': {
                    default: null,
                    parseHTML: (element: HTMLElement) => (parseDataLink(element.getAttribute('data-link')) ? element.getAttribute('data-link') : null),
                    renderHTML: (attributes: Record<string, unknown>) => (typeof attributes['data-link'] === 'string' ? { 'data-link': attributes['data-link'] } : {}),
                },
            },
        }];
    },
});

/**
 * The rich text editor (TipTap): the formatting the servers keep when they
 * clean the HTML (headings, lists, quotes, code, links, library images).
 * Loaded on demand through RichTextEditor in rich-text.tsx.
 */
export function RichTextEditor({ id, value, onChange, invalid = false, labelledBy, images = true, imageStyles, disabled = false }: RichTextEditorProps) {
    const styles = React.useMemo(() => imageStylesOf(imageStyles), [imageStyles]);
    const [dialog, setDialog] = React.useState<'image' | null>(null);
    const linkSources = usePage<SharedProps>().props.sunrice?.richTextLinks ?? [];
    const linkMenu = React.useRef<LinkMenuHandle | null>(null);
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3, 4] },
                // No rel="nofollow" (TipTap's default) unless chosen in the link menu: the servers add rel="noopener noreferrer" themselves.
                link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: null, target: null } } }),
            LinkTarget,
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
            {editor && !disabled && <Toolbar editor={editor} onLink={() => linkMenu.current?.open()} onImage={images ? () => setDialog('image') : null} />}
            <EditorContent editor={editor} />
            {editor && !disabled && <ImageMenu editor={editor} styles={styles} />}
            {editor && !disabled && <LinkMenu ref={linkMenu} editor={editor} sources={linkSources} />}
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
    const { wrapper, area, options } = useMenuPlacement(editor);

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

/**
 * Where a bubble menu shows: Tab from the text goes straight to its first
 * control, and it stays within the editor, no wider than its text area
 * (its groups wrap onto more rows) and moved along to stay over it.
 */
function useMenuPlacement(editor: Editor) {
    // The menu plugin makes its wrapper focusable when it starts (after BubbleMenu's own effect, before this one).
    const wrapper = React.useRef<HTMLDivElement | null>(null);
    React.useEffect(() => { if (wrapper.current) wrapper.current.tabIndex = -1; }, [editor]);
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

    return { wrapper, area, options };
}

const LINK_MENU = 'sunriceLinkMenu';

interface LinkMenuHandle {
    /** The toolbar's Link: the menu of the link at the cursor, or a new one for the selection, its address focused. */
    open: () => void;
}

interface LinkState {
    href: string;
    newTab: boolean;
    rel: string | null;
    dataLink: string | null;
    /** The link's text when it's one run of plain text (editable in the menu), else null. */
    text: string | null;
}

/** The link mark's range around the cursor, and its text when that's a single text node. */
function linkRange(editor: Editor): { from: number; to: number; text: string | null } | null {
    const type = editor.schema.marks.link;
    if (!type) return null;
    const { $from } = editor.state.selection;
    const range = getMarkRange($from, type) ?? (editor.state.selection.empty ? null : getMarkRange(editor.state.doc.resolve($from.pos + 1), type));
    if (!range) return null;
    const nodes: ProseMirrorNode[] = [];
    editor.state.doc.nodesBetween(range.from, range.to, (node) => { if (node.isText) nodes.push(node); return !node.isText; });

    return { ...range, text: nodes.length === 1 ? nodes[0]!.text ?? null : null };
}

/**
 * The link at the cursor's own menu, laid out as a Link field is: what it
 * links to (Link, an address; or a link source's kind, Entry, Term…,
 * Panel::richTextLinkSource(), picked by search), its text, opening in a
 * new tab and the rel choices, then opening it and removing it. Clicking a
 * link puts the cursor in it, which shows the menu; the toolbar's Link
 * opens it for the selection. Tab reaches it from the text; Esc goes back.
 */
function LinkMenu({ editor, sources, ref }: { editor: Editor; sources: { key: string; label: string }[]; ref: React.Ref<LinkMenuHandle> }) {
    const link = useEditorState({
        editor,
        selector: ({ editor: e }): LinkState | null => {
            if (!e.isActive('link') || e.isActive('image')) return null;
            const attributes = e.getAttributes('link');
            return {
                href: typeof attributes.href === 'string' ? attributes.href : '',
                newTab: attributes.target === '_blank',
                rel: typeof attributes.rel === 'string' ? attributes.rel : null,
                dataLink: parseDataLink(attributes['data-link']) ? (attributes['data-link'] as string) : null,
                text: linkRange(e)?.text ?? null,
            };
        },
    });
    // A new link for the selection (the toolbar's Link): shown until the selection moves.
    const [adding, setAdding] = React.useState<{ from: number; to: number } | null>(null);
    const addingRef = React.useRef(adding);
    addingRef.current = adding;
    // While the kind's dropdown is open its options are outside the menu: leaving for them isn't leaving.
    const selecting = React.useRef(false);
    const input = React.useRef<HTMLInputElement | null>(null);
    const [draft, setDraft] = React.useState('');
    const [text, setText] = React.useState('');
    const [kind, setKind] = React.useState('url');
    // A new link's choices before it exists: a new address opens in a new tab unless unticked, the site's own pages don't.
    const [pending, setPending] = React.useState<{ newTab: boolean | null; rel: RelOption[] }>({ newTab: null, rel: [] });
    const { wrapper, area, options } = useMenuPlacement(editor);
    const inputId = React.useId();
    const textId = React.useId();
    const shown = link !== null || adding !== null;

    // Another link (or none): its address, text and kind in the inputs.
    const target = link ? parseDataLink(link.dataLink) : null;
    React.useEffect(() => {
        setDraft(link?.href ?? '');
        setText(link?.text ?? '');
        setKind(target && sources.some((source) => source.key === target.source) ? target.source : 'url');
        if (adding === null) setPending({ newTab: null, rel: [] });
    }, [link?.href, link?.dataLink, link?.text, adding === null]);

    // A move away from the selection ends a new link.
    React.useEffect(() => {
        const moved = () => {
            const current = addingRef.current;
            const { from, to } = editor.state.selection;
            if (current && (current.from !== from || current.to !== to)) setAdding(null);
        };
        editor.on('selectionUpdate', moved);
        return () => { editor.off('selectionUpdate', moved); };
    }, [editor]);

    React.useImperativeHandle(ref, () => ({
        open: () => {
            if (!editor.isActive('link')) {
                const { from, to } = editor.state.selection;
                setAdding({ from, to });
                addingRef.current = { from, to };
            }
            // Once its content has rendered: shown where it fits, then focused.
            requestAnimationFrame(() => {
                if (addingRef.current !== null) editor.view.dispatch(editor.state.tr.setMeta(LINK_MENU, 'show'));
                input.current?.focus();
            });
        },
    }), [editor]);

    const back = () => editor.commands.focus();
    const query = draft.trim();
    const newTab = link ? link.newTab : pending.newTab ?? defaultNewTab(kind);
    const rel = link ? relTokens(link.rel) : pending.rel;

    /** Sets the link's attributes: on the link at the cursor, on the selection, or as new text where nothing is selected. */
    const setLink = (attributes: Record<string, string | null>, fallbackText: string) => {
        if (editor.isActive('link')) {
            editor.chain().focus().extendMarkRange('link').updateAttributes('link', attributes).run();
        } else {
            const all = { target: newTab ? '_blank' : null, rel: relAttribute(rel), ...attributes };
            if (editor.state.selection.empty) {
                const words = text.trim() !== '' ? text.trim() : fallbackText;
                const { from } = editor.state.selection;
                editor.chain().focus().insertContent({ type: 'text', text: words, marks: [{ type: 'link', attrs: all }] }).setTextSelection({ from, to: from + words.length }).run();
            } else {
                editor.chain().focus().setMark('link', all).run();
            }
        }
        setAdding(null);
    };
    /** Changes the link at the cursor without moving the cursor or the focus. */
    const update = (attributes: Record<string, string | null>) => {
        const { from, to } = editor.state.selection;
        editor.chain().extendMarkRange('link').updateAttributes('link', attributes).setTextSelection({ from, to }).run();
    };
    /** Replaces the link's text, keeping the link (and its other marks). */
    const applyText = () => {
        const range = linkRange(editor);
        const words = text.trim();
        if (!range || range.text === null || words === '' || words === range.text) return;
        const node = editor.state.doc.nodeAt(range.from);
        if (!node) return;
        const tr = editor.state.tr.replaceWith(range.from, range.to, editor.schema.text(words, node.marks));
        editor.view.dispatch(tr.setSelection(TextSelection.create(tr.doc, range.from, range.from + words.length)));
    };
    const invalid = kind === 'url' && query !== '' && !isSafeUrl(query);
    const applyAddress = () => {
        if (query === '') {
            if (link) editor.chain().focus().extendMarkRange('link').unsetLink().run();
            else back();
            setAdding(null);
            return;
        }
        if (invalid) return;
        setLink({ href: query, 'data-link': null }, query);
    };
    const choose = (item: LinkItem | null) => {
        if (item === null) {
            if (link) update({ 'data-link': null });
            input.current?.focus();
            return;
        }
        setLink({ href: item.url, 'data-link': item.link }, item.title);
    };
    const remove = () => { editor.chain().focus().extendMarkRange('link').unsetLink().run(); setAdding(null); };
    const chooseKind = (next: string) => {
        setKind(next);
        setDraft(next === 'url' ? (link?.href ?? '') : '');
    };

    const opens = link !== null && link.href !== '' && isSafeUrl(link.href);
    const kinds = [{ key: 'url', label: t('Link') }, ...sources];
    const chosen = target && target.source === kind ? target.id : null;
    const showText = link ? link.text !== null : adding !== null && adding.from === adding.to;

    return (
        <BubbleMenu editor={editor} pluginKey={LINK_MENU} options={options}
            shouldShow={({ editor: e, view, element }) => e.isEditable && !e.isActive('image') && (e.isActive('link') || addingRef.current !== null)
                && (view.hasFocus() || element.contains(document.activeElement) || selecting.current)}
            ref={wrapper} className="z-20">
            {shown && (
                <div role="toolbar" aria-label={t('Link options')}
                    className="flex w-[30rem] max-w-[calc(100vw-2rem)] flex-col gap-2 rounded-lg border bg-popover p-2 text-popover-foreground shadow-md"
                    style={area ? { maxWidth: Math.max(area.width - 2 * MENU_INSET, 200) } : undefined}
                    onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Escape' && !e.defaultPrevented) { e.preventDefault(); e.stopPropagation(); setAdding(null); back(); } }}
                    onBlur={(e: React.FocusEvent) => {
                        const next = e.relatedTarget;
                        if (selecting.current || (next instanceof Node && (e.currentTarget.contains(next) || editor.view.dom.contains(next)))) return;
                        setAdding(null);
                        editor.view.dispatch(editor.state.tr.setMeta(LINK_MENU, 'hide'));
                    }}>
                    <div className="flex flex-wrap items-start gap-2">
                        {sources.length > 0 && (
                            <Select value={kind} onValueChange={chooseKind} onOpenChange={(open: boolean) => { selecting.current = open; }}>
                                <SelectTrigger aria-label={t('Link to')} className="h-9 w-32 shrink-0"><SelectValue /></SelectTrigger>
                                {/* Closed: on to what the kind asks for (its address, or its search). */}
                                <SelectContent onCloseAutoFocus={(e: Event) => { e.preventDefault(); input.current?.focus(); }}>
                                    {kinds.map((option) => <SelectItem key={option.key} value={option.key}>{option.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        )}
                        <div className="flex min-w-40 flex-1 flex-col gap-1">
                            {kind === 'url' ? (
                                <>
                                    <Input ref={input} id={inputId} value={draft} autoComplete="off" aria-label={t('Address')}
                                        className={cn('h-9 text-sm', invalid && 'border-destructive focus-visible:ring-destructive/20')} placeholder="https://… or /about"
                                        aria-invalid={invalid || undefined} aria-describedby={invalid ? `${inputId}-error` : undefined}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDraft(e.target.value)}
                                        onBlur={() => { if (link && query !== '' && query !== link.href && !invalid) update({ href: query, 'data-link': null }); }}
                                        onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter') { e.preventDefault(); applyAddress(); } }} />
                                    {invalid && <span id={`${inputId}-error`} className="flex items-center gap-1 text-xs text-destructive"><TriangleAlert className="size-3.5" aria-hidden /> {t('Not a safe address')}</span>}
                                </>
                            ) : (
                                <LinkSourcePicker key={kind} id={inputId} source={kind} value={chosen} label={t('Search')} onChange={choose} onEscape={back}
                                    inputRef={input} />
                            )}
                        </div>
                    </div>
                    {showText && (
                        <Input id={textId} value={text} aria-label={t('Link text')} placeholder={t('Link text')} className="h-9 text-sm"
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setText(e.target.value)}
                            onBlur={() => { if (link) applyText(); }}
                            onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter') { e.preventDefault(); if (link) { applyText(); back(); } else if (kind === 'url') applyAddress(); } }} />
                    )}
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <LinkAttributes newTab={newTab} rel={rel} className="min-w-0 flex-1"
                            onNewTabChange={(next) => (link ? update({ target: next ? '_blank' : null }) : setPending((p) => ({ ...p, newTab: next })))}
                            onRelChange={(next) => (link ? update({ rel: relAttribute(next) }) : setPending((p) => ({ ...p, rel: next })))} />
                        {link && (
                            <div className="flex shrink-0 items-center gap-0.5">
                                {opens && (
                                    <Button asChild variant="ghost" size="icon" className="size-8">
                                        <a href={link.href} target="_blank" rel="noopener noreferrer" aria-label={t('Open the link')} title={t('Open the link')}
                                            onMouseDown={(e: React.MouseEvent) => e.preventDefault()}><ExternalLink /></a>
                                    </Button>
                                )}
                                <MenuButton label={t('Remove link')} onClick={remove}><Unlink /></MenuButton>
                            </div>
                        )}
                    </div>
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
            // A click keeps the image or link selected and the text focused.
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
