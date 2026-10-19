/**
 * The panel's UI components, so plugin pages match the admin's look:
 *
 *     import { Button, Card, toast } from '@sunrice/admin-sdk/ui';
 *
 * In a bundle built with the Vite plugin these come straight from the
 * panel; this module is the fallback for other bundlers.
 */
import type { ComponentType } from 'react';

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
export const Dialog = m.Dialog as AnyComponent;
export const DialogContent = m.DialogContent as AnyComponent;
export const DialogDescription = m.DialogDescription as AnyComponent;
export const DialogFooter = m.DialogFooter as AnyComponent;
export const DialogHeader = m.DialogHeader as AnyComponent;
export const DialogTitle = m.DialogTitle as AnyComponent;
export const DialogTrigger = m.DialogTrigger as AnyComponent;
export const DropdownMenu = m.DropdownMenu as AnyComponent;
export const DropdownMenuContent = m.DropdownMenuContent as AnyComponent;
export const DropdownMenuItem = m.DropdownMenuItem as AnyComponent;
export const DropdownMenuLabel = m.DropdownMenuLabel as AnyComponent;
export const DropdownMenuSeparator = m.DropdownMenuSeparator as AnyComponent;
export const DropdownMenuTrigger = m.DropdownMenuTrigger as AnyComponent;
export const Input = m.Input as AnyComponent;
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
export const Select = m.Select as AnyComponent;
export const SelectContent = m.SelectContent as AnyComponent;
export const SelectItem = m.SelectItem as AnyComponent;
export const SelectTrigger = m.SelectTrigger as AnyComponent;
export const SelectValue = m.SelectValue as AnyComponent;
export const Separator = m.Separator as AnyComponent;
export const Skeleton = m.Skeleton as AnyComponent;
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

/**
 * Guards a form with unsaved edits: asks before leaving the page and saves
 * on Ctrl/Cmd+S. useUnsavedChanges(form.isDirty, () => form.put(url)).
 */
export const useUnsavedChanges = m.useUnsavedChanges as (dirty: boolean, onSave?: () => void) => void;
