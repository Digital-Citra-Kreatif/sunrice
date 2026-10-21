/** A field's condition (Field::showWhen()): another field filled in, or holding a value. */
export interface ShowWhen {
    field: string;
    value: string | number | boolean | null;
}

const filled = (value: unknown): boolean =>
    value !== null && value !== undefined && value !== false && value !== '' && !(Array.isArray(value) && value.length === 0);

/** Whether a field with this condition shows, given the form's values. */
export function isShownBy(condition: ShowWhen | null | undefined, data: Record<string, unknown>): boolean {
    if (!condition) return true;
    const other = data[condition.field];
    if (condition.value === null) return filled(other);

    return Array.isArray(other) ? other.map(String).includes(String(condition.value)) : String(other ?? '') === String(condition.value);
}
