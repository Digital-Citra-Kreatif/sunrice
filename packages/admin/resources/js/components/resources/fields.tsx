import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { FieldInfo } from './types';

export type FieldValue = string | number | boolean | null;

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
    const common = { id, 'aria-invalid': invalid, required: field.required, placeholder: field.placeholder ?? undefined };

    switch (field.type) {
        case 'textarea':
            return <Textarea {...common} rows={Number(field.rows ?? 4)} maxLength={Number(field.maxLength) || undefined}
                value={value === null ? '' : String(value)} onChange={(e) => onChange(e.target.value)} />;
        case 'number':
            return <Input {...common} type="number" inputMode="decimal"
                min={field.min as number | undefined} max={field.max as number | undefined} step={String(field.step ?? 'any')}
                value={value === null ? '' : String(value)} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)} />;
        case 'toggle':
            return <Switch id={id} aria-invalid={invalid} checked={value === true} onCheckedChange={(checked) => onChange(checked)} />;
        case 'select': {
            const options = (field.options as { value: string; label: string }[] | undefined) ?? [];
            return (
                <Select value={value === null || value === '' ? (field.required ? undefined : NONE) : String(value)}
                    onValueChange={(selected) => onChange(selected === NONE ? null : selected)}>
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
