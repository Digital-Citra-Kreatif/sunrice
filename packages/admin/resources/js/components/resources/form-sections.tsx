import { InputError } from '@/components/app/input-error';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { Card, CardContent } from '@/components/ui/card';
import { isShownBy, type ShowWhen } from '@/lib/show-when';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { FieldInput, type FieldValue } from './fields';
import type { SectionInfo } from './types';

const gridColumns: Record<number, string> = { 1: '', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4' };
const spans: Record<number, string> = { 1: '', 2: 'sm:col-span-2', 3: 'sm:col-span-3', 4: 'sm:col-span-4' };

interface Props {
    sections: SectionInfo[];
    /** Remembers which titled sections are folded, under this key plus the title. */
    storageKey?: string;
    data: Record<string, FieldValue>;
    errors: Partial<Record<string, string>>;
    onChange: (name: string, value: FieldValue) => void;
}

/** A form's sections as cards of fields (resources and settings). */
export function FormSections({ sections, data, errors, storageKey, onChange }: Props) {
    return sections.map((section, index) => {
        // Fields whose condition isn't met are hidden (showWhen), a section of only those too.
        const fields = section.fields.filter((field) => isShownBy(field.showWhen as ShowWhen | null | undefined, data));
        if (fields.length === 0) return null;
        const content = (
            <div className={cn('grid gap-5', gridColumns[section.columns])}>
                {fields.map((field) => {
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
            </div>
        );

        // A titled section folds away (remembered), and opens on an error inside.
        return section.title ? (
            <CollapsibleCard key={index} title={section.title} description={section.description ?? undefined} titleClassName="text-base"
                storageKey={storageKey ? `${storageKey}:${section.title}` : undefined} hasErrors={fields.some((field) => errors[field.name] !== undefined)}>
                {content}
            </CollapsibleCard>
        ) : (
            <Card key={index}><CardContent>{content}</CardContent></Card>
        );
    });
}
