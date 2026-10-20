import { InputError } from '@/components/app/input-error';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { FieldInput, type FieldValue } from './fields';
import type { SectionInfo } from './types';

const gridColumns: Record<number, string> = { 1: '', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4' };
const spans: Record<number, string> = { 1: '', 2: 'sm:col-span-2', 3: 'sm:col-span-3', 4: 'sm:col-span-4' };

interface Props {
    sections: SectionInfo[];
    data: Record<string, FieldValue>;
    errors: Partial<Record<string, string>>;
    onChange: (name: string, value: FieldValue) => void;
}

/** A form's sections as cards of fields (resources and settings). */
export function FormSections({ sections, data, errors, onChange }: Props) {
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
                    // A section's only field named like the section: the card's title says it already.
                    const label = section.fields.length === 1 && field.label === section.title ? null : field.label;
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
                                    {label === null ? <span id={`field-${field.name}-label`} className="sr-only">{field.label}</span> : (
                                        <Label id={`field-${field.name}-label`} htmlFor={`field-${field.name}`}>
                                            {label}
                                            {field.required && <span className="text-destructive" aria-hidden>*</span>}
                                        </Label>
                                    )}
                                    {input}
                                </>
                            )}
                            {field.help && <p className="mt-1 text-sm text-muted-foreground">{field.help}</p>}
                            <InputError message={error} />
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    ));
}
