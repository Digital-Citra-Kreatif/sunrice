/**
 * A copy of the list with the item at `from` moved to `to` (what a drag
 * from one position to another means). Out-of-range positions and a move
 * to the same place give the list back unchanged.
 */
export function moveItem<T>(items: readonly T[], from: number, to: number): T[] {
    if (from === to || from < 0 || to < 0 || from >= items.length || to >= items.length) {
        return [...items];
    }
    const next = [...items];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved as T);

    return next;
}
