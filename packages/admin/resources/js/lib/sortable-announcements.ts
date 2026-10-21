type Id = string | number;

/** Says a text in the user's language (the panel's t); the tests run without one. */
type Translate = (text: string, replace?: Record<string, string | number>) => string;
const english: Translate = (text, replace = {}) => text.replace(/:([A-Za-z_]+)/g, (match, key: string) => (key in replace ? String(replace[key]) : match));
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
export function sortableAnnouncements(ids: readonly Id[], label?: (id: Id, index: number) => string | null | undefined, t: Translate = english): SortableAnnouncements {
    const count = ids.length;
    const name = (id: Id): string => {
        const index = ids.indexOf(id);
        const text = index >= 0 ? label?.(id, index)?.trim() : '';

        return text || (index >= 0 ? t('item :number', { number: index + 1 }) : t('item'));
    };
    /** Where `active` ends up when dropped on `over`: above it when moving up, below it when moving down. */
    const place = (active: Id, over: Id) => {
        const from = ids.indexOf(active);
        const to = ids.indexOf(over);

        return { position: to + 1, above: to < from };
    };

    return {
        onDragStart: ({ active }) => t('Picked up :item, position :position of :count.', { item: name(active.id), position: Math.max(0, ids.indexOf(active.id)) + 1, count }),
        onDragOver: ({ active, over }) => {
            if (!over) return t(':item is no longer over the list.', { item: name(active.id) });
            if (over.id === active.id) return t(':item is back in its place.', { item: name(active.id) });
            const { position, above } = place(active.id, over.id);
            const replace = { item: name(active.id), other: name(over.id), position, count };

            return above ? t(':item moved above :other, position :position of :count.', replace) : t(':item moved below :other, position :position of :count.', replace);
        },
        onDragEnd: ({ active, over }) => {
            if (!over || over.id === active.id) return t(':item dropped; its place didn\'t change.', { item: name(active.id) });
            const { position, above } = place(active.id, over.id);
            const replace = { item: name(active.id), other: name(over.id), position, count };

            return above ? t(':item dropped above :other, position :position of :count.', replace) : t(':item dropped below :other, position :position of :count.', replace);
        },
        onDragCancel: ({ active }) => t('Moving :item was cancelled; it\'s back in its place.', { item: name(active.id) }),
    };
}
