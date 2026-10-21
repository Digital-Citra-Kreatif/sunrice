/** What a dragged file tile carries: the ids of the files to move. */
export const MEDIA_DRAG = 'application/x-sunrice-media';

/** The file ids from a drop; anything else counts as none. */
export function parseDraggedIds(data: string): number[] {
    try {
        const ids: unknown = JSON.parse(data);
        return Array.isArray(ids) ? ids.filter((id): id is number => typeof id === 'number') : [];
    } catch {
        return [];
    }
}
