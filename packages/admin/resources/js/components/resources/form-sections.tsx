import * as React from 'react';
import { InputError } from '@/components/app/input-error';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { slugify } from '@/lib/slug';
import { cn } from '@/lib/utils';
import { FieldInput, type FieldValue } from './fields';
import type { FieldInfo, SectionInfo } from './types';

const gridColumns: Record<number, string> = { 1: '', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4' };
const spans: Record<number, string> = { 1: '', 2: 'sm:col-span-2', 3: 'sm:col-span-3', 4: 'sm:col-span-4' };

interface Props {
    sections: SectionInfo[];
    data: Record<string, FieldValue>;
    errors: Partial<Record<string, string>>;
    onChange: (name: string, value: FieldValue) => void;
}

/** A form's sections as cards of fields (resources and settings). */
export function FormSections({ sections, data, errors, onChange: setValue }: Props) {
    // Slug fields (TextInput::slug()) follow their source field until one is typed in (or has a value).
    const slugs = sections.flatMap((section) => section.fields).filter((field) => typeof field.slugFrom === 'string');
    const [typed, setTyped] = React.useState(() => new Set(slugs.filter((field) => (data[field.name] ?? '') !== '').map((field) => field.name)));
    const onChange = (name: string, value: FieldValue) => {
        setValue(name, value);
        if (slugs.some((field) => field.name === name)) {
            setTyped((names) => {
                const next = new Set(names);
                value === '' || value === null ? next.delete(name) : next.add(name);
                return next;
            });
        }
        for (const field of slugs) {
            if (field.slugFrom === name && !typed.has(field.name)) {
                setValue(field.name, slugify(typeof value === 'string' ? value : '', String(field.slugSeparator ?? '-'), Number(field.maxLength) || 64));
            }
        }
    };

    return sections.map((section, index) => (
        <Card key={index}>
            {section.title && (
                <CardHeader>
                    <CardTitle>{section.title}</CardTitle>
                    {section.description && <CardDescription>{section.description}</CardDescription>}
                </CardHeader>
            )}
            <CardContent className={cn('grid gap-5', gridColumns[section.columns])}>
                {section.fields.map((field) => {
                    const error = errors[field.name];
                    const input = <FieldInput field={field} value={data[field.name] ?? null} invalid={!!error} onChange={(value) => onChange(field.name, value)} />;

                    return (
                        <div key={field.name} className={cn('grid min-w-0 grid-cols-1 content-start gap-2', spans[Math.min(field.span, section.columns)])}>
                            {field.type === 'toggle' ? (
                                <div className="flex items-center gap-3">
                                    {input}
                                    <Label id={`field-${field.name}-label`} htmlFor={`field-${field.name}`}>{field.label}</Label>
                                </div>
                            ) : (
                                <>
                                    <Label id={`field-${field.name}-label`} htmlFor={`field-${field.name}`}>
                                        {field.label}
                                        {field.required && <span className="text-destructive" aria-hidden>*</span>}
                                    </Label>
                                    {input}
                                </>
                            )}
                            {field.help && <p className="text-sm text-muted-foreground">{field.help}</p>}
                            <InputError message={error} />
                            {field.type === 'password' && field.confirmed === true && (
                                // Password::confirmed(): the same again, as "{name}_confirmation".
                                <PasswordConfirmation field={field} value={data[`${field.name}_confirmation`] ?? null}
                                    onChange={(value) => onChange(`${field.name}_confirmation`, value)} />
                            )}
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    ));
}

function PasswordConfirmation({ field, value, onChange }: { field: FieldInfo; value: FieldValue; onChange: (value: FieldValue) => void }) {
    const name = `${field.name}_confirmation`;

    return (
        <div className="mt-3 grid min-w-0 grid-cols-1 content-start gap-2">
            <Label id={`field-${name}-label`} htmlFor={`field-${name}`}>
                Confirm {field.label.toLowerCase()}
                {field.required && <span className="text-destructive" aria-hidden>*</span>}
            </Label>
            <FieldInput field={{ ...field, name, confirmed: false, placeholder: null }} value={value} invalid={false} onChange={onChange} />
        </div>
    );
}
