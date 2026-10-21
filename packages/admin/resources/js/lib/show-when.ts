/** A field's condition (Field::showWhen()): another field filled in, or holding a value. */
export interface ShowWhen {
    field: string;
    value: string | number | boolean | null;
    /** Field::showWhenChanged(): shown once that field differs from its saved value (edit forms only). */
    changed?: boolean;
}

/** A value to compare with the saved one: lists in any order. */
const comparable = (value: unknown): string => JSON.stringify(Array.isArray(value) ? value.map(String).sort() : (value ?? null));

const filled = (value: unknown): boolean =>
    value !== null && value !== undefined && value !== false && value !== '' && !(Array.isArray(value) && value.length === 0);

/** Whether a field with this condition shows, given the form's values. */
export function isShownBy(condition: ShowWhen | null | undefined, data: Record<string, unknown>, saved?: Record<string, unknown>): boolean {
    if (!condition) return true;
    const other = data[condition.field];
    if (condition.changed) return saved !== undefined && comparable(other) !== comparable(saved[condition.field]);
    if (condition.value === null) return filled(other);

    return Array.isArray(other) ? other.map(String).includes(String(condition.value)) : String(other ?? '') === String(condition.value);
}
