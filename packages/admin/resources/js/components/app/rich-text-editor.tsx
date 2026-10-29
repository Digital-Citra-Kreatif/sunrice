import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { EditorContent, Extension, useEditor, useEditorState, type Editor } from '@tiptap/react';
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
import { REL_OPTIONS, isAddressInput, isSearchInput, linkGroupsOf, linkSearchQuery, parseDataLink, relTokens, toggleRel, type LinkGroup, type LinkItem, type RelOption } from '@/lib/rich-link';
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

const SEARCH_DEBOUNCE_MS = 200;

/** Saved links' items by data-link, once asked (null: gone, or not the user's to see). */
const linkItems = new Map<string, { title: string; source: string } | null>();

interface LinkMenuHandle {
    /** The toolbar's Link: the menu of the link at the cursor, or a new one for the selection, its address focused. */
    open: () => void;
}

interface LinkState { href: string; newTab: boolean; rel: string | null; dataLink: string | null }

function relHelp(option: RelOption): string {
    return {
        nofollow: t('nofollow: search engines don\'t follow the link or count it as an endorsement.'),
        sponsored: t('sponsored: a paid link, an advert or a sponsorship.'),
        ugc: t('ugc: a link in what visitors wrote, such as comments.'),
    }[option];
}

/**
 * The link at the cursor's own toolbar, like an image's: where it goes (an
 * address, or with link sources a search of the site's entries, terms…,
 * Panel::richTextLinkSource()), opening in a new tab, the rel choices,
 * opening it and removing it. Clicking a link puts the cursor in it, which
 * shows the menu; the toolbar's Link opens it for the selection. Tab
 * reaches it from the text; Esc goes back.
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
            };
        },
    });
    // A new link for the selection (the toolbar's Link): shown until the selection moves.
    const [adding, setAdding] = React.useState<{ from: number; to: number } | null>(null);
    const addingRef = React.useRef(adding);
    addingRef.current = adding;
    const input = React.useRef<HTMLInputElement | null>(null);
    const [draft, setDraft] = React.useState('');
    const [kind, setKind] = React.useState('url');
    const [groups, setGroups] = React.useState<LinkGroup[]>([]);
    const [searching, setSearching] = React.useState(false);
    const [active, setActive] = React.useState(0);
    const [, setNamed] = React.useState(0);
    const { wrapper, area, options } = useMenuPlacement(editor);
    const listId = React.useId();
    const inputId = React.useId();
    const shown = link !== null || adding !== null;

    // Another link (or none): its address in the input, the kind it points at.
    const target = link ? parseDataLink(link.dataLink) : null;
    React.useEffect(() => {
        setDraft(link?.href ?? '');
        setKind(target && sources.some((source) => source.key === target.source) ? target.source : 'url');
        setGroups([]);
    }, [link?.href, link?.dataLink, adding === null]);

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

    // A saved link's item, for its chip: asked once per link.
    React.useEffect(() => {
        const key = link?.dataLink;
        if (!key || linkItems.has(key)) return;
        const controller = new AbortController();
        void fetch(adminUrl(`rich-text-links/${key}`), { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(async (response) => {
                const item = response.ok ? ((await response.json()) as { item?: { title?: unknown; source?: unknown } | null }).item : null;
                linkItems.set(key, item && typeof item.title === 'string' && typeof item.source === 'string' ? { title: item.title, source: item.source } : null);
                setNamed((n) => n + 1);
            })
            .catch(() => undefined);
        return () => controller.abort();
    }, [link?.dataLink]);

    // Searching: the chosen kind's items, or with "Link" words that aren't an address, every kind's.
    const query = draft.trim();
    const search = sources.length > 0 && (kind === 'url' ? isSearchInput(draft) : query.length >= 2) && (link === null || query !== link.href);
    React.useEffect(() => {
        if (!search) { setGroups([]); setSearching(false); return; }
        const controller = new AbortController();
        setSearching(true);
        const timer = setTimeout(() => {
            void fetch(`${adminUrl('rich-text-links')}?${linkSearchQuery(query, kind === 'url' ? null : kind)}`, { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(async (response) => { setGroups(response.ok ? linkGroupsOf(await response.json()) : []); setActive(0); setSearching(false); })
                .catch(() => undefined);
        }, SEARCH_DEBOUNCE_MS);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [search, query, kind]);

    const results = groups.flatMap((group) => group.items.map((item) => ({ item, group })));
    const back = () => editor.commands.focus();
    const sourceLabel = (key: string) => sources.find((source) => source.key === key)?.label ?? key;

    /** Sets the link's attributes: on the link at the cursor, on the selection, or as new text where nothing is selected. */
    const setLink = (attributes: Record<string, string | null>, text: string) => {
        if (editor.isActive('link')) {
            editor.chain().focus().extendMarkRange('link').updateAttributes('link', attributes).run();
        } else if (editor.state.selection.empty) {
            const { from } = editor.state.selection;
            editor.chain().focus().insertContent({ type: 'text', text, marks: [{ type: 'link', attrs: attributes }] }).setTextSelection({ from, to: from + text.length }).run();
        } else {
            editor.chain().focus().setMark('link', attributes).run();
        }
        setAdding(null);
        setGroups([]);
    };
    /** Changes the link at the cursor without moving the cursor or the focus. */
    const update = (attributes: Record<string, string | null>) => {
        const { from, to } = editor.state.selection;
        editor.chain().extendMarkRange('link').updateAttributes('link', attributes).setTextSelection({ from, to }).run();
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
    const choose = (item: LinkItem, group: LinkGroup) => {
        linkItems.set(item.link, { title: item.title, source: group.label });
        setLink({ href: item.url, 'data-link': item.link }, item.title);
    };
    const remove = () => { editor.chain().focus().extendMarkRange('link').unsetLink().run(); setAdding(null); };

    const named = link?.dataLink ? linkItems.get(link.dataLink) : undefined;
    const rel = relTokens(link?.rel);
    const opens = link !== null && link.href !== '' && isSafeUrl(link.href);
    const kinds = [{ key: 'url', label: t('Link') }, ...sources];

    return (
        <BubbleMenu editor={editor} pluginKey={LINK_MENU} options={options}
            shouldShow={({ editor: e, view, element }) => e.isEditable && !e.isActive('image') && (e.isActive('link') || addingRef.current !== null)
                && (view.hasFocus() || element.contains(document.activeElement))}
            ref={wrapper} className="z-20">
            {shown && (
                <div role="toolbar" aria-label={t('Link options')}
                    className="max-w-[calc(100vw-2rem)] rounded-lg border bg-popover p-1 text-popover-foreground shadow-md"
                    style={area ? { maxWidth: Math.max(area.width - 2 * MENU_INSET, 160) } : undefined}
                    onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); setAdding(null); back(); } }}
                    onBlur={(e: React.FocusEvent) => {
                        const next = e.relatedTarget;
                        if (next instanceof Node && (e.currentTarget.contains(next) || editor.view.dom.contains(next))) return;
                        setAdding(null);
                        editor.view.dispatch(editor.state.tr.setMeta(LINK_MENU, 'hide'));
                    }}>
                    <MenuRows>
                    {sources.length > 0 && (
                        <div role="group" aria-label={t('Link to')} className="flex gap-0.5">
                            {kinds.map((option) => (
                                <MenuButton key={option.key} label={option.key === 'url' ? t('An address') : option.label} active={kind === option.key}
                                    onClick={() => { setKind(option.key); setDraft(option.key === 'url' ? (link?.href ?? '') : ''); setGroups([]); input.current?.focus(); }}>
                                    <span className="px-1 text-xs font-medium">{option.label}</span>
                                </MenuButton>
                            ))}
                        </div>
                    )}
                    <div className={cn('flex min-w-0 flex-wrap items-center gap-1.5 px-1', sources.length > 0 && MENU_SEPARATED)}>
                        {link?.dataLink && target && kind === target.source ? (
                            <span className={cn('flex h-8 min-w-0 items-center gap-1 rounded-md border bg-muted/50 pr-0.5 pl-2 text-sm', named === null && 'border-amber-500/70')}>
                                <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" aria-hidden />
                                <span className="truncate">
                                    {named === undefined ? t('Loading…') : named === null ? t('Not found: shows as text') : named.title}
                                    <span className="text-muted-foreground"> · {named?.source ?? sourceLabel(target?.source ?? '')}</span>
                                </span>
                                <MenuButton label={t('Use an address instead')} onClick={() => { update({ 'data-link': null }); setKind('url'); input.current?.focus(); }}><X /></MenuButton>
                            </span>
                        ) : (
                            <>
                                <label htmlFor={inputId} className="sr-only">{kind === 'url' ? t('Address') : t('Search')}</label>
                                <Input ref={input} id={inputId} value={draft} autoComplete="off"
                                    className={cn('h-8 w-52 min-w-0 shrink text-sm', invalid && 'border-destructive focus-visible:ring-destructive/20')}
                                    placeholder={kind === 'url' ? (sources.length > 0 ? t('https://…, /about or search') : 'https://…') : t('Search…')}
                                    role={sources.length > 0 ? 'combobox' : undefined} aria-expanded={sources.length > 0 ? results.length > 0 : undefined}
                                    aria-controls={results.length > 0 ? listId : undefined} aria-activedescendant={results.length > 0 ? `${listId}-${active}` : undefined}
                                    aria-invalid={invalid || undefined} aria-describedby={invalid ? `${inputId}-error` : undefined}
                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDraft(e.target.value)}
                                    onBlur={() => { if (link && kind === 'url' && query !== '' && query !== link.href && !invalid && isAddressInput(query)) update({ href: query, 'data-link': null }); }}
                                    onKeyDown={(e: React.KeyboardEvent) => {
                                        if (e.key === 'ArrowDown' && results.length > 0) { e.preventDefault(); setActive((i) => (i + 1) % results.length); }
                                        else if (e.key === 'ArrowUp' && results.length > 0) { e.preventDefault(); setActive((i) => (i - 1 + results.length) % results.length); }
                                        else if (e.key === 'Enter') {
                                            e.preventDefault();
                                            const picked = results[active];
                                            if (picked && (kind !== 'url' || !isAddressInput(query))) choose(picked.item, picked.group);
                                            else if (kind === 'url') applyAddress();
                                        }
                                    }} />
                                {searching && <LoaderCircle className="size-3.5 animate-spin text-muted-foreground" aria-label={t('Searching…')} />}
                                {invalid && <span id={`${inputId}-error`} className="flex items-center gap-1 text-xs text-destructive"><TriangleAlert className="size-3.5" aria-hidden /> {t('Not a safe address')}</span>}
                            </>
                        )}
                    </div>
                    {link && (
                        <div role="group" aria-label={t('Link attributes')} className={cn('flex flex-wrap items-center gap-0.5', MENU_SEPARATED)}>
                            <MenuButton label={t('Open in a new tab')} tip={[t('Open in a new tab'), t('Links always open safely (noopener noreferrer).')]} active={link.newTab}
                                onClick={() => update({ target: link.newTab ? null : '_blank' })}><SquareArrowOutUpRight /></MenuButton>
                            {REL_OPTIONS.map((option) => (
                                <MenuButton key={option} label={option} tip={[relHelp(option)]} active={rel.includes(option)} onClick={() => update({ rel: toggleRel(link.rel, option) })}>
                                    <span className="px-1 text-xs font-medium">{option}</span>
                                </MenuButton>
                            ))}
                        </div>
                    )}
                    {link && (
                        <div className={cn('flex items-center gap-0.5', MENU_SEPARATED)}>
                            {opens && (
                                <Button asChild variant="ghost" size="icon" className="size-8">
                                    <a href={link.href} target="_blank" rel="noopener noreferrer" aria-label={t('Open the link')} title={t('Open the link')}
                                        onMouseDown={(e: React.MouseEvent) => e.preventDefault()}><ExternalLink /></a>
                                </Button>
                            )}
                            <MenuButton label={t('Remove link')} onClick={remove}><Unlink /></MenuButton>
                        </div>
                    )}
                    </MenuRows>
                    {results.length > 0 && (
                        <div id={listId} role="listbox" aria-label={t('Search results')} className="mt-1 max-h-64 overflow-y-auto border-t pt-1">
                            {groups.map((group) => (
                                <div key={group.key} role="group" aria-label={group.label}>
                                    <div aria-hidden className="px-2 pt-1 pb-0.5 text-xs font-medium text-muted-foreground">{group.label}</div>
                                    {group.items.map((item) => {
                                        const index = results.findIndex((result) => result.item === item);
                                        return (
                                            <div key={item.link} id={`${listId}-${index}`} role="option" aria-selected={index === active}
                                                className={cn('flex cursor-pointer items-baseline gap-2 rounded-md px-2 py-1 text-sm', index === active && 'bg-accent text-accent-foreground')}
                                                onMouseDown={(e: React.MouseEvent) => e.preventDefault()} onMouseEnter={() => setActive(index)} onClick={() => choose(item, group)}>
                                                <span className="truncate">{item.title}</span>
                                                {item.hint && <span className="shrink-0 truncate text-xs text-muted-foreground">{item.hint}</span>}
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    )}
                    {search && !searching && results.length === 0 && (
                        <p className="mt-1 border-t px-2 pt-1.5 pb-0.5 text-xs text-muted-foreground">{t('Nothing found.')}</p>
                    )}
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

/** A menu's button; with `tip`, its tooltip's lines (else the label shows as its title). */
function MenuButton({ label, active, onClick, tip, children }: { label: string; active?: boolean; onClick: () => void; tip?: string[]; children: React.ReactNode }) {
    const button = (
        <Button type="button" variant={active ? 'secondary' : 'ghost'} size="icon" className="size-8 w-auto min-w-8" aria-label={label} title={tip ? undefined : label}
            aria-pressed={active === undefined ? undefined : active} onClick={onClick}
            // A click keeps the image or link selected and the text focused.
            onMouseDown={(e: React.MouseEvent) => e.preventDefault()}>
            {children}
        </Button>
    );
    if (!tip) return button;

    return (
        <Tooltip>
            <TooltipTrigger asChild>{button}</TooltipTrigger>
            <TooltipContent className="max-w-64">{tip.map((line) => <p key={line}>{line}</p>)}</TooltipContent>
        </Tooltip>
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
