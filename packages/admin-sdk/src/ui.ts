/**
 * The panel's UI components, so plugin pages match the admin's look:
 *
 *     import { Button, Card, toast } from '@sunrice/admin-sdk/ui';
 *
 * In a bundle built with the Vite plugin these come straight from the
 * panel; this module is the fallback for other bundlers.
 */
import type { ComponentType, ReactNode } from 'react';

type AnyComponent = ComponentType<any>;

function ui(): Record<string, unknown> {
    const modules = globalThis.Sunrice?.modules;
    if (!modules) {
        throw new Error('[sunrice] @sunrice/admin-sdk/ui needs the Sunrice admin panel (window.Sunrice is missing).');
    }

    return modules['@sunrice/admin-sdk/ui'] ?? {};
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

/** cmdk's command list (a filterable list of items), for comboboxes and pickers. */
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
export const Input = m.Input as AnyComponent;
export interface KeptEdit { id: number; by: string | null; taken_by: string | null; at: string | null }
/** "Ana's unsaved changes were kept" notices, with Load and Discard (see useEditLock). */
export const KeptEditsNotice = m.KeptEditsNotice as ComponentType<{ kept: KeptEdit[]; onLoad: (id: number) => void; onDiscard: (id: number) => void; disabled?: boolean }>;
export const Label = m.Label as AnyComponent;

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

/**
 * The block at the top of a page: the title in the panel's display style,
 * an optional description, a back button, buttons on the right (`actions`),
 * and children beside the title (a badge, a switcher, "Unsaved changes").
 */
export const PageHeader = m.PageHeader as ComponentType<{
    title: ReactNode;
    description?: ReactNode;
    back?: { href: string; label: string };
    actions?: ReactNode;
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
    disabled?: boolean;
}>;
export const Select = m.Select as AnyComponent;
export const SelectContent = m.SelectContent as AnyComponent;
export const SelectItem = m.SelectItem as AnyComponent;
export const SelectTrigger = m.SelectTrigger as AnyComponent;
export const SelectValue = m.SelectValue as AnyComponent;
export const Separator = m.Separator as AnyComponent;
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
 *     <SortableList items={rows} getId={(row) => row.id} onReorder={setRows}>
 *         {(row, { handle }) => <div><SortableHandle {...handle} />{row.title}</div>}
 *     </SortableList>
 */
export const SortableList = m.SortableList as <T>(props: {
    items: readonly T[];
    getId: (item: T) => string | number;
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
    dialogs: ReactNode;
};

/** The banner while viewing what someone else is editing (useEditLock's viewingBy). */
export const ViewingNotice = m.ViewingNotice as ComponentType<{ by: string | null }>;
