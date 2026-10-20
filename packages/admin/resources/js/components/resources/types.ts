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
    /** A select of options, or two dates ("from,to"). */
    type: 'select' | 'date_range';
    options: { value: string; label: string }[];
}

export interface ActionInfo {
    key: string;
    label: string;
    confirm: string | null;
    destructive: boolean;
    /** It opens an address (the row's `urls`) instead of running. */
    url: boolean;
    newTab: boolean;
    /** Fields the dialog asks for (Action::form()), each with its `default`. */
    fields: FieldInfo[];
}

export interface Row {
    id: string | number;
    title: string;
    cells: Record<string, CellValue>;
    can: { update: boolean; delete: boolean };
    actions: string[];
    /** The addresses of the row's url actions, by key. */
    urls: Record<string, string>;
}

export interface FieldInfo {
    kind: 'field';
    type: 'text' | 'textarea' | 'number' | 'toggle' | 'select' | 'date' | 'password' | 'checkboxes' | 'belongs_to' | 'belongs_to_many' | 'richtext' | string;
    name: string;
    label: string;
    required: boolean;
    disabled: boolean;
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
