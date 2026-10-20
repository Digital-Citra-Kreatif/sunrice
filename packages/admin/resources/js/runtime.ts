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
import { KeptEditsNotice, useEditLock, ViewingNotice } from '@/components/app/edit-lock';
import { moveItem, SortableHandle, SortableList } from '@/components/app/sortable-list';
import { MediaPickerInput } from '@/components/media/media-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
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
import { registerPage } from '@/lib/page-registry';
import { useUnsavedChanges } from '@/lib/use-unsaved-changes';

/** What `@sunrice/admin-sdk/ui` gives plugins. Keep in step with SHARED_MODULES (a test checks). */
const ui = {
    Badge, Button, Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle, Checkbox, CollapsibleCard,
    Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator, DatePicker,
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
    Input, KeptEditsNotice, Label, MediaPickerInput, moveItem, registerCommandSource,
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue, Separator, Skeleton, SortableHandle, SortableList, Switch,
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, Tabs, TabsContent, TabsList, TabsTrigger,
    Textarea, Tooltip, TooltipContent, TooltipTrigger, toast, useBreadcrumbs, useEditLock, useUnsavedChanges, ViewingNotice,
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
    const runtime: SunriceRuntime = { version: SDK_VERSION, modules, registerPage, adminUrl };
    Object.defineProperty(globalThis, 'Sunrice', { value: Object.freeze(runtime), writable: false, configurable: false });
}
