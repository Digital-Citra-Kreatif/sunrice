import * as React from 'react';
import { LoaderCircle, Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { adminUrl } from '@/lib/admin-url';
import { linkGroupsOf, linkSearchQuery, type LinkItem } from '@/lib/rich-link';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';

export interface LinkSourcePickerProps {
    id?: string;
    /** A rich text link source's key ($panel->richTextLinkSource()): "entry", "term", "listing". */
    source: string;
    /** The chosen item's id, or null. */
    value: number | null;
    /** The chosen item, or null when it's cleared (✕). */
    onChange: (item: LinkItem | null) => void;
    /** The search input's name for screen readers. */
    label: string;
    invalid?: boolean;
    /** Enter on the search with nothing to pick, Escape: for a menu to go back to the text. */
    onEscape?: () => void;
    /** The search input, for focusing it. */
    inputRef?: React.Ref<HTMLInputElement>;
    className?: string;
}

const DEBOUNCE_MS = 200;

/** Chosen items' titles, by "source:id", once asked (null: gone, or not the user's to see). */
const named = new Map<string, LinkItem | null>();

/**
 * Choose one item of a link source: a few show on focus, typing searches
 * their titles (GET admin/rich-text-links?source=…); the chosen one shows
 * as a chip with ✕. Only what the user may view. The rich text editor's
 * link menu and the CMS's Link fields use it.
 */
export function LinkSourcePicker({ id, source, value, onChange, label, invalid = false, onEscape, inputRef, className }: LinkSourcePickerProps) {
    const key = value === null ? null : `${source}:${value}`;
    const [, setLoaded] = React.useState(0);
    const [query, setQuery] = React.useState('');
    const [open, setOpen] = React.useState(false);
    const [items, setItems] = React.useState<LinkItem[] | null>(null);
    const [active, setActive] = React.useState(0);
    const listId = React.useId();

    React.useEffect(() => {
        if (key === null || named.has(key)) return;
        const controller = new AbortController();
        void fetch(adminUrl(`rich-text-links/${key}`), { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(async (response) => {
                const item = response.ok ? ((await response.json()) as { item?: LinkItem | null }).item : null;
                named.set(key, item && typeof item.title === 'string' ? item : null);
                setLoaded((n) => n + 1);
            })
            .catch(() => undefined);
        return () => controller.abort();
    }, [key]);

    React.useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        setItems(null);
        const timer = setTimeout(() => {
            void fetch(`${adminUrl('rich-text-links')}?${linkSearchQuery(query, source)}`, { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(async (response) => { setItems(response.ok ? (linkGroupsOf(await response.json())[0]?.items ?? []) : []); setActive(0); })
                .catch(() => undefined);
        }, DEBOUNCE_MS);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [open, query, source]);

    const pick = (item: LinkItem) => { named.set(item.link, item); onChange(item); setOpen(false); setQuery(''); };

    if (key !== null) {
        const item = named.get(key);
        return (
            <div className={cn('flex h-9 min-w-0 items-center gap-1 rounded-md border bg-muted/40 pr-1 pl-3 text-sm', item === null && 'border-amber-500/70', className)}>
                <span className="truncate">{item === undefined ? t('Loading…') : item === null ? t('Not found: shows as text') : item.title}</span>
                {item?.hint && <span className="truncate text-xs text-muted-foreground">{item.hint}</span>}
                <Button type="button" variant="ghost" size="icon" className="ml-auto size-7" aria-label={t('Remove')} onClick={() => onChange(null)}
                    onMouseDown={(e: React.MouseEvent) => e.preventDefault()}><X /></Button>
            </div>
        );
    }

    return (
        // Over what's below it while its list is open.
        <div className={cn('relative min-w-0', open && 'z-30', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
            <Input ref={inputRef} id={id} value={query} autoComplete="off" className="h-9 pl-8 text-sm" placeholder={t('Search…')} aria-label={label} aria-invalid={invalid || undefined}
                role="combobox" aria-expanded={open && (items?.length ?? 0) > 0} aria-controls={listId} aria-activedescendant={open && items?.length ? `${listId}-${active}` : undefined}
                onFocus={() => setOpen(true)} onBlur={() => setOpen(false)}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => { setQuery(e.target.value); setOpen(true); }}
                onKeyDown={(e: React.KeyboardEvent) => {
                    const count = items?.length ?? 0;
                    if (e.key === 'ArrowDown' && count > 0) { e.preventDefault(); setOpen(true); setActive((i) => (i + 1) % count); }
                    else if (e.key === 'ArrowUp' && count > 0) { e.preventDefault(); setActive((i) => (i - 1 + count) % count); }
                    else if (e.key === 'Enter') { e.preventDefault(); const item = items?.[active]; if (open && item) pick(item); }
                    else if (e.key === 'Escape' && open && count > 0) { e.preventDefault(); e.stopPropagation(); setOpen(false); }
                    else if (e.key === 'Escape') onEscape?.();
                }} />
            {open && (
                <div id={listId} role="listbox" aria-label={label}
                    className="absolute z-30 mt-1 max-h-64 w-full min-w-56 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                    {items === null && <div className="flex items-center gap-2 px-2 py-1.5 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin" aria-hidden /> {t('Searching…')}</div>}
                    {items?.length === 0 && <div className="px-2 py-1.5 text-sm text-muted-foreground">{t('Nothing found.')}</div>}
                    {items?.map((item, index) => (
                        <div key={item.link} id={`${listId}-${index}`} role="option" aria-selected={index === active}
                            className={cn('flex cursor-pointer items-baseline gap-2 rounded-sm px-2 py-1.5 text-sm', index === active && 'bg-accent text-accent-foreground')}
                            onMouseDown={(e: React.MouseEvent) => e.preventDefault()} onMouseEnter={() => setActive(index)} onClick={() => pick(item)}>
                            <span className="truncate">{item.title}</span>
                            {item.hint && <span className="shrink-0 truncate text-xs text-muted-foreground">{item.hint}</span>}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
