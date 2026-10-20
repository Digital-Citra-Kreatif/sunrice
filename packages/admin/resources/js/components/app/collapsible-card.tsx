import * as React from 'react';
import { ChevronDown } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface CollapsibleCardProps {
    title: React.ReactNode;
    description?: React.ReactNode;
    /** Remembers open or closed in this browser under this key. */
    storageKey?: string;
    defaultOpen?: boolean;
    /** Opens the card, so a validation error inside is never hidden. */
    hasErrors?: boolean;
    /** Shown in the header, outside the toggle (a button, a badge). */
    headerAction?: React.ReactNode;
    titleClassName?: string;
    contentClassName?: string;
    className?: string;
    children: React.ReactNode;
}

const storage = (key: string) => `sunrice:card:${key}`;

const read = (key?: string): boolean | null => {
    if (!key) return null;
    try {
        const value = window.localStorage.getItem(storage(key));
        return value === null ? null : value === '1';
    } catch {
        return null;
    }
};

/**
 * A card whose header folds its content away (animated by
 * `.sunrice-card-content`); the choice is remembered per `storageKey`.
 * Folded content stays mounted (its fields are still submitted) but is
 * inert and hidden from assistive technology.
 */
export function CollapsibleCard({
    title, description, storageKey, defaultOpen = true, hasErrors = false, headerAction, titleClassName, contentClassName, className, children,
}: CollapsibleCardProps) {
    const [open, setOpen] = React.useState(() => hasErrors || (read(storageKey) ?? defaultOpen));
    const contentId = React.useId();

    const change = React.useCallback((next: boolean) => {
        setOpen(next);
        if (!storageKey) return;
        try {
            window.localStorage.setItem(storage(storageKey), next ? '1' : '0');
        } catch {
            // Storage blocked: the card still works, it just won't remember.
        }
    }, [storageKey]);

    React.useEffect(() => {
        if (hasErrors) change(true);
    }, [hasErrors, change]);

    return (
        <Card
            className={cn('gap-0 overflow-hidden py-0', className)}
            onInvalidCapture={(event) => {
                // The browser can't show a validation bubble on a folded field: open first.
                if (open) return;
                event.preventDefault();
                change(true);
                const control = event.target as HTMLElement;
                requestAnimationFrame(() => control.focus());
            }}
        >
            <CardHeader className={cn('flex flex-row items-center gap-0 px-0 [.border-b]:pb-0', open && 'border-b border-border/70')}>
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={contentId}
                    onClick={() => change(!open)}
                    className="group/trigger flex min-w-0 flex-1 cursor-pointer items-start justify-between gap-3 px-6 py-4 text-left transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset focus-visible:outline-none"
                >
                    <div className="grid gap-1">
                        <CardTitle className={titleClassName}>{title}</CardTitle>
                        {description && open && <CardDescription>{description}</CardDescription>}
                    </div>
                    <span className="mt-[-2px] grid size-6 shrink-0 place-items-center rounded-md text-muted-foreground transition-colors group-hover/trigger:bg-muted group-hover/trigger:text-foreground">
                        <ChevronDown aria-hidden="true" className={cn('size-4 transition-transform duration-250 ease-[cubic-bezier(0.22,1,0.36,1)]', !open && '-rotate-90')} />
                    </span>
                </button>
                {headerAction && <div className="shrink-0 pr-6">{headerAction}</div>}
            </CardHeader>
            <div id={contentId} data-state={open ? 'open' : 'closed'} className="sunrice-card-content">
                <div className="min-h-0 overflow-hidden" inert={!open} aria-hidden={!open}>
                    <CardContent className={cn('py-5', contentClassName)}>{children}</CardContent>
                </div>
            </div>
        </Card>
    );
}
