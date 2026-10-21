import { DatePicker } from '@/components/app/date-picker';
import { RichTextEditor } from '@/components/app/rich-text';
import { MediaPickerInput } from '@/components/media/media-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { isHexColor, normalizeHex, readableOn } from '@/lib/branding';
import { RelationSelect, SearchableSelect, type RelationOption } from './relation-select';
import type { FieldInfo } from './types';

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
            const options = (field.options as { value: string; label: string }[] | undefined) ?? [];
            if (field.searchable === true) {
                return <SearchableSelect id={id} options={options} required={field.required === true} disabled={disabled} invalid={invalid}
                    placeholder={field.placeholder ?? undefined} value={value === null || value === '' ? null : String(value)} onChange={onChange} />;
            }
            return (
                <Select value={value === null || value === '' ? (field.required ? undefined : NONE) : String(value)}
                    disabled={disabled} onValueChange={(selected) => onChange(selected === NONE ? null : selected)}>
                    <SelectTrigger id={id} aria-invalid={invalid} className="w-full"><SelectValue placeholder={field.placeholder ?? 'Choose…'} /></SelectTrigger>
                    <SelectContent>
                        {!field.required && <SelectItem value={NONE}>—</SelectItem>}
                        {options.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                    </SelectContent>
                </Select>
            );
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
        case 'date':
            return <DatePicker id={id} disabled={disabled} invalid={invalid} required={field.required === true} withTime={field.withTime === true}
                placeholder={field.placeholder ?? undefined} value={value === null ? null : String(value)} onChange={onChange} />;
        default:
            if (field.inputType === 'color') {
                return <ColorInput common={common} value={typeof value === 'string' ? value : ''} onChange={onChange} />;
            }
            return <Input {...common} type={String(field.inputType ?? 'text')} maxLength={Number(field.maxLength) || undefined}
                value={value === null ? '' : String(value)} onChange={(e) => onChange(e.target.value)} />;
    }
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
        return <p id={id} className="text-sm text-muted-foreground">Nothing to choose from.</p>;
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
                    <div className="grid gap-2 sm:grid-cols-2">
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
                <input type="color" aria-label="Pick a colour" disabled={common.disabled} value={valid ? value.toLowerCase() : '#000000'}
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
