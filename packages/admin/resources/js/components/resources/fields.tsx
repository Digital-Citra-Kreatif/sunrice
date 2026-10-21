import * as React from 'react';
import { ArrowDown, ArrowUp, Globe, Mail, Plus, X } from 'lucide-react';
import { DatePicker } from '@/components/app/date-picker';
import { RichTextEditor } from '@/components/app/rich-text';
import { MediaPickerInput } from '@/components/media/media-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { isHexColor, normalizeHex, readableOn } from '@/lib/branding';
import { fixedValues, formatPairs, parsePairs, type Pair, withFixedValue } from '@/lib/pairs';
import { formatList, looksValid, networkOf, parseList } from '@/lib/networks';
import { slugInput } from '@/lib/slug';
import { type ChoiceOption, groupOptions, RelationSelect, SearchableSelect, type RelationOption } from './relation-select';
import type { FieldInfo } from './types';
import { t } from '@/lib/i18n';

export type FieldValue = string | number | boolean | string[] | number[] | (string | number)[] | null;

export interface CheckboxOption {
    value: string;
    label: string;
    group: string | null;
}

interface FieldProps {
    field: FieldInfo;
    value: FieldValue;
    onChange: (value: FieldValue) => void;
    invalid: boolean;
}

const NONE = '__none';

/** The input for one form field, by its type. */
export function FieldInput({ field, value, onChange, invalid }: FieldProps) {
    const id = `field-${field.name}`;
    const disabled = field.disabled === true;
    const common = { id, disabled, 'aria-invalid': invalid, required: field.required, placeholder: field.placeholder ?? undefined };

    switch (field.type) {
        case 'password':
            return <Input {...common} type="password" autoComplete="new-password"
                value={typeof value === 'string' ? value : ''} onChange={(e) => onChange(e.target.value)} />;
        case 'media':
            return <MediaPickerInput id={id} disabled={disabled} invalid={invalid} multiple={field.multiple === true}
                max={typeof field.max === 'number' ? field.max : null} types={(field.types as string[] | undefined) ?? []}
                value={typeof value === 'number' || (Array.isArray(value) && value.every((v) => typeof v === 'number')) ? value as number | number[] : null}
                onChange={onChange} />;
        case 'checkboxes':
            return <CheckboxList id={id} options={(field.options as CheckboxOption[] | undefined) ?? []} disabled={disabled}
                value={Array.isArray(value) ? value.map(String) : []} onChange={onChange} />;
        case 'textarea':
            return <Textarea {...common} rows={Number(field.rows ?? 4)} maxLength={Number(field.maxLength) || undefined}
                value={value === null ? '' : String(value)} onChange={(e) => onChange(e.target.value)} />;
        case 'number':
            return <Input {...common} type="number" inputMode="decimal"
                min={field.min as number | undefined} max={field.max as number | undefined} step={String(field.step ?? 'any')}
                value={value === null ? '' : String(value)} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)} />;
        case 'toggle':
            return <Switch id={id} disabled={disabled} aria-invalid={invalid} checked={value === true} onCheckedChange={(checked) => onChange(checked)} />;
        case 'select': {
            const options = (field.options as ChoiceOption[] | undefined) ?? [];
            const other = field.other as { label: string; placeholder: string | null } | undefined;
            if (other) {
                return <SelectWithOther id={id} field={field} options={options} other={other} disabled={disabled} invalid={invalid}
                    value={value === null || value === '' ? null : String(value)} onChange={onChange} />;
            }
            if (field.searchable === true) {
                return <SearchableSelect id={id} options={options} required={field.required === true} disabled={disabled} invalid={invalid}
                    placeholder={field.placeholder ?? undefined} value={value === null || value === '' ? null : String(value)} onChange={onChange} />;
            }
            return <PlainSelect id={id} options={options} required={field.required === true} disabled={disabled} invalid={invalid}
                placeholder={field.placeholder ?? undefined} value={value === null || value === '' ? null : String(value)} onChange={onChange} />;
        }
        case 'belongs_to':
        case 'belongs_to_many': {
            const multiple = field.type === 'belongs_to_many';
            const keys = Array.isArray(value) ? value : [];
            return <RelationSelect id={id} field={field.name} multiple={multiple} required={field.required} disabled={disabled} invalid={invalid}
                placeholder={field.placeholder ?? undefined} selected={(field.selected as RelationOption[] | undefined) ?? []}
                value={multiple ? keys : (typeof value === 'string' || typeof value === 'number' ? value : null)} onChange={onChange} />;
        }
        case 'richtext':
            return <RichTextEditor id={id} labelledBy={`${id}-label`} invalid={invalid} disabled={disabled} images={field.images !== false}
                value={typeof value === 'string' ? value : ''} onChange={onChange} />;
        case 'pairs':
            if (Array.isArray(field.fixed)) {
                return <FixedPairsInput id={id} keys={field.fixed as { key: string; label: string }[]} disabled={disabled} invalid={invalid}
                    value={typeof value === 'string' ? value : ''} onChange={onChange} />;
            }
            return <PairsInput id={id} field={field} disabled={disabled} invalid={invalid} value={typeof value === 'string' ? value : ''} onChange={onChange} />;
        case 'list':
            return <ListInput id={id} field={field} disabled={disabled} invalid={invalid} value={typeof value === 'string' ? value : ''} onChange={onChange} />;
        case 'date':
            return <DatePicker id={id} disabled={disabled} invalid={invalid} required={field.required === true} withTime={field.withTime === true}
                placeholder={field.placeholder ?? undefined} value={value === null ? null : String(value)} onChange={onChange} />;
        default:
            if (field.inputType === 'color') {
                return <ColorInput common={common} value={typeof value === 'string' ? value : ''} onChange={onChange} />;
            }
            if (typeof field.slugFrom === 'string') {
                return <Input {...common} type="text" className="font-mono" maxLength={Number(field.maxLength) || undefined} spellCheck={false} autoCapitalize="off"
                    value={value === null ? '' : String(value)} onChange={(e) => onChange(slugInput(e.target.value, String(field.slugSeparator ?? '-')))} />;
            }
            return <Input {...common} type={String(field.inputType ?? 'text')} maxLength={Number(field.maxLength) || undefined}
                value={value === null ? '' : String(value)} onChange={(e) => onChange(e.target.value)} />;
    }
}

/** A choice from fixed options (Select), under headings when they have groups. */
function PlainSelect({ id, options, value, required, disabled, invalid, placeholder, onChange, extra }: {
    id: string; options: ChoiceOption[]; value: string | null; required: boolean; disabled: boolean; invalid: boolean;
    placeholder?: string; onChange: (value: string | null) => void; extra?: ChoiceOption;
}) {
    const item = (option: ChoiceOption) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>;

    return (
        <Select value={value === null ? (required ? undefined : NONE) : value} disabled={disabled} onValueChange={(selected) => onChange(selected === NONE ? null : selected)}>
            <SelectTrigger id={id} aria-invalid={invalid} className="w-full"><SelectValue placeholder={placeholder ?? t('Choose…')} /></SelectTrigger>
            <SelectContent>
                {!required && <SelectItem value={NONE}>—</SelectItem>}
                {groupOptions(options).map(([group, items]) => group === ''
                    ? <React.Fragment key="">{items.map(item)}</React.Fragment>
                    : <SelectGroup key={group}><SelectLabel>{group}</SelectLabel>{items.map(item)}</SelectGroup>)}
                {extra && item(extra)}
            </SelectContent>
        </Select>
    );
}

const OTHER = '__other';

/**
 * Select::other(): the options and "Other…", which shows a text input for a
 * value not listed. A saved value that isn't an option shows in that input.
 */
function SelectWithOther({ id, field, options, other, value, disabled, invalid, onChange }: {
    id: string; field: FieldInfo; options: ChoiceOption[]; other: { label: string; placeholder: string | null };
    value: string | null; disabled: boolean; invalid: boolean; onChange: (value: string | null) => void;
}) {
    const listed = value !== null && options.some((option) => option.value === value);
    const [typing, setTyping] = React.useState(value !== null && !listed);
    const input = React.useRef<HTMLInputElement>(null);
    const showOther = typing || (value !== null && !listed);
    const extra = { value: OTHER, label: other.label };
    const choose = (chosen: string | null) => {
        if (chosen === OTHER) {
            setTyping(true);
            onChange(listed ? '' : value);
            window.setTimeout(() => input.current?.focus(), 0);
            return;
        }
        setTyping(false);
        onChange(chosen);
    };
    const props = { id, options, required: field.required === true, disabled, invalid, placeholder: field.placeholder ?? undefined, extra,
        value: showOther ? OTHER : value, onChange: choose };

    return (
        <div className="grid gap-2">
            {field.searchable === true ? <SearchableSelect {...props} /> : <PlainSelect {...props} />}
            {showOther && (
                <Input ref={input} id={`${id}-other`} aria-label={other.label} aria-invalid={invalid} disabled={disabled} placeholder={other.placeholder ?? undefined}
                    value={value ?? ''} onChange={(e) => onChange(e.target.value === '' ? '' : e.target.value)} />
            )}
        </div>
    );
}

/** ListInput: one input per value, with Add and remove, kept as one value per line. */
function ListInput({ id, field, value, disabled, invalid, onChange }: {
    id: string; field: FieldInfo; value: string; disabled: boolean; invalid: boolean; onChange: (value: string) => void;
}) {
    const emails = field.accepts === 'email';
    const [rows, setRows] = React.useState<string[]>(() => parseList(value, emails));
    const [focus, setFocus] = React.useState<number | null>(null);
    // Changed from outside (a reset, a save): show that.
    React.useEffect(() => {
        if (formatList(rows) !== value) setRows(parseList(value, emails));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);
    const update = (next: string[]) => {
        setRows(next);
        onChange(formatList(next));
    };
    const kind = field.accepts as 'url' | 'email' | null | undefined;
    const max = typeof field.max === 'number' ? field.max : null;
    const label = field.label;
    // After a refused save, mark the rows that look wrong (all of them when none does: too many).
    const wrong = rows.map((row) => !looksValid(row, kind, field.httpsOnly === true));
    const rowInvalid = (index: number) => invalid && (wrong[index] === true || !wrong.includes(true));

    return (
        <div id={id} role="group" aria-invalid={invalid} className="grid gap-2">
            {rows.map((row, index) => {
                const network = field.networks === true ? networkOf(row) : null;
                return (
                    <div key={index} className="flex items-center gap-2">
                        {field.networks === true && (
                            network
                                ? <span aria-hidden="true" title={network.name} className="flex size-8 shrink-0 items-center justify-center rounded-md text-xs font-semibold text-white" style={{ background: network.color }}>{network.mark}</span>
                                : <span aria-hidden="true" className="flex size-8 shrink-0 items-center justify-center rounded-md border text-muted-foreground"><Globe className="size-4" /></span>
                        )}
                        {kind === 'email' && <Mail aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />}
                        <Input aria-label={`${label} ${index + 1}`} disabled={disabled} aria-invalid={rowInvalid(index)} value={row}
                            type={kind === 'email' ? 'email' : kind === 'url' ? 'url' : 'text'} inputMode={kind === 'email' ? 'email' : kind === 'url' ? 'url' : undefined}
                            spellCheck={kind ? false : undefined} autoCapitalize={kind ? 'off' : undefined}
                            placeholder={field.placeholder ?? (kind === 'url' ? 'https://…' : undefined)} autoFocus={focus === index}
                            onChange={(e) => update(rows.map((r, i) => (i === index ? e.target.value : r)))} />
                        {field.networks === true && <span className="hidden w-20 shrink-0 truncate text-xs text-muted-foreground sm:inline">{network?.name}</span>}
                        <Button type="button" variant="ghost" size="icon" className="size-8 shrink-0 text-destructive" disabled={disabled}
                            aria-label={t('Remove :item :number', { item: label, number: index + 1 })} onClick={() => update(rows.filter((_, i) => i !== index))}><X /></Button>
                    </div>
                );
            })}
            <div>
                <Button type="button" variant="outline" size="sm" disabled={disabled || (max !== null && rows.length >= max)}
                    onClick={() => { setFocus(rows.length); setRows([...rows, '']); }}>
                    <Plus />{String(field.addLabel ?? t('Add'))}
                </Button>
            </div>
        </div>
    );
}

/** Checkboxes for several choices, under group headings when the options have groups. */
function CheckboxList({ id, options, value, disabled, onChange }: {
    id: string;
    options: CheckboxOption[];
    value: string[];
    disabled: boolean;
    onChange: (value: string[]) => void;
}) {
    if (options.length === 0) {
        return <p id={id} className="text-sm text-muted-foreground">{t('Nothing to choose from.')}</p>;
    }
    const groups = new Map<string, CheckboxOption[]>();
    for (const option of options) {
        groups.set(option.group ?? '', [...(groups.get(option.group ?? '') ?? []), option]);
    }
    const toggle = (option: string, checked: boolean) =>
        onChange(checked ? [...value.filter((v) => v !== option), option] : value.filter((v) => v !== option));

    return (
        <div id={id} role="group" className="grid gap-4">
            {[...groups].map(([group, items]) => (
                <fieldset key={group} className="grid gap-2">
                    {group !== '' && <legend className="mb-1 text-sm font-medium">{group}</legend>}
                    <div className="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                        {items.map((option) => {
                            const optionId = `${id}-${option.value}`;
                            return (
                                <div key={option.value} className="flex items-center gap-2">
                                    <Checkbox id={optionId} disabled={disabled} checked={value.includes(option.value)}
                                        onCheckedChange={(checked) => toggle(option.value, checked === true)} />
                                    <Label htmlFor={optionId} className="font-normal">{option.label}</Label>
                                </div>
                            );
                        })}
                    </div>
                </fieldset>
            ))}
        </div>
    );
}

/** A #rrggbb colour: a picker and the code, with a sample of the text that goes on it. Empty means none. */
function ColorInput({ common, value, onChange }: {
    common: { id: string; disabled: boolean; 'aria-invalid': boolean; required?: boolean; placeholder?: string };
    value: string;
    onChange: (value: string) => void;
}) {
    const valid = isHexColor(value);

    return (
        <div className="flex items-center gap-2">
            {/* The swatch is ours (the native one shows black when empty); the picker sits invisibly on top. */}
            <span className="relative size-10 shrink-0 overflow-hidden rounded-lg border border-input bg-card shadow-xs transition-[border-color,box-shadow] duration-150 ease-out-quart focus-within:border-ring/70 focus-within:ring-4 focus-within:ring-ring-glow hover:border-ring/45"
                style={{ background: valid ? value : 'linear-gradient(to top right, transparent calc(50% - 0.75px), var(--border) 50%, transparent calc(50% + 0.75px)), var(--card)' }}>
                <input type="color" aria-label={t('Pick a colour')} disabled={common.disabled} value={valid ? value.toLowerCase() : '#000000'}
                    onChange={(e) => onChange(e.target.value)}
                    className="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed" />
            </span>
            {/* Not cut to 7 characters: what's typed stays, and the server says what's wrong. Six hex digits get their #. */}
            <Input {...common} type="text" maxLength={20} spellCheck={false} className="font-mono" value={value} onChange={(e) => onChange(normalizeHex(e.target.value))} />
            {valid && (
                <span aria-hidden="true" className="flex h-10 shrink-0 items-center rounded-lg px-3 text-sm font-medium shadow-xs" style={{ background: value, color: readableOn(value) }}>Aa</span>
            )}
        </div>
    );
}

/** A labelled input per given key (Pairs with ->fixed()), kept as "key: value" lines. */
function FixedPairsInput({ id, keys, value, disabled, invalid, onChange }: {
    id: string; keys: { key: string; label: string }[]; value: string; disabled: boolean; invalid: boolean; onChange: (value: string) => void;
}) {
    const values = fixedValues(value, keys.map((each) => each.key));

    return (
        <div id={id} role="group" aria-invalid={invalid} className="grid gap-3">
            {keys.map(({ key, label }) => (
                <div key={key} className="grid gap-1.5">
                    <Label htmlFor={`${id}-${key}`} className="text-sm font-normal text-muted-foreground">{label}</Label>
                    <Input id={`${id}-${key}`} disabled={disabled} aria-invalid={invalid} value={values[key] ?? ''}
                        onChange={(e) => onChange(withFixedValue(value, key, e.target.value))} />
                </div>
            ))}
        </div>
    );
}

/** Rows of a key and a value (Pairs), kept as "key: value" lines. */
function PairsInput({ id, field, value, disabled, invalid, onChange }: {
    id: string; field: FieldInfo; value: string; disabled: boolean; invalid: boolean; onChange: (value: string) => void;
}) {
    const suggestions = (field.suggestions as Pair[] | undefined) ?? [];
    const [rows, setRows] = React.useState<Pair[]>(() => parsePairs(value));
    // Changed from outside (a reset, another save): show that.
    React.useEffect(() => {
        if (formatPairs(rows) !== value) setRows(parsePairs(value));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);
    const update = (next: Pair[]) => {
        setRows(next);
        onChange(formatPairs(next));
    };
    const set = (index: number, pair: Pair) => update(rows.map((row, i) => (i === index ? pair : row)));
    const move = (index: number, by: -1 | 1) => {
        const next = [...rows];
        const [row] = next.splice(index, 1);
        if (row) next.splice(index + by, 0, row);
        update(next);
    };
    const listId = `${id}-suggestions`;
    const extra = field.extra as { label: string; placeholder: string | null; firstPlaceholder: string | null } | null | undefined;
    const columns = extra ? 'grid-cols-[7rem_1fr_9rem_6.5rem]' : 'grid-cols-[8rem_1fr_6.5rem]';

    return (
        <div id={id} role="group" aria-invalid={invalid} className="grid gap-2">
            {rows.length > 0 && (
                <div className={`grid ${columns} gap-2 text-xs font-medium text-muted-foreground`}>
                    <span>{String(field.keyLabel ?? t('Key'))}</span><span>{String(field.valueLabel ?? t('Value'))}</span>{extra && <span>{extra.label}</span>}<span className="sr-only">{t('Order')}</span>
                </div>
            )}
            {rows.map((row, index) => (
                <div key={index} className={`grid ${columns} items-center gap-2`}>
                    <Input aria-label={`${String(field.keyLabel ?? t('Key'))} ${index + 1}`} list={listId} disabled={disabled} className="font-mono" value={row.key}
                        onChange={(e) => {
                            const key = e.target.value;
                            const known = suggestions.find((s) => s.key === key.trim());
                            set(index, { key, value: row.value === '' && known ? known.value : row.value });
                        }} />
                    <div className="flex items-center gap-2">
                        <Input aria-label={`${String(field.valueLabel ?? t('Value'))} ${index + 1}`} disabled={disabled} value={row.value}
                            onChange={(e) => set(index, { ...row, value: e.target.value })} />
                        {index === 0 && typeof field.firstBadge === 'string' && <Badge variant="secondary">{field.firstBadge}</Badge>}
                    </div>
                    {extra && (
                        <Input aria-label={`${extra.label} ${index + 1}`} disabled={disabled} className="font-mono" value={row.extra ?? ''}
                            placeholder={(index === 0 && extra.firstPlaceholder !== null ? extra.firstPlaceholder : extra.placeholder ?? '').replace('{key}', row.key || 'code')}
                            onChange={(e) => set(index, { ...row, extra: e.target.value })} />
                    )}
                    <div className="flex items-center">
                        <Button type="button" variant="ghost" size="icon" className="size-8" disabled={disabled || index === 0} aria-label={t('Move up')} onClick={() => move(index, -1)}><ArrowUp /></Button>
                        <Button type="button" variant="ghost" size="icon" className="size-8" disabled={disabled || index === rows.length - 1} aria-label={t('Move down')} onClick={() => move(index, 1)}><ArrowDown /></Button>
                        <Button type="button" variant="ghost" size="icon" className="size-8 text-destructive" disabled={disabled} aria-label={t('Remove')} onClick={() => update(rows.filter((_, i) => i !== index))}><X /></Button>
                    </div>
                </div>
            ))}
            <datalist id={listId}>{suggestions.map((s) => <option key={s.key} value={s.key}>{s.value}</option>)}</datalist>
            <div>
                <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={() => setRows([...rows, { key: '', value: '' }])}>
                    <Plus />{String(field.addLabel ?? t('Add'))}
                </Button>
            </div>
        </div>
    );
}
