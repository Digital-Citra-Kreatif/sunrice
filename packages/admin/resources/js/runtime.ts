import * as React from 'react';
import * as jsxRuntime from 'react/jsx-runtime';
import * as ReactDOM from 'react-dom';
import * as ReactDOMClient from 'react-dom/client';
import * as Inertia from '@inertiajs/react';
import { toast } from 'sonner';
import { SDK_VERSION, type SharedModuleName } from '@sunrice/admin-sdk/shared';
import type { SunriceRuntime } from '@sunrice/admin-sdk';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { registerCommandSource } from '@/components/app/command-palette';
import { DatePicker } from '@/components/app/date-picker';
import { DeveloperHelp } from '@/components/app/developer-help';
import { KeptEditsNotice, useEditLock, ViewingNotice } from '@/components/app/edit-lock';
import { moveItem, SortableHandle, SortableList } from '@/components/app/sortable-list';
import { MediaPickerInput } from '@/components/media/media-picker';
import { PageHeader } from '@/components/app/page-header';
import { RichTextEditor } from '@/components/app/rich-text';
import { SettingsLayout, SettingsNav } from '@/components/app/settings-nav';
import { TrashHint } from '@/components/app/trash-hint';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import {
    DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';
import { registerPage } from '@/lib/page-registry';
import { useTablePreferences } from '@/lib/use-table-preferences';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

/** What `@sunrice/admin-sdk/ui` gives plugins. Keep in step with SHARED_MODULES (a test checks). */
const ui = {
    Badge, Button, Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle, Checkbox, CollapsibleCard,
    Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator, DatePicker, DeveloperHelp,
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
    DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
    Input, KeptEditsNotice, Label, MediaPickerInput, moveItem, PageHeader, registerCommandSource, RichTextEditor,
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue, Separator, SettingsLayout, SettingsNav, Skeleton, SortableHandle, SortableList, Switch,
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, Tabs, TabsContent, TabsList, TabsTrigger,
    Textarea, Tooltip, TooltipContent, TooltipTrigger, toast, TrashHint, useBreadcrumbs, useEditLock, useTablePreferences, useUnsavedChanges, ViewingNotice,
};

const modules: Record<SharedModuleName, Record<string, unknown>> = {
    react: React,
    'react/jsx-runtime': jsxRuntime,
    'react-dom': ReactDOM,
    'react-dom/client': ReactDOMClient,
    '@inertiajs/react': Inertia,
    '@sunrice/admin-sdk/ui': ui,
};

/**
 * window.Sunrice, which plugin bundles use through @sunrice/admin-sdk.
 * Installed before the first page renders.
 */
export function installRuntime(): void {
    const runtime: SunriceRuntime = { version: SDK_VERSION, modules, registerPage, adminUrl, t };
    Object.defineProperty(globalThis, 'Sunrice', { value: Object.freeze(runtime), writable: false, configurable: false });
}
