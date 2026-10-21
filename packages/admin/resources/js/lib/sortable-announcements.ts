type Id = string | number;
interface Moved { active: { id: Id }; over?: { id: Id } | null }

/** What a screen reader hears while a SortableList item is moved (dnd-kit's `Announcements`). */
export interface SortableAnnouncements {
    onDragStart(event: { active: { id: Id } }): string;
    onDragOver(event: Moved): string | undefined;
    onDragEnd(event: Moved): string;
    onDragCancel(event: { active: { id: Id } }): string;
}

/**
 * Announcements that name items by their label and say where they go
 * ("Home moved above About") instead of dnd-kit's defaults, which read the
 * internal ids ("Draggable item 47 was dropped over droppable area 36").
 * An item without a label is named by its position ("item 3").
 */
export function sortableAnnouncements(ids: readonly Id[], label?: (id: Id, index: number) => string | null | undefined): SortableAnnouncements {
    const count = ids.length;
    const name = (id: Id): string => {
        const index = ids.indexOf(id);
        const text = index >= 0 ? label?.(id, index)?.trim() : '';

        return text || (index >= 0 ? `item ${index + 1}` : 'item');
    };
    const position = (index: number) => `position ${index + 1} of ${count}`;
    /** Where `active` ends up when dropped on `over`: above it when moving up, below it when moving down. */
    const place = (active: Id, over: Id) => {
        const from = ids.indexOf(active);
        const to = ids.indexOf(over);

        return { to, where: to < from ? 'above' : 'below' };
    };

    return {
        onDragStart: ({ active }) => `Picked up ${name(active.id)}, ${position(Math.max(0, ids.indexOf(active.id)))}.`,
        onDragOver: ({ active, over }) => {
            if (!over) return `${name(active.id)} is no longer over the list.`;
            if (over.id === active.id) return `${name(active.id)} is back in its place.`;
            const { to, where } = place(active.id, over.id);

            return `${name(active.id)} moved ${where} ${name(over.id)}, ${position(to)}.`;
        },
        onDragEnd: ({ active, over }) => {
            if (!over || over.id === active.id) return `${name(active.id)} dropped; its place didn't change.`;
            const { to, where } = place(active.id, over.id);

            return `${name(active.id)} dropped ${where} ${name(over.id)}, ${position(to)}.`;
        },
        onDragCancel: ({ active }) => `Moving ${name(active.id)} was cancelled; it's back in its place.`,
    };
}
