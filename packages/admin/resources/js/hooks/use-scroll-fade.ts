import * as React from 'react';

/**
 * Marks a scrolling box whose content runs past an edge, for the
 * `sunrice-scroll-fade-x` / `-y` classes to fade that edge out: sets
 * `data-fade-start` and `data-fade-end` while there is more to scroll that
 * way. With `keepVisible` (a selector inside the box), that element is
 * scrolled into the box's view when shown, without moving the page.
 */
export function useScrollFade<T extends HTMLElement>(axis: 'x' | 'y', keepVisible?: string, key?: unknown): React.RefObject<T | null> {
    const ref = React.useRef<T>(null);

    React.useEffect(() => {
        const box = ref.current;
        if (!box) return;

        if (keepVisible) {
            const target = box.querySelector<HTMLElement>(keepVisible);
            if (target) {
                const boxRect = box.getBoundingClientRect();
                const rect = target.getBoundingClientRect();
                if (axis === 'y' && (rect.top < boxRect.top || rect.bottom > boxRect.bottom)) {
                    box.scrollTop += rect.top - boxRect.top - (box.clientHeight - rect.height) / 2;
                } else if (axis === 'x' && (rect.left < boxRect.left || rect.right > boxRect.right)) {
                    box.scrollLeft += rect.left - boxRect.left - (box.clientWidth - rect.width) / 2;
                }
            }
        }

        const update = () => {
            const position = axis === 'y' ? box.scrollTop : Math.abs(box.scrollLeft);
            const visible = axis === 'y' ? box.clientHeight : box.clientWidth;
            const total = axis === 'y' ? box.scrollHeight : box.scrollWidth;
            box.dataset.fadeStart = String(position > 1);
            box.dataset.fadeEnd = String(position + visible < total - 1);
        };
        update();
        box.addEventListener('scroll', update, { passive: true });
        const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(update);
        observer?.observe(box);

        return () => {
            box.removeEventListener('scroll', update);
            observer?.disconnect();
        };
    }, [axis, keepVisible, key]);

    return ref;
}
