import { MediaPickerInput } from '@/components/media/media-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { FieldInfo } from './types';

export type FieldValue = string | number | boolean | string[] | number[] | null;

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
        case 'date':
            return <Input {...common} type={field.withTime ? 'datetime-local' : 'date'}
                value={value === null ? '' : String(value)} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)} />;
        default:
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
