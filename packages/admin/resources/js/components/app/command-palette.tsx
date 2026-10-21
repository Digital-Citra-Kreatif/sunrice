import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandLoading } from '@/components/ui/command';
import { navIcon } from '@/components/app/nav-icon';
import { adminUrl } from '@/lib/admin-url';
import {
    commandSourcesSnapshot, matchCommands, navigationCommands, queryCommandSources, subscribeCommandSources, type CommandItem as Item,
} from '@/lib/command-sources';
import type { SharedProps } from '@/types';
import { t } from '@/lib/i18n';

export { registerCommandSource, type CommandItem, type CommandSource } from '@/lib/command-sources';

/* The palette's open state, so the header's button and the shortcut share it. */
let isOpen = false;
const openListeners = new Set<() => void>();

export function setCommandPaletteOpen(open: boolean): void {
    isOpen = open;
    openListeners.forEach((listener) => listener());
}

export function useCommandPaletteOpen(): boolean {
    return React.useSyncExternalStore(
        (listener) => { openListeners.add(listener); return () => openListeners.delete(listener); },
        () => isOpen,
        () => false,
    );
}

/** Whether a keydown is the palette's shortcut (Cmd+K on a Mac, Ctrl+K elsewhere). */
export function isCommandPaletteShortcut(event: KeyboardEvent): boolean {
    return (event.metaKey || event.ctrlKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'k';
}

const DEBOUNCE_MS = 150;

/**
 * Cmd/Ctrl+K: jump to any page of the navigation, and search what plugins'
 * registerCommandSource() sources find.
 */
export function CommandPalette() {
    const open = useCommandPaletteOpen();
    const { navigation } = usePage<SharedProps>().props;
    const [query, setQuery] = React.useState('');
    const [results, setResults] = React.useState<Item[]>([]);
    const [loading, setLoading] = React.useState(false);
    const sources = React.useSyncExternalStore(subscribeCommandSources, commandSourcesSnapshot, commandSourcesSnapshot);

    const pages = React.useMemo(
        () => [{ id: 'dashboard', label: t('Dashboard'), group: t('Pages'), icon: 'layout-grid', url: adminUrl() }, ...navigationCommands(navigation, adminUrl, t('Pages'))],
        [navigation],
    );
    const matched = React.useMemo(() => matchCommands(pages, query), [pages, query]);

    React.useEffect(() => {
        if (!open) {
            setQuery('');
            setResults([]);
            setLoading(false);
        }
    }, [open]);

    React.useEffect(() => {
        if (!open || sources.every((source) => query.trim().length < source.minLength)) {
            setResults([]);
            setLoading(false);
            return;
        }
        const controller = new AbortController();
        setLoading(true);
        const timer = setTimeout(() => {
            void queryCommandSources(query, controller.signal).then((items) => {
                if (controller.signal.aborted) return;
                setResults(items);
                setLoading(false);
            });
        }, DEBOUNCE_MS);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, query, sources]);

    const choose = (item: Item) => {
        setCommandPaletteOpen(false);
        item.onSelect?.();
        if (item.url) router.visit(item.url);
    };

    const groups = new Map<string, Item[]>();
    for (const item of [...matched, ...results]) {
        const group = item.group ?? t('Results');
        groups.set(group, [...(groups.get(group) ?? []), item]);
    }

    return (
        <CommandDialog open={open} onOpenChange={setCommandPaletteOpen} title={t('Go to')} description={t('Type to find a page or search.')} showCloseButton={false} className="top-[12vh] translate-y-0 sm:top-[15vh]" shouldFilter={false}>
            <CommandInput placeholder={t('Go to…')} value={query} onValueChange={setQuery} />
            <CommandList>
                {loading && <CommandLoading><p className="px-3 py-2 text-xs text-muted-foreground">{t('Searching…')}</p></CommandLoading>}
                <CommandEmpty>{loading ? t('Searching…') : t('Nothing found.')}</CommandEmpty>
                {[...groups].map(([group, items]) => (
                    <CommandGroup key={group} heading={group}>
                        {items.map((item) => {
                            const Icon = navIcon(item.icon);
                            return (
                                <CommandItem key={`${group}:${item.id}`} value={`${group}:${item.id}`} onSelect={() => choose(item)}>
                                    <Icon />
                                    <span className="truncate">{item.label}</span>
                                    {item.description && <span className="ml-auto shrink-0 truncate text-xs text-muted-foreground">{item.description}</span>}
                                </CommandItem>
                            );
                        })}
                    </CommandGroup>
                ))}
            </CommandList>
        </CommandDialog>
    );
}
