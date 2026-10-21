import * as React from 'react';
import {
    DndContext, DragOverlay, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors,
    type DragEndEvent, type DragStartEvent, type UniqueIdentifier,
} from '@dnd-kit/core';
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
import { moveItem } from '@/lib/move-item';
import { sortableAnnouncements } from '@/lib/sortable-announcements';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';

export { moveItem };

/** Spread onto the element that starts a drag (SortableHandle does). */
export interface SortableHandleProps extends React.HTMLAttributes<HTMLElement> {
    ref?: (element: HTMLElement | null) => void;
    disabled?: boolean;
}

export interface SortableItemState {
    index: number;
    /** Props for the drag handle: pointer and keyboard sorting, ARIA. */
    handle: SortableHandleProps;
    /** This item is being dragged (the row it left behind shows faded). */
    isDragging: boolean;
    /** This render is the ghost that follows the pointer. */
    isOverlay: boolean;
}

export interface SortableListProps<T> {
    items: readonly T[];
    /** A stable id per item (an item's id, handle or key). */
    getId: (item: T) => string | number;
    /** The item's name for screen reader announcements ("Home moved above About"); without it, items are named by position. */
    getLabel?: (item: T) => string | null | undefined;
    /** Called with the reordered list once a drag or a keyboard move ends. */
    onReorder: (items: T[], from: number, to: number) => void;
    children: (item: T, state: SortableItemState) => React.ReactNode;
    disabled?: boolean;
    className?: string;
    itemClassName?: string;
}

/** The grip button that starts a drag; also sortable by keyboard (space, arrows, space). */
export function SortableHandle({ className, label, ...handle }: SortableHandleProps & { label?: string }) {
    return (
        <button
            type="button"
            aria-label={label ?? t('Reorder')}
            className={cn(
                'inline-grid size-7 shrink-0 cursor-grab place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing disabled:cursor-default disabled:opacity-40',
                className,
            )}
            {...(handle as React.ButtonHTMLAttributes<HTMLButtonElement>)}
        >
            <GripVertical className="size-4" />
        </button>
    );
}

function SortableRow<T>({ item, id, index, disabled, reduced, className, children }: {
    item: T;
    id: UniqueIdentifier;
    index: number;
    disabled: boolean;
    reduced: boolean;
    className?: string;
    children: SortableListProps<T>['children'];
}) {
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id, disabled });
    const handle: SortableHandleProps = { ...attributes, ...listeners, ref: setActivatorNodeRef, ...(disabled ? { disabled: true, tabIndex: -1 } : {}) };

    return (
        <li
            ref={setNodeRef}
            data-sortable-item={id}
            className={cn('list-none', isDragging && 'opacity-40', className)}
            style={{ transform: CSS.Translate.toString(transform), transition: reduced ? undefined : transition }}
        >
            {children(item, { index, handle, isDragging, isOverlay: false })}
        </li>
    );
}

/**
 * A vertical list whose items are reordered by dragging their handle or
 * with the keyboard (focus the handle, space, arrow keys, space). The
 * dragged item follows the pointer as a lifted ghost; with reduced motion
 * nothing travels.
 *
 *     <SortableList items={rows} getId={(row) => row.id} getLabel={(row) => row.title} onReorder={setRows}>
 *         {(row, { handle }) => <div className="flex gap-2"><SortableHandle {...handle} />{row.title}</div>}
 *     </SortableList>
 */
export function SortableList<T>({ items, getId, getLabel, onReorder, children, disabled = false, className, itemClassName }: SortableListProps<T>) {
    const [active, setActive] = React.useState<UniqueIdentifier | null>(null);
    const reduced = useReducedMotion();
    const ids = React.useMemo(() => items.map(getId), [items, getId]);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const announcements = React.useMemo(
        () => sortableAnnouncements(ids, getLabel ? (_id, index) => getLabel(items[index] as T) : undefined, t),
        [ids, items, getLabel],
    );

    const onDragStart = (event: DragStartEvent) => setActive(event.active.id);
    const onDragEnd = ({ active: dragged, over }: DragEndEvent) => {
        setActive(null);
        if (!over || dragged.id === over.id) return;
        const from = ids.indexOf(dragged.id);
        const to = ids.indexOf(over.id);
        if (from >= 0 && to >= 0) onReorder(moveItem(items, from, to), from, to);
    };

    const activeIndex = active === null ? -1 : ids.indexOf(active);
    const activeItem = activeIndex >= 0 ? items[activeIndex] : undefined;
    const ghostHandle: SortableHandleProps = { 'aria-hidden': true, tabIndex: -1 };

    return (
        <DndContext sensors={sensors} collisionDetection={closestCenter} accessibility={{ announcements }} onDragStart={onDragStart} onDragEnd={onDragEnd} onDragCancel={() => setActive(null)}>
            <SortableContext items={ids} strategy={verticalListSortingStrategy}>
                <ul className={cn('m-0 grid gap-2 p-0', className)}>
                    {items.map((item, index) => (
                        <SortableRow key={ids[index]} item={item} id={ids[index] as UniqueIdentifier} index={index} disabled={disabled} reduced={reduced} className={itemClassName}>
                            {children}
                        </SortableRow>
                    ))}
                </ul>
            </SortableContext>
            <DragOverlay dropAnimation={reduced ? null : undefined} zIndex={60}>
                {activeItem !== undefined && (
                    <div className={cn(
                        'cursor-grabbing rounded-lg bg-card shadow-[var(--shadow-pop,0_6px_16px_-6px_rgb(0_0_0/0.18),0_24px_50px_-12px_rgb(0_0_0/0.28))]',
                        !reduced && 'scale-[1.02] rotate-[0.4deg]',
                        itemClassName,
                    )}>
                        {children(activeItem, { index: activeIndex, handle: ghostHandle, isDragging: true, isOverlay: true })}
                    </div>
                )}
            </DragOverlay>
        </DndContext>
    );
}
