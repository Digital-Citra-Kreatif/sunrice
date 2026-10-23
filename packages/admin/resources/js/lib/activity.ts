/**
 * Whether an activity line's Who shows its source as a badge: not for the
 * panel's own (admin), and not for a system line without a person, whose
 * name already says "System".
 */
export function showsSourceBadge(row: { source: string; userId: string | null; user: string | null }): boolean {
    if (row.source === 'admin') return false;

    return !(row.source === 'system' && row.userId === null && row.user === null);
}
