import * as React from 'react';
import { LoaderCircle, Search, X } from 'lucide-react';
import { adminUrl, t } from '@imajiner/admin-sdk';
import { Button, Input } from '@imajiner/admin-sdk/ui';

interface Item { id: number; title: string; hint: string | null }

interface Props {
    id: string;
    /** The panel's rich text link source ($panel->richTextLinkSource()): "term", "listing". */
    source: string;
    value: number | null;
    invalid: boolean;
    onChange: (value: number | null) => void;
    /** The input's name for screen readers. */
    label: string;
}

const DEBOUNCE_MS = 200;

async function search(source: string, query: string, signal: AbortSignal): Promise<Item[]> {
    const params = new URLSearchParams({ q: query.trim(), source });
    const response = await fetch(`${adminUrl('rich-text-links')}?${params.toString()}`, { signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) return [];
    const body = (await response.json()) as { groups?: { items?: Item[] }[] };

    return body.groups?.[0]?.items ?? [];
}

/**
 * Choose one term or listing page for a Link field, from the same search
 * as rich text links (GET admin/rich-text-links?source=term): a few on
 * focus, typing searches their titles. Only what the user may view.
 */
export function LinkSourcePicker({ id, source, value, invalid, onChange, label }: Props) {
    const [chosen, setChosen] = React.useState<Item | null>(null);
    const [query, setQuery] = React.useState('');
    const [open, setOpen] = React.useState(false);
    const [items, setItems] = React.useState<Item[] | null>(null);
    const [active, setActive] = React.useState(0);
    const listId = React.useId();

    // The chosen one's title, when it came with the value.
    React.useEffect(() => {
        if (value === null) { setChosen(null); return; }
        if (chosen?.id === value) return;
        const controller = new AbortController();
        void fetch(adminUrl(`rich-text-links/${source}:${value}`), { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(async (response) => {
                const item = response.ok ? ((await response.json()) as { item?: Item | null }).item : null;
                setChosen(item ?? { id: value, title: t('Not found'), hint: null });
            })
            .catch(() => undefined);
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, source]);

    React.useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        setItems(null);
        const timer = setTimeout(() => {
            void search(source, query, controller.signal).then((found) => { setItems(found); setActive(0); }).catch(() => undefined);
        }, DEBOUNCE_MS);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [open, query, source]);

    const pick = (item: Item) => { setChosen(item); onChange(item.id); setOpen(false); setQuery(''); };

    if (value !== null) {
        return (
            <div className="cms:flex cms:h-9 cms:min-w-0 cms:items-center cms:gap-1 cms:rounded-md cms:border cms:bg-muted/40 cms:pr-1 cms:pl-3 cms:text-sm">
                <span className="cms:truncate">{chosen?.title ?? t('Loading…')}</span>
                {chosen?.hint && <span className="cms:truncate cms:text-xs cms:text-muted-foreground">{chosen.hint}</span>}
                <Button type="button" variant="ghost" size="icon" className="cms:ml-auto cms:size-7" aria-label={t('Remove')} onClick={() => onChange(null)}><X /></Button>
            </div>
        );
    }

    return (
        // Over the inputs below it while its list is open.
        <div className={open ? 'cms:relative cms:z-30' : 'cms:relative'}>
            <Search className="cms:pointer-events-none cms:absolute cms:top-1/2 cms:left-2.5 cms:size-4 cms:-translate-y-1/2 cms:text-muted-foreground" aria-hidden />
            <Input id={id} value={query} autoComplete="off" className="cms:pl-8" placeholder={t('Search…')} aria-label={label} aria-invalid={invalid || undefined}
                role="combobox" aria-expanded={open && (items?.length ?? 0) > 0} aria-controls={listId} aria-activedescendant={open && items?.length ? `${listId}-${active}` : undefined}
                onFocus={() => setOpen(true)} onBlur={() => setOpen(false)}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => { setQuery(e.target.value); setOpen(true); }}
                onKeyDown={(e: React.KeyboardEvent) => {
                    const count = items?.length ?? 0;
                    if (e.key === 'ArrowDown' && count > 0) { e.preventDefault(); setActive((i) => (i + 1) % count); }
                    else if (e.key === 'ArrowUp' && count > 0) { e.preventDefault(); setActive((i) => (i - 1 + count) % count); }
                    else if (e.key === 'Enter' && items?.[active]) { e.preventDefault(); pick(items[active]); }
                    else if (e.key === 'Escape') setOpen(false);
                }} />
            {open && (
                <div id={listId} role="listbox" aria-label={label}
                    className="cms:absolute cms:z-30 cms:mt-1 cms:max-h-64 cms:w-full cms:overflow-y-auto cms:rounded-md cms:border cms:bg-card cms:p-1 cms:shadow-md">
                    {items === null && <div className="cms:flex cms:items-center cms:gap-2 cms:px-2 cms:py-1.5 cms:text-sm cms:text-muted-foreground"><LoaderCircle className="cms:size-4 cms:animate-spin" aria-hidden /> {t('Searching…')}</div>}
                    {items?.length === 0 && <div className="cms:px-2 cms:py-1.5 cms:text-sm cms:text-muted-foreground">{t('Nothing found.')}</div>}
                    {items?.map((item, index) => (
                        <div key={item.id} id={`${listId}-${index}`} role="option" aria-selected={index === active}
                            className={`cms:flex cms:cursor-pointer cms:items-baseline cms:gap-2 cms:rounded-sm cms:px-2 cms:py-1.5 cms:text-sm ${index === active ? 'cms:bg-accent cms:text-accent-foreground' : ''}`}
                            onMouseDown={(e: React.MouseEvent) => e.preventDefault()} onMouseEnter={() => setActive(index)} onClick={() => pick(item)}>
                            <span className="cms:truncate">{item.title}</span>
                            {item.hint && <span className="cms:shrink-0 cms:text-xs cms:text-muted-foreground">{item.hint}</span>}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
