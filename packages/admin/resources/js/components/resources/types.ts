/** Page data of the Resources pages (see ResourceController). */

export interface ResourceInfo {
    key: string;
    label: string;
    singularLabel: string;
}

export type CellValue = string | number | boolean | null;

export interface ColumnInfo {
    name: string;
    label: string;
    sortable: boolean;
    display: 'text' | 'badge' | 'boolean';
}

export interface FilterInfo {
    name: string;
    label: string;
    options: { value: string; label: string }[];
}

export interface ActionInfo {
    key: string;
    label: string;
    confirm: string | null;
    destructive: boolean;
}

export interface Row {
    id: string | number;
    title: string;
    cells: Record<string, CellValue>;
    can: { update: boolean; delete: boolean };
    actions: string[];
}

export interface FieldInfo {
    kind: 'field';
    type: 'text' | 'textarea' | 'number' | 'toggle' | 'select' | 'date' | string;
    name: string;
    label: string;
    required: boolean;
    help: string | null;
    placeholder: string | null;
    span: number;
    [prop: string]: unknown;
}

export interface SectionInfo {
    kind: 'section';
    title: string | null;
    description: string | null;
    columns: number;
    fields: FieldInfo[];
}
