/**
 * The panel's UI components, so plugin pages match the admin's look:
 *
 *     import { Button, Card, toast } from '@imajiner/admin-sdk/ui';
 *
 * In a bundle built with the Vite plugin these come straight from the
 * panel; this module is the fallback for other bundlers.
 */
import type { ComponentType, ReactNode } from 'react';

type AnyComponent = ComponentType<any>;

function ui(): Record<string, unknown> {
    const modules = globalThis.Sunrice?.modules;
    if (!modules) {
        throw new Error('[sunrice] @imajiner/admin-sdk/ui needs the Sunrice admin panel (window.Sunrice is missing).');
    }

    return modules['@imajiner/admin-sdk/ui'] ?? {};
}

const m = ui();

export const Badge = m.Badge as AnyComponent;
export const Button = m.Button as AnyComponent;
export const Card = m.Card as AnyComponent;
export const CardContent = m.CardContent as AnyComponent;
export const CardDescription = m.CardDescription as AnyComponent;
export const CardFooter = m.CardFooter as AnyComponent;
export const CardHeader = m.CardHeader as AnyComponent;
export const CardTitle = m.CardTitle as AnyComponent;
export const Checkbox = m.Checkbox as AnyComponent;

/**
 * The panel's code editor (CodeMirror 6): line numbers, highlighting for
 * `language` (html, css, javascript, json; plain by default), bracket
 * matching and indentation. It grows from `minLines` (4) to `maxLines`
 * (20), then scrolls. Tab indents; Esc then Tab moves on. Its code loads
 * when an editor first shows, with a plain textarea meanwhile.
 */
export const CodeEditor = m.CodeEditor as ComponentType<{
    id?: string;
    value: string;
    onChange: (value: string) => void;
    language?: 'html' | 'css' | 'javascript' | 'json' | 'plain';
    invalid?: boolean;
    disabled?: boolean;
    readOnly?: boolean;
    minLines?: number;
    maxLines?: number;
    placeholder?: string;
    'aria-labelledby'?: string;
    'aria-describedby'?: string;
    className?: string;
}>;

/**
 * A card whose header folds its content away (animated); the choice is
 * remembered per `storageKey`, and `hasErrors` opens it so a validation
 * error is never hidden. Folded fields stay in the form but are inert.
 */
export const CollapsibleCard = m.CollapsibleCard as ComponentType<{
    title: ReactNode;
    description?: ReactNode;
    storageKey?: string;
    defaultOpen?: boolean;
    hasErrors?: boolean;
    headerAction?: ReactNode;
    titleClassName?: string;
    contentClassName?: string;
    className?: string;
    children: ReactNode;
}>;

/** The panel's command list (a filterable list of items with a search box), for comboboxes and pickers. */
export const Command = m.Command as AnyComponent;
export const CommandEmpty = m.CommandEmpty as AnyComponent;
export const CommandGroup = m.CommandGroup as AnyComponent;
export const CommandInput = m.CommandInput as AnyComponent;
export const CommandItem = m.CommandItem as AnyComponent;
export const CommandList = m.CommandList as AnyComponent;
export const CommandSeparator = m.CommandSeparator as AnyComponent;

/**
 * A calendar in a popover, with a time input when `withTime`. The value is
 * what a native date input gives: "2026-10-19", or "2026-10-19T14:30".
 */
export const DatePicker = m.DatePicker as ComponentType<{
    id?: string;
    value: string | null;
    onChange: (value: string | null) => void;
    withTime?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    required?: boolean;
    placeholder?: string;
    className?: string;
}>;
export const Dialog = m.Dialog as AnyComponent;
export const DialogContent = m.DialogContent as AnyComponent;
export const DialogDescription = m.DialogDescription as AnyComponent;
export const DialogFooter = m.DialogFooter as AnyComponent;
export const DialogHeader = m.DialogHeader as AnyComponent;
export const DialogTitle = m.DialogTitle as AnyComponent;
export const DialogTrigger = m.DialogTrigger as AnyComponent;
export const DropdownMenu = m.DropdownMenu as AnyComponent;
/** A menu item with a check mark: `checked`, `onCheckedChange`. */
export const DropdownMenuCheckboxItem = m.DropdownMenuCheckboxItem as AnyComponent;
export const DropdownMenuContent = m.DropdownMenuContent as AnyComponent;
export const DropdownMenuItem = m.DropdownMenuItem as AnyComponent;
export const DropdownMenuLabel = m.DropdownMenuLabel as AnyComponent;
export const DropdownMenuSeparator = m.DropdownMenuSeparator as AnyComponent;
export const DropdownMenuTrigger = m.DropdownMenuTrigger as AnyComponent;
/** A setting's value as the form holds it. */
export type SettingsValue = string | number | boolean | null | string[] | number[] | (string | number)[];

/** A settings section's fields as `SettingsFields::props()` (PHP) gives them: `sections`, `values`, `secrets`. */
export interface SettingsFieldsProps {
    /** The form: opaque, made by the panel; pass it to FormSections as it is. */
    sections: unknown[];
    values: Record<string, SettingsValue>;
    /** Whether each Secret field has a saved value (never sent). */
    secrets: Record<string, boolean>;
}

/**
 * Settings fields drawn as the Settings page draws them (every field type,
 * sections as cards): `sections` from `SettingsFields::props()`, the values
 * in `data` (start from its `values`), errors by field name.
 */
export const FormSections = m.FormSections as ComponentType<{
    sections: unknown[];
    data: Record<string, SettingsValue>;
    errors: Partial<Record<string, string>>;
    onChange: (name: string, value: SettingsValue) => void;
    /** Remembers which titled sections are folded, under this key plus the title. */
    storageKey?: string;
}>;
export const Input = m.Input as AnyComponent;
export interface KeptEdit { id: number; by: string | null; taken_by: string | null; at: string | null }
/** "Ana's unsaved changes were kept" notices, with Load and Discard (see useEditLock). */
export const KeptEditsNotice = m.KeptEditsNotice as ComponentType<{ kept: KeptEdit[]; onLoad: (id: number) => void; onDiscard: (id: number) => void; disabled?: boolean }>;
export const Label = m.Label as AnyComponent;

/** A language as a LanguageTabs tab shows it. */
export interface LanguageTab {
    code: string;
    /** Its name ("Bahasa Indonesia"); the code when not given. */
    name?: string;
    /** The main language: said in its tooltip ("English (main)"). */
    main?: boolean;
    /** Nothing in this language yet: muted with a plus; choosing it calls `onAdd` when given, else opens it. */
    missing?: boolean;
    /** A dot and what it means (tooltip, screen readers): `success` green (published), `warning` amber (outdated). */
    status?: { tone: 'success' | 'warning'; label: string } | null;
    /** More for the tooltip and screen readers ("Draft"), without a dot. */
    description?: string | null;
}

/**
 * The panel's one way to choose which language's content is shown or
 * edited: a row of tabs (the main language marked, missing ones muted with
 * a plus, an optional status dot) with `actions` at its end; no tabs with
 * fewer than two languages. Without children it switches pages
 * (`onValueChange` visits `?locale=…`; tabs activate on Enter/Space);
 * with `children(code)` it switches each language's panel in place.
 *
 *     <LanguageTabs value={locale} languages={[{ code: 'en', name: 'English', main: true }, { code: 'id', name: 'Bahasa Indonesia' }]}
 *         onValueChange={(code) => router.get(url, { locale: code })} />
 */
export const LanguageTabs = m.LanguageTabs as ComponentType<{
    languages: LanguageTab[];
    value: string;
    onValueChange: (code: string) => void;
    onAdd?: (code: string) => void;
    actions?: ReactNode;
    children?: (code: string) => ReactNode;
    label?: string;
    className?: string;
}>;

/**
 * Choose files from the media library (folders, search, upload), as the
 * panel's own MediaPicker field does: one id, or an ordered list with
 * `multiple`. Choosing needs sunrice.media.view, uploading .upload.
 */
export const MediaPickerInput = m.MediaPickerInput as ComponentType<{
    id: string;
    value: number | number[] | null;
    multiple: boolean;
    /** With `multiple`: at most this many. */
    max: number | null;
    /** image, video, audio, document; none for any. */
    types: string[];
    disabled: boolean;
    invalid: boolean;
    onChange: (value: number | number[] | null) => void;
}>;
/** A copy of `items` with the one at `from` moved to `to` (what SortableList's onReorder did). */
export const moveItem = m.moveItem as <T>(items: readonly T[], from: number, to: number) => T[];

/** An item the command palette (Cmd/Ctrl+K) lists. */
export interface CommandItem {
    id: string;
    label: string;
    description?: string;
    group?: string;
    /** A navigation icon name ('file-text', 'users'…). */
    icon?: string;
    /** Opened with Inertia when chosen: a full href, so use adminUrl(). */
    url?: string;
    onSelect?: () => void;
    keywords?: string[];
}

/**
 * Adds a source of results to the command palette, asked while the person
 * types (after `minLength` letters, 2 by default); returns a function that
 * removes it. Throwing or an aborted request contributes nothing:
 *
 *     registerCommandSource(async (query, signal) => {
 *         const response = await fetch(`${adminUrl('shop/orders/search')}?q=${encodeURIComponent(query)}`, { signal, headers: { Accept: 'application/json' } });
 *         return (await response.json()).map((order) => ({ id: String(order.id), label: order.number, group: 'Orders', url: adminUrl(`shop/orders/${order.id}`) }));
 *     });
 */
export const registerCommandSource = m.registerCommandSource as (
    source: (query: string, signal: AbortSignal) => Promise<CommandItem[]> | CommandItem[],
    options?: { minLength?: number },
) => () => void;

/** An item of a page header's "⋮" menu: a link (`href`) or an action (`onSelect`). */
export interface PageHeaderMenuItem {
    label: ReactNode;
    /** A panel page (an Inertia visit); with `external` another site in a new tab; with `download` a file. */
    href?: string;
    onSelect?: () => void;
    icon?: ReactNode;
    external?: boolean;
    download?: boolean;
    /** Items with the same group sit together under its heading. */
    group?: string;
    destructive?: boolean;
}

/**
 * The block at the top of a page: the title in the panel's display style,
 * an optional description, a back button, children beside the title (a
 * badge, a switcher, "Unsaved changes"), and its actions: `primary`, the
 * one prominent button ("New entry"), and `menu`, the secondary actions and
 * links in a "⋮" menu after it (Export CSV, settings, the blueprint…).
 * `actions` puts other buttons before them. Table controls (Columns,
 * filters) belong in the table's toolbar, not here.
 */
export const PageHeader = m.PageHeader as ComponentType<{
    title: ReactNode;
    description?: ReactNode;
    back?: { href: string; label: string };
    actions?: ReactNode;
    primary?: ReactNode;
    menu?: PageHeaderMenuItem[];
    children?: ReactNode;
    className?: string;
}>;

/**
 * The panel's rich text editor (TipTap): headings, lists, quotes, code,
 * links and, with `images` (the default), media library images. Its code
 * loads when an editor first shows. Sanitise the HTML on the server.
 */
export const RichTextEditor = m.RichTextEditor as ComponentType<{
    id: string;
    value: string;
    /** The new HTML, or null when the text is empty. */
    onChange: (html: string | null) => void;
    invalid?: boolean;
    /** The label's id, for the editing area's name. */
    labelledBy?: string;
    images?: boolean;
    /** The image styles to offer in an image's toolbar ({class, label}; the CMS's and RichText's fields carry them as `imageStyles`). */
    imageStyles?: { class: string; label: string }[];
    disabled?: boolean;
}>;
export const Select = m.Select as AnyComponent;
export const SelectContent = m.SelectContent as AnyComponent;
export const SelectItem = m.SelectItem as AnyComponent;
export const SelectTrigger = m.SelectTrigger as AnyComponent;
export const SelectValue = m.SelectValue as AnyComponent;
export const Separator = m.Separator as AnyComponent;

/** An empty list's note that `count` more are in the trash ("1 is in the trash."), with a button that shows it. */
export const TrashHint = m.TrashHint as ComponentType<{ count: number; onShow: () => void }>;

/** A list's title while it shows the trash: `children` (the name) with a red Trash badge. */
export const TrashTitle = m.TrashTitle as ComponentType<{ children: ReactNode }>;

/** Above a list showing the trash: its `items` ("blog posts") are deleted and show nowhere until restored, with a button back (`backLabel`, else "Back to the list"). */
export const TrashBanner = m.TrashBanner as ComponentType<{ items: string; onBack: () => void; backLabel?: string }>;

/** A "For developers" disclosure (as under a field's help, Field::developerHelp()): a sentence and code with a copy button. */
export const DeveloperHelp = m.DeveloperHelp as ComponentType<{ text?: string | null; code?: string | null; className?: string }>;

/** An entry of the Settings menu, as `Panel::settingsNavigation()` gives them. */
export interface SettingsNavEntry {
    key: string;
    label: string;
    /** Its address under the panel: "settings/site", "settings/seo". */
    url: string;
}

/** The Settings menu beside a settings page (`items` from `Panel::settingsNavigation()`); `current` is the key shown. */
export const SettingsNav = m.SettingsNav as ComponentType<{ items: SettingsNavEntry[]; current: string }>;

/** A page among the settings sections (`NavigationItem::inSettings()`): the Settings menu, then the page. */
export const SettingsLayout = m.SettingsLayout as ComponentType<{ items: SettingsNavEntry[]; current: string; children: ReactNode }>;
export const Skeleton = m.Skeleton as AnyComponent;

/** Props the SortableList render prop gives for the drag handle; spread them on SortableHandle or your own button. */
export interface SortableHandleProps extends Record<string, unknown> {
    ref?: (element: HTMLElement | null) => void;
    disabled?: boolean;
}

export interface SortableItemState {
    index: number;
    handle: SortableHandleProps;
    isDragging: boolean;
    /** This render is the ghost that follows the pointer. */
    isOverlay: boolean;
}

/** The grip button that starts a drag (also sortable with the keyboard). */
export const SortableHandle = m.SortableHandle as ComponentType<SortableHandleProps & { label?: string; className?: string }>;

/**
 * A vertical list reordered by dragging a handle or with the keyboard:
 *
 *     <SortableList items={rows} getId={(row) => row.id} getLabel={(row) => row.title} onReorder={setRows}>
 *         {(row, { handle }) => <div><SortableHandle {...handle} />{row.title}</div>}
 *     </SortableList>
 */
export const SortableList = m.SortableList as <T>(props: {
    items: readonly T[];
    getId: (item: T) => string | number;
    /** The item's name for screen reader announcements ("Home moved above About"); without it, items are named by position. */
    getLabel?: (item: T) => string | null | undefined;
    onReorder: (items: T[], from: number, to: number) => void;
    children: (item: T, state: SortableItemState) => ReactNode;
    disabled?: boolean;
    className?: string;
    itemClassName?: string;
}) => ReactNode;
export const Switch = m.Switch as AnyComponent;
export const Table = m.Table as AnyComponent;
export const TableBody = m.TableBody as AnyComponent;
export const TableCell = m.TableCell as AnyComponent;
export const TableHead = m.TableHead as AnyComponent;
export const TableHeader = m.TableHeader as AnyComponent;
export const TableRow = m.TableRow as AnyComponent;
export const Tabs = m.Tabs as AnyComponent;
export const TabsContent = m.TabsContent as AnyComponent;
export const TabsList = m.TabsList as AnyComponent;
export const TabsTrigger = m.TabsTrigger as AnyComponent;
export const Textarea = m.Textarea as AnyComponent;
/** A small panel opened by its trigger (a button), for help or a few choices: `<Popover><PopoverTrigger asChild>…</PopoverTrigger><PopoverContent align="start">…</PopoverContent></Popover>`. */
export const Popover = m.Popover as AnyComponent;
/** What the panel is placed against instead of its trigger (a whole field, say): `<PopoverAnchor asChild><div>…<PopoverTrigger asChild>…</PopoverTrigger></div></PopoverAnchor>`. */
export const PopoverAnchor = m.PopoverAnchor as AnyComponent;
export const PopoverContent = m.PopoverContent as AnyComponent;
export const PopoverTrigger = m.PopoverTrigger as AnyComponent;
export const Tooltip = m.Tooltip as AnyComponent;
export const TooltipContent = m.TooltipContent as AnyComponent;
export const TooltipTrigger = m.TooltipTrigger as AnyComponent;

/** Toasts: toast.success('Saved'), toast.error('…'). */
export const toast = m.toast as { success(message: string): void; error(message: string): void; (message: string): void };

/** The header's breadcrumb trail for this page: useBreadcrumbs([{ label: 'Orders', href: adminUrl('shop/orders') }, { label: '#1042' }]). */
export const useBreadcrumbs = m.useBreadcrumbs as (crumbs: { label: string; href?: string }[]) => void;

/** A table's saved choices: the visible columns' keys in order, and rows per page; null where not chosen. */
export interface TablePreferences {
    columns: string[] | null;
    perPage: number | null;
}

/**
 * The signed-in user's saved columns and page size for a table, kept per
 * user on the server (`GET`/`PUT admin/preferences/{key}`; read them in
 * PHP with `Sunrice\Admin\Preferences\Preferences::table()`). Pass what
 * the page's props already hold as `initial`, or nothing to fetch them:
 *
 *     const { preferences, setColumns, setPerPage } = useTablePreferences('table.shop.orders', props.preferences);
 */
export const useTablePreferences = m.useTablePreferences as (key: string, initial?: Partial<TablePreferences> | null) => {
    preferences: TablePreferences;
    setColumns: (columns: string[] | null) => Promise<void>;
    setPerPage: (perPage: number | null) => Promise<void>;
};

/**
 * Guards a form with unsaved edits: asks before leaving the page and saves
 * on Ctrl/Cmd+S. useUnsavedChanges(form.isDirty, () => form.put(url)).
 */
export const useUnsavedChanges = m.useUnsavedChanges as (dirty: boolean, onSave?: () => void) => void;

/**
 * The edit lock of an editor page, for a type registered with
 * $panel->lockable(): holds it while the page is open, asks what to do when
 * someone else is editing, and keeps unsaved changes when taken over.
 * Render `dialogs`; disable saving while `readOnly`.
 */
export const useEditLock = m.useEditLock as (options: {
    type: string;
    id: string | number | null | undefined;
    locale?: string;
    enabled: boolean;
    getUnsaved: () => Record<string, unknown> | null;
    leaveTo: string;
    noun?: string;
}) => {
    readOnly: boolean;
    viewingBy: string | null;
    takenOver: boolean;
    kept: KeptEdit[];
    loadKept: (id: number) => Promise<Record<string, unknown>>;
    discardKept: (id: number) => Promise<void>;
    /** While viewing: asks to take over (the confirm dialog), as the first dialog's Take over does. */
    requestTakeOver: () => void;
    dialogs: ReactNode;
};

/** The banner while viewing what someone else is editing (useEditLock's viewingBy), with Take over when `onTakeOver` is given. */
export const ViewingNotice = m.ViewingNotice as ComponentType<{ by: string | null; onTakeOver?: () => void }>;
