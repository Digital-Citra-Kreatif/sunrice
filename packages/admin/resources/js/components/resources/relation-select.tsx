import * as React from 'react';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { SortableHandle, SortableList } from '@/components/app/sortable-list';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { adminUrl } from '@/lib/admin-url';
import { fetchJson, withQuery } from '@/lib/fetch-json';
import { cn } from '@/lib/utils';
import { ResourceKeyContext } from './resource-context';

export type RelationKey = string | number;

export interface RelationOption {
    value: RelationKey;
    label: string;
}

/** The address a relation field searches (ResourceController::options). */
export function optionsUrl(resource: string, field: string, query: string): string {
    return withQuery(adminUrl(`resources/${resource}/options/${field}`), { q: query.trim() });
}

/** Options as the person types (debounced), from the resource's options route. */
function useRelationSearch(field: string, open: boolean) {
    const resource = React.useContext(ResourceKeyContext);
    const [query, setQuery] = React.useState('');
    const [options, setOptions] = React.useState<RelationOption[]>([]);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    React.useEffect(() => {
        if (!open || resource === null) return;
        const controller = new AbortController();
        setLoading(true);
        const timer = window.setTimeout(() => {
            fetchJson<{ options: RelationOption[] }>(optionsUrl(resource, field, query), { signal: controller.signal })
                .then((body) => { setOptions(body.options); setError(null); })
                .catch((e: unknown) => { if (!controller.signal.aborted) setError(e instanceof Error ? e.message : 'Search failed.'); })
                .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, query === '' ? 0 : 200);

        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [open, resource, field, query]);

    return { query, setQuery, options, loading, error };
}

interface Props {
    id: string;
    field: string;
    multiple: boolean;
    value: RelationKey | RelationKey[] | null;
    /** Labels of the records chosen when the form was loaded. */
    selected: RelationOption[];
    required: boolean;
    disabled: boolean;
    invalid: boolean;
    placeholder?: string;
    onChange: (value: RelationKey | RelationKey[] | null) => void;
}

/** A searchable choice of related records: one (BelongsToSelect), or an ordered list (BelongsToManySelect). */
export function RelationSelect({ id, field, multiple, value, selected, required, disabled, invalid, placeholder, onChange }: Props) {
    const [open, setOpen] = React.useState(false);
    const { query, setQuery, options, loading, error } = useRelationSearch(field, open);
    // Labels seen so far, so chosen records stay named after the search moves on.
    const [labels, setLabels] = React.useState(() => new Map(selected.map((option) => [String(option.value), option.label])));
    const chosen = value === null ? [] : Array.isArray(value) ? value : [value];
    const labelOf = (key: RelationKey) => labels.get(String(key)) ?? `#${key}`;

    const choose = (option: RelationOption) => {
        setLabels((current) => new Map(current).set(String(option.value), option.label));
        if (!multiple) {
            onChange(option.value);
            setOpen(false);
            return;
        }
        const has = chosen.some((key) => String(key) === String(option.value));
        onChange(has ? chosen.filter((key) => String(key) !== String(option.value)) : [...chosen, option.value]);
    };

    const picker = (
        <Popover open={open} onOpenChange={(next) => { setOpen(next); if (!next) setQuery(''); }}>
            <PopoverTrigger asChild>
                <Button id={id} type="button" variant="outline" role="combobox" aria-expanded={open} aria-invalid={invalid} disabled={disabled}
                    className={cn('w-full justify-between font-normal', !multiple && chosen.length === 0 && 'text-muted-foreground')}>
                    <span className="truncate">{multiple ? 'Add…' : chosen.length > 0 ? labelOf(chosen[0]!) : (placeholder ?? 'Choose…')}</span>
                    <ChevronsUpDown className="opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-(--radix-popover-trigger-width) min-w-64 p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput placeholder="Search…" value={query} onValueChange={setQuery} />
                    <CommandList>
                        <CommandEmpty>{loading ? 'Searching…' : (error ?? 'Nothing found.')}</CommandEmpty>
                        {options.map((option) => {
                            const isChosen = chosen.some((key) => String(key) === String(option.value));
                            return (
                                <CommandItem key={String(option.value)} value={String(option.value)} onSelect={() => choose(option)}>
                                    <Check className={cn(isChosen ? 'opacity-100' : 'opacity-0')} />
                                    <span className="truncate">{option.label}</span>
                                </CommandItem>
                            );
                        })}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );

    if (!multiple) {
        return (
            <div className="flex items-center gap-2">
                {picker}
                {!required && chosen.length > 0 && !disabled && (
                    <Button type="button" variant="ghost" size="icon" aria-label="Clear" onClick={() => onChange(null)}><X /></Button>
                )}
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            {chosen.length > 0 && (
                <SortableList items={chosen} getId={(key) => String(key)} disabled={disabled} className="grid gap-1"
                    onReorder={(items) => onChange(items)}>
                    {(key, { handle }) => (
                        <div className="flex items-center gap-2 rounded-md border bg-card px-2 py-1 text-sm">
                            <SortableHandle {...handle} label={`Move ${labelOf(key)}`} />
                            <span className="flex-1 truncate">{labelOf(key)}</span>
                            {!disabled && (
                                <Button type="button" variant="ghost" size="icon" className="size-7" aria-label={`Remove ${labelOf(key)}`}
                                    onClick={() => onChange(chosen.filter((other) => String(other) !== String(key)))}><X /></Button>
                            )}
                        </div>
                    )}
                </SortableList>
            )}
            {picker}
        </div>
    );
}
