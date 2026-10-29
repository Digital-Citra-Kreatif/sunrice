import * as React from 'react';
import { InputError } from '@/components/app/input-error';
import { PasswordChecklist } from '@/components/app/password-checklist';
import { CollapsibleCard } from '@/components/app/collapsible-card';
import { Card, CardContent } from '@/components/ui/card';
import { TriangleAlert } from 'lucide-react';
import { changeWarning, savedWarning } from '@/lib/change-warning';
import { livePlaceholder, type HelpValue } from '@/lib/live-help';
import { isPasswordRules } from '@/lib/password-rules';
import { fieldGroups } from '@/lib/field-groups';
import { isShownBy, type ShowWhen } from '@/lib/show-when';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { FieldHelp, FormLinks, SectionLinks } from './field-help';
import { FieldInput, type FieldValue } from './fields';
import type { FieldInfo, SectionInfo } from './types';

const gridColumns: Record<number, string> = { 1: '', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4' };
const spans: Record<number, string> = { 1: '', 2: 'sm:col-span-2', 3: 'sm:col-span-3', 4: 'sm:col-span-4' };

interface Props {
    sections: SectionInfo[];
    /** Remembers which titled sections are folded, under this key plus the title. */
    storageKey?: string;
    data: Record<string, FieldValue>;
    /** The record's saved values, on an edit form: fields with warnWhenChanged() warn once theirs differ. */
    saved?: Record<string, FieldValue>;
    errors: Partial<Record<string, string>>;
    onChange: (name: string, value: FieldValue) => void;
}

/** A form's sections as cards of fields (resources and settings). */
export function FormSections({ sections, data, saved, errors, storageKey, onChange }: Props) {
    return sections.map((section, index) => {
        // Fields whose condition isn't met are hidden (showWhen), a section of only those too.
        const fields = section.fields.filter((field) => isShownBy(field.showWhen as ShowWhen | null | undefined, data, saved));
        if (fields.length === 0) return null;
        const renderField = (field: FieldInfo) => {
            const error = errors[field.name];
            // A section's only field named like the section: the card's title says it already.
            const label = section.fields.length === 1 && field.label === section.title ? null : field.label;
            const warning = changeWarning(field.warnWhenChanged, data[field.name], saved, field.name);
            const onSaved = savedWarning(field.warning, data[field.name], saved, field.name);
            const input = <FieldInput field={{ ...field, placeholder: livePlaceholder(field.placeholder, field.placeholderFrom as HelpValue | null | undefined, data) }} value={data[field.name] ?? null} invalid={!!error} onChange={(value) => onChange(field.name, value)} />;

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
                    {field.type === 'password' && isPasswordRules(field.passwordRules) && (
                        <PasswordChecklist id={`field-${field.name}-rules`} rules={field.passwordRules}
                            value={typeof data[field.name] === 'string' ? String(data[field.name]) : ''} />
                    )}
                    <FieldHelp field={field} data={data} />
                    <div aria-live="polite" className="grid gap-2">
                        {onSaved && (
                            <div id={`field-${field.name}-saved-warning`} className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-200">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                                <div className="grid min-w-0 gap-1">
                                    <span className="whitespace-pre-line">{onSaved.text}</span>
                                    {onSaved.links.length > 0 && <FormLinks links={onSaved.links} />}
                                </div>
                            </div>
                        )}
                        {warning && (
                            <p id={`field-${field.name}-warning`} className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm whitespace-pre-line text-amber-900 dark:text-amber-200">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                                <span>{warning}</span>
                            </p>
                        )}
                    </div>
                    <InputError message={error} />
                    {field.type === 'password' && field.confirmed === true && (
                        // Password::confirmed(): the same again, as "{name}_confirmation".
                        <PasswordConfirmation field={field} value={data[`${field.name}_confirmation`] ?? null}
                            onChange={(value) => onChange(`${field.name}_confirmation`, value)} />
                    )}
                </div>
            );
        };
        const grid = (shown: FieldInfo[]) => <div className={cn('grid gap-5', gridColumns[section.columns])}>{shown.map(renderField)}</div>;
        const groups = fieldGroups(fields);
        // Field::group(): each group under its heading, set apart by a divider; without any, the plain grid.
        const content = groups.every((group) => group.title === null) ? grid(fields) : (
            <div className="grid gap-5">
                {groups.map((group, at) => group.title === null ? <React.Fragment key={at}>{grid(group.fields)}</React.Fragment> : (
                    <div key={at} role="group" aria-labelledby={`group-${index}-${at}`} className="grid min-w-0 gap-4 border-t pt-5 first:border-t-0 first:pt-0">
                        <h3 id={`group-${index}-${at}`} className="text-sm font-semibold">{group.title}</h3>
                        {grid(group.fields)}
                    </div>
                ))}
            </div>
        );

        // A titled section folds away (remembered), and opens on an error inside.
        return section.title ? (
            <CollapsibleCard key={index} title={section.title} description={section.description ?? undefined} titleClassName="text-base"
                storageKey={storageKey ? `${storageKey}:${section.title}` : undefined} hasErrors={fields.some((field) => errors[field.name] !== undefined)}
                headerAction={section.links ? <SectionLinks links={section.links} data={data} /> : undefined}>
                {content}
            </CollapsibleCard>
        ) : (
            <Card key={index}><CardContent>{content}</CardContent></Card>
        );
    });
}

function PasswordConfirmation({ field, value, onChange }: { field: FieldInfo; value: FieldValue; onChange: (value: FieldValue) => void }) {
    const name = `${field.name}_confirmation`;

    return (
        <div className="mt-3 grid min-w-0 grid-cols-1 content-start gap-2">
            <Label id={`field-${name}-label`} htmlFor={`field-${name}`}>
                {t('Confirm :field', { field: field.label.toLowerCase() })}
                {field.required && <span className="text-destructive" aria-hidden>*</span>}
            </Label>
            <FieldInput field={{ ...field, name, confirmed: false, placeholder: null, passwordRules: null }} value={value} invalid={false} onChange={onChange} />
        </div>
    );
}
