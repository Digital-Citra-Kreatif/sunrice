import * as React from 'react';
import { ChevronDown, ChevronsUpDown } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface CollapsibleCardProps {
    title: React.ReactNode;
    description?: React.ReactNode;
    /** What's inside, in one line, shown in place of the description while folded ("Jl. Senopati 26 · Jakarta Selatan"). */
    summary?: React.ReactNode;
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

interface CardGroupState {
    /** The last "expand all" or "collapse all", numbered so the same one twice still runs. */
    command: { open: boolean; seq: number } | null;
    report: (id: string, open: boolean | null) => void;
}

const CardGroup = React.createContext<CardGroupState | null>(null);

/**
 * Cards that fold together: with two or more CollapsibleCards inside, a
 * "Collapse all" (or "Expand all", when every card is folded) button sits
 * above them, at the right. Each card still remembers its own choice.
 */
export function CollapsibleCardGroup({ children, className }: { children: React.ReactNode; className?: string }) {
    const [states, setStates] = React.useState<Record<string, boolean>>({});
    const [command, setCommand] = React.useState<CardGroupState['command']>(null);
    const report = React.useCallback((id: string, open: boolean | null) => setStates((current) => {
        if (open === null) {
            const { [id]: _gone, ...rest } = current;
            return rest;
        }
        return current[id] === open ? current : { ...current, [id]: open };
    }), []);
    const value = React.useMemo(() => ({ command, report }), [command, report]);
    const cards = Object.values(states);
    const anyOpen = cards.some(Boolean);

    return (
        <CardGroup.Provider value={value}>
            <div className={cn('flex min-w-0 flex-col gap-3', className)}>
                {cards.length >= 2 && (
                    <div className="-my-3 flex justify-end">
                        <button type="button" onClick={() => setCommand((last) => ({ open: !anyOpen, seq: (last?.seq ?? 0) + 1 }))}
                            className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                            <ChevronsUpDown className="size-3.5" aria-hidden="true" />
                            {anyOpen ? 'Collapse all' : 'Expand all'}
                        </button>
                    </div>
                )}
                {children}
            </div>
        </CardGroup.Provider>
    );
}

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
    title, description, summary, storageKey, defaultOpen = true, hasErrors = false, headerAction, titleClassName, contentClassName, className, children,
}: CollapsibleCardProps) {
    const [open, setOpen] = React.useState(() => hasErrors || (read(storageKey) ?? defaultOpen));
    const contentId = React.useId();
    const group = React.useContext(CardGroup);

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

    // In a CollapsibleCardGroup: tell it whether this card is open, and follow "expand all" / "collapse all".
    const report = group?.report;
    React.useEffect(() => {
        report?.(contentId, open);
    }, [report, contentId, open]);
    React.useEffect(() => () => report?.(contentId, null), [report, contentId]);
    const seq = group?.command?.seq;
    React.useEffect(() => {
        if (seq !== undefined && group?.command) change(group.command.open || hasErrors);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only a new command moves the card
    }, [seq]);

    return (
        <Card
            // Clipped, not a scroll box (overflow: clip), so what sticks in it (a permissions table's header) sticks to the page.
            className={cn('gap-0 overflow-clip py-0', className)}
            onInvalidCapture={(event) => {
                // The browser can't show a validation bubble on a folded field: open first.
                if (open) return;
                event.preventDefault();
                change(true);
                const control = event.target as HTMLElement;
                requestAnimationFrame(() => control.focus());
            }}
        >
            {/* Title, then the header's action (a hint, links), then the fold chevron at the far edge. */}
            <CardHeader className={cn('group/header flex flex-row items-center gap-0 px-0 transition-colors hover:bg-muted/40 [.border-b]:pb-0', open && 'border-b border-border/70')}>
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={contentId}
                    onClick={() => change(!open)}
                    className="flex min-w-0 flex-1 cursor-pointer items-start gap-3 py-4 pl-6 pr-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset focus-visible:outline-none"
                >
                    <div className="grid gap-1">
                        <CardTitle className={titleClassName}>{title}</CardTitle>
                        {description && open && <CardDescription>{description}</CardDescription>}
                        {summary && !open && <CardDescription className="truncate">{summary}</CardDescription>}
                    </div>
                </button>
                {headerAction && <div className="min-w-0 shrink-0">{headerAction}</div>}
                {/* Folds too, for the pointer; the button above is the control for keyboards and screen readers. */}
                <span aria-hidden="true" onClick={() => change(!open)}
                    className="mr-6 ml-3 grid size-6 shrink-0 cursor-pointer place-items-center self-start mt-[calc(1rem-2px)] rounded-md text-muted-foreground transition-colors group-hover/header:bg-muted group-hover/header:text-foreground">
                    <ChevronDown className={cn('size-4 transition-transform duration-250 ease-[cubic-bezier(0.22,1,0.36,1)]', !open && '-rotate-90')} />
                </span>
            </CardHeader>
            <div id={contentId} data-state={open ? 'open' : 'closed'} className="sunrice-card-content">
                {/* min-w-0: a grid item is as wide as its content by default; the card's width wins. */}
                <div className="min-h-0 min-w-0 overflow-clip" inert={!open} aria-hidden={!open}>
                    <CardContent className={cn('py-5', contentClassName)}>{children}</CardContent>
                </div>
            </div>
        </Card>
    );
}
