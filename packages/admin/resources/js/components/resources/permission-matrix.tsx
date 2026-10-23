import * as React from 'react';
import { ChevronRight } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { hasColumns, hasOwn, type MatrixAction, matrixColumns, type MatrixOption, matrixRowName, type MatrixRow, matrixRows, selectionState } from '@/lib/permission-matrix';
import { cn } from '@/lib/utils';
import { groupOptions } from './relation-select';

interface Option {
    value: string;
    label: string;
    group: string | null;
    short?: string;
}

const PARTIAL = 'data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary/35 data-[state=indeterminate]:text-primary-foreground';

function actionLabel(action: MatrixAction): string {
    switch (action) {
        case 'view': return t('View');
        case 'create': return t('Create');
        case 'update': return t('Edit');
        case 'publish': return t('Publish');
        case 'moderate': return t('Moderate');
        case 'delete': return t('Delete');
        case 'export': return t('Export');
    }
}

/** An empty cell: a faint dot, "Not applicable" to screen readers. */
function NotApplicable() {
    return (
        <>
            <span aria-hidden className="mx-auto block size-1 rounded-full bg-muted-foreground/25" />
            <span className="sr-only">{t('Not applicable')}</span>
        </>
    );
}

/**
 * CheckboxList::matrix(): each group of permissions as a compact table, a row per thing and a column per
 * standard action. "-own" variants are a line under their row ("Own entries only"), boxes in their action's
 * column; other actions are a line of short choices under it ("Also: Translate · See drafts on the site"), or
 * the row itself when it has nothing else (General). Select all per group and per row; groups fold away and
 * say how many are granted. On narrow screens, a stacked list.
 */
export function PermissionMatrix({ id, options, value, disabled, onChange }: {
    id: string; options: Option[]; value: string[]; disabled: boolean; onChange: (value: string[]) => void;
}) {
    const groups = groupOptions(options);
    // Groups with something granted start open.
    const [open, setOpen] = React.useState<Record<string, boolean>>(() => Object.fromEntries(groups.map(([group, items]) => [group, items.some((item) => value.includes(item.value))])));
    const set = (values: string[], on: boolean) => onChange(on ? [...new Set([...value, ...values])] : value.filter((v) => !values.includes(v)));
    const allOpen = groups.every(([group]) => open[group]);

    return (
        <div id={id} role="group" className="grid gap-3">
            <div className="flex justify-end">
                <button type="button" className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    onClick={() => setOpen(Object.fromEntries(groups.map(([group]) => [group, !allOpen])))}>
                    {allOpen ? t('Collapse all') : t('Expand all')}
                </button>
            </div>
            {groups.map(([group, items]) => {
                const values = items.map((item) => item.value);
                const granted = values.filter((v) => value.includes(v)).length;
                const rows = matrixRows(items);
                const columns = matrixColumns(rows);
                const groupId = `${id}-${group.replace(/\W+/g, '-')}`;
                const isOpen = open[group] === true;
                // A row's name is a word of the permission's name ("Activity", "Guide"): said in the panel's language.
                const name = (row: MatrixRow) => rows.length === 1 && group !== '' ? group : t(matrixRowName(row.key, 'General'));
                // Nothing by action (settings, AI access): a plain list reads better than a table.
                const plain = columns.length === 0;
                const box = (option: MatrixOption, extra = '') => (
                    <Checkbox id={`${groupId}-${option.value}`} aria-label={option.label} title={option.label} disabled={disabled} className={extra}
                        checked={value.includes(option.value)} onCheckedChange={(checked) => set([option.value], checked === true)} />
                );
                // Other actions as small choices: the short label shows, the full one describes it (and is its tooltip).
                const chips = (options: MatrixOption[]) => (
                    <span className="flex flex-wrap items-center gap-1.5">
                        {options.map((option) => {
                            const boxId = `${groupId}-${option.value}`;
                            const short = option.short !== undefined && option.short !== '' && option.short !== option.label ? option.short : null;
                            return (
                                <span key={option.value} title={option.label}
                                    className="inline-flex items-center gap-1.5 rounded-full border bg-background py-0.5 pr-2.5 pl-1.5 has-[[data-state=checked]]:border-primary/40 has-[[data-state=checked]]:bg-primary/5">
                                    <Checkbox id={boxId} disabled={disabled} aria-describedby={short ? `${boxId}-description` : undefined} className="size-3.5"
                                        checked={value.includes(option.value)} onCheckedChange={(checked) => set([option.value], checked === true)} />
                                    <Label htmlFor={boxId} className="cursor-pointer text-xs leading-5 font-normal whitespace-nowrap">{short ?? option.label}</Label>
                                    {short && <span id={`${boxId}-description`} className="sr-only">{option.label}</span>}
                                </span>
                            );
                        })}
                    </span>
                );
                // A line under a row: its name column holds a small caption, indented past the row's select-all box.
                const caption = (text: string) => (
                    <th scope="row" className="py-1.5 pr-3 pl-9 text-left text-xs font-normal text-muted-foreground">{text}</th>
                );

                return (
                    <section key={group} className="rounded-lg border bg-card">
                        <div className="flex items-center gap-3 px-3 py-2">
                            <Checkbox aria-label={t('Select all in :group', { group: group || t('Other') })} disabled={disabled} className={PARTIAL}
                                checked={selectionState(values, value)} onCheckedChange={(checked) => set(values, checked === true)} />
                            <button type="button" aria-expanded={isOpen} aria-controls={`${groupId}-body`} onClick={() => setOpen({ ...open, [group]: !isOpen })}
                                className="flex flex-1 items-center gap-2 text-left text-sm font-medium">
                                <ChevronRight className={cn('size-4 text-muted-foreground transition-transform', isOpen && 'rotate-90')} aria-hidden />
                                <span>{group || t('Other')}</span>
                                <span className="ml-auto text-xs font-normal text-muted-foreground tabular-nums">{t(':granted of :total', { granted, total: values.length })}</span>
                            </button>
                        </div>
                        {isOpen && (
                            <div id={`${groupId}-body`} className="@container border-t">
                                {plain && (
                                    <div className="grid gap-x-6 gap-y-2.5 p-3 sm:grid-cols-2">
                                        {items.map((option) => (
                                            <span key={option.value} className="flex items-start gap-2">
                                                {box(option, 'mt-0.5')}
                                                <Label htmlFor={`${groupId}-${option.value}`} className="text-sm leading-snug font-normal">{option.label}</Label>
                                            </span>
                                        ))}
                                    </div>
                                )}
                                {/* Where seven actions fit (by the area's own width, beside the form's sections too): a table of things by
                                    action, the same widths in every group (the last column takes the rest). */}
                                {!plain && <table className="hidden w-full table-fixed text-sm @min-[39rem]:table">
                                    <colgroup>
                                        <col className="w-44 @min-[52rem]:w-52" />
                                        {columns.map((action) => <col key={action} className="w-16 @min-[52rem]:w-20" />)}
                                        <col />
                                    </colgroup>
                                    <thead className="sticky top-16 z-[1] bg-card">
                                        <tr className="border-b text-xs text-muted-foreground">
                                            <th scope="col" className="px-3 py-2 text-left font-medium"><span className="sr-only">{t('Permissions')}</span></th>
                                            {columns.map((action) => <th key={action} scope="col" className="px-1 py-2 text-center font-medium">{actionLabel(action)}</th>)}
                                            <td />
                                        </tr>
                                    </thead>
                                    {/* A body per thing, so its lines hover and divide together. */}
                                    {rows.map((row) => (
                                        <tbody key={row.key} className="border-b last:border-0 hover:bg-muted/40">
                                            <tr className="h-10">
                                                <th scope="row" className="px-3 py-1.5 text-left align-middle font-normal">
                                                    <span className="flex items-center gap-2">
                                                        <Checkbox aria-label={t('Select all for :name', { name: name(row) })} disabled={disabled} className={PARTIAL}
                                                            checked={selectionState(row.values, value)} onCheckedChange={(checked) => set(row.values, checked === true)} />
                                                        <span className="truncate font-medium" title={name(row)}>{name(row)}</span>
                                                    </span>
                                                </th>
                                                {hasColumns(row) ? (
                                                    <>
                                                        {columns.map((action) => {
                                                            const cell = row.cells[action];
                                                            return (
                                                                <td key={action} className="px-1 py-1.5 text-center align-middle">
                                                                    {cell.option ? <span className="inline-flex align-middle">{box(cell.option)}</span> : <NotApplicable />}
                                                                </td>
                                                            );
                                                        })}
                                                        <td />
                                                    </>
                                                ) : (
                                                    <td colSpan={columns.length + 1} className="px-2 py-1.5 align-middle">{chips(row.other)}</td>
                                                )}
                                            </tr>
                                            {hasOwn(row) && (
                                                <tr className="h-8">
                                                    {caption(t('Own :name only', { name: name(row).toLocaleLowerCase() }))}
                                                    {columns.map((action) => {
                                                        const own = row.cells[action].own;
                                                        return (
                                                            <td key={action} className="px-1 py-1.5 text-center align-middle">
                                                                {own && <span className="inline-flex align-middle">{box(own)}</span>}
                                                            </td>
                                                        );
                                                    })}
                                                    <td />
                                                </tr>
                                            )}
                                            {hasColumns(row) && row.other.length > 0 && (
                                                <tr className="h-8">
                                                    {caption(t('Also'))}
                                                    <td colSpan={columns.length + 1} className="px-2 py-1.5 align-middle">{chips(row.other)}</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    ))}
                                </table>}
                                {/* Narrower: each thing with its permissions listed. */}
                                {!plain && <div className="grid gap-4 p-3 @min-[39rem]:hidden">
                                    {rows.map((row) => (
                                        <div key={row.key} className="grid gap-2">
                                            <span className="text-sm font-medium">{name(row)}</span>
                                            {items.filter((item) => row.values.includes(item.value)).map((option) => (
                                                <span key={option.value} className="flex items-start gap-2">
                                                    <Checkbox id={`${groupId}-m-${option.value}`} disabled={disabled} className="mt-0.5"
                                                        checked={value.includes(option.value)} onCheckedChange={(checked) => set([option.value], checked === true)} />
                                                    <Label htmlFor={`${groupId}-m-${option.value}`} className="text-sm leading-snug font-normal">{option.label}</Label>
                                                </span>
                                            ))}
                                        </div>
                                    ))}
                                </div>}
                            </div>
                        )}
                    </section>
                );
            })}
        </div>
    );
}
