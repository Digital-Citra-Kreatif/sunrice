/** The standard actions a matrix has columns for, in order. */
export const MATRIX_ACTIONS = ['view', 'create', 'update', 'publish', 'moderate', 'delete', 'export'] as const;
export type MatrixAction = (typeof MATRIX_ACTIONS)[number];

export interface MatrixOption {
    value: string;
    label: string;
}

export interface MatrixCell {
    /** The option for the action ("cms.entries.update"), if any. */
    option: MatrixOption | null;
    /** Its "-own" variant ("cms.entries.update-own"), if any. */
    own: MatrixOption | null;
}

export interface MatrixRow {
    /** The name without its action: "cms.entries", "cms.entries.blog". */
    key: string;
    cells: Record<MatrixAction, MatrixCell>;
    /** Options of other actions (translate, homepage…), listed in the row. */
    other: MatrixOption[];
    /** Every option of the row. */
    values: string[];
}

/**
 * A group's options as rows of things by action: "cms.entries.view" goes in
 * row "cms.entries", column "view"; "cms.entries.update-own" is the own
 * variant of its "update" cell; an action without a column goes in the row's
 * other options. Rows keep the order their first option comes in.
 */
export function matrixRows(options: MatrixOption[]): MatrixRow[] {
    const rows = new Map<string, MatrixRow>();
    const row = (key: string): MatrixRow => {
        let found = rows.get(key);
        if (!found) {
            const cells = Object.fromEntries(MATRIX_ACTIONS.map((action) => [action, { option: null, own: null }])) as unknown as Record<MatrixAction, MatrixCell>;
            found = { key, cells, other: [], values: [] };
            rows.set(key, found);
        }
        return found;
    };
    for (const option of options) {
        const dot = option.value.lastIndexOf('.');
        const key = dot > 0 ? option.value.slice(0, dot) : '';
        const action = dot > 0 ? option.value.slice(dot + 1) : option.value;
        const target = row(key);
        target.values.push(option.value);
        const base = action.endsWith('-own') ? action.slice(0, -4) : action;
        if ((MATRIX_ACTIONS as readonly string[]).includes(base)) {
            const cell = target.cells[base as MatrixAction];
            if (action.endsWith('-own')) cell.own = option;
            else cell.option = option;
        } else {
            target.other.push(option);
        }
    }
    // An own variant without its action stays reachable: it goes with the others.
    for (const each of rows.values()) {
        for (const action of MATRIX_ACTIONS) {
            const cell = each.cells[action];
            if (cell.own && !cell.option) {
                each.other.push(cell.own);
                cell.own = null;
            }
        }
    }

    return [...rows.values()];
}

/** The columns any of the rows uses, so a group shows only those. */
export function matrixColumns(rows: MatrixRow[]): MatrixAction[] {
    return MATRIX_ACTIONS.filter((action) => rows.some((row) => row.cells[action].option !== null));
}

/** A row's name from its key: the last part in words ("cms.entries" → "Entries", "cms.submissions.contact_form" → "Contact form"); `general` for a plugin's own ("cms"). */
export function matrixRowName(key: string, general = 'General'): string {
    // A name of two parts ("cms.homepage"): the plugin's own, general permissions.
    if (!key.includes('.')) return general;
    const last = key.split('.').pop() ?? key;
    const words = last.replace(/[_-]+/g, ' ').trim();

    return words === '' ? key : words.charAt(0).toUpperCase() + words.slice(1);
}

/** Checked, unchecked or some of `values`, for a select-all box. */
export function selectionState(values: string[], chosen: string[]): boolean | 'indeterminate' {
    const count = values.filter((value) => chosen.includes(value)).length;

    return count === 0 ? false : count === values.length ? true : 'indeterminate';
}
