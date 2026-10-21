import * as React from 'react';
import { ChevronRight } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { type MatrixAction, matrixColumns, matrixRowName, type MatrixRow, matrixRows, selectionState } from '@/lib/permission-matrix';
import { cn } from '@/lib/utils';
import { groupOptions } from './relation-select';

interface Option {
    value: string;
    label: string;
    group: string | null;
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

/**
 * CheckboxList::matrix(): each group of permissions as a compact table, a row per thing and a column per
 * standard action, with "Own only" variants in their cell and other actions listed in the row; select all per
 * group and per row; groups fold away and say how many are granted. On narrow screens, a stacked list.
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
                const hasOther = rows.some((row) => row.other.length > 0);
                const groupId = `${id}-${group.replace(/\W+/g, '-')}`;
                const isOpen = open[group] === true;
                const name = (row: MatrixRow) => rows.length === 1 && group !== '' ? group : matrixRowName(row.key, t('General'));
                // Nothing by action (settings, AI access): a plain list reads better than a table.
                const plain = columns.length === 0;
                const box = (option: { value: string; label: string }, extra = '') => (
                    <Checkbox id={`${groupId}-${option.value}`} aria-label={option.label} title={option.label} disabled={disabled} className={extra}
                        checked={value.includes(option.value)} onCheckedChange={(checked) => set([option.value], checked === true)} />
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
                            <div id={`${groupId}-body`} className="border-t">
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
                                {/* Wide screens: a table of things by action, the same widths in every group. */}
                                {!plain && <table className="hidden w-full table-fixed text-sm md:table">
                                    <colgroup>
                                        <col className="w-52" />
                                        {columns.map((action) => <col key={action} className="w-20" />)}
                                        {hasOther ? <col /> : <col className="w-auto" />}
                                    </colgroup>
                                    <thead className="sticky top-16 z-[1] bg-card/95 backdrop-blur">
                                        <tr className="border-b text-xs text-muted-foreground">
                                            <th scope="col" className="px-3 py-2 text-left font-medium"><span className="sr-only">{t('Permissions')}</span></th>
                                            {columns.map((action) => <th key={action} scope="col" className="w-20 px-2 py-2 text-center font-medium">{actionLabel(action)}</th>)}
                                            <th scope="col" className="px-3 py-2 text-left font-medium">{hasOther ? t('Other') : ''}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => (
                                            <tr key={row.key} className="border-b last:border-0 hover:bg-muted/40">
                                                <th scope="row" className="px-3 py-2 text-left font-normal">
                                                    <span className="flex items-center gap-2">
                                                        <Checkbox aria-label={t('Select all for :name', { name: name(row) })} disabled={disabled} className={PARTIAL}
                                                            checked={selectionState(row.values, value)} onCheckedChange={(checked) => set(row.values, checked === true)} />
                                                        <span className="font-medium">{name(row)}</span>
                                                    </span>
                                                </th>
                                                {columns.map((action) => {
                                                    const cell = row.cells[action];
                                                    return (
                                                        <td key={action} className="px-2 py-2 text-center align-middle">
                                                            {cell.option ? (
                                                                <span className="inline-flex flex-col items-center gap-1">
                                                                    {box(cell.option)}
                                                                    {cell.own && (
                                                                        <label className="inline-flex items-center gap-1 text-[11px] text-muted-foreground" title={cell.own.label}>
                                                                            {box(cell.own, 'size-3.5')}{t('Own only')}
                                                                        </label>
                                                                    )}
                                                                </span>
                                                            ) : <span className="text-muted-foreground" aria-label={t('Not applicable')}>—</span>}
                                                        </td>
                                                    );
                                                })}
                                                {!hasOther && <td />}
                                                {hasOther && (
                                                    <td className="px-3 py-2">
                                                        <span className="flex flex-col gap-1.5">
                                                            {row.other.map((option) => (
                                                                <span key={option.value} className="flex items-start gap-2">
                                                                    {box(option, 'mt-0.5')}
                                                                    <Label htmlFor={`${groupId}-${option.value}`} className="text-sm leading-snug font-normal">{option.label}</Label>
                                                                </span>
                                                            ))}
                                                        </span>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>}
                                {/* Narrow screens: each thing with its permissions listed. */}
                                {!plain && <div className="grid gap-4 p-3 md:hidden">
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
