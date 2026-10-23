import * as React from 'react';
import { ChevronDown } from 'lucide-react';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * A region that folds away (height and opacity, `.sunrice-collapse`, none
 * with reduced motion). Folded content stays mounted but is inert and
 * hidden from assistive technology.
 */
export function CollapseRegion({ id, open, className, children }: { id: string; open: boolean; className?: string; children: React.ReactNode }) {
    return (
        <div id={id} data-state={open ? 'open' : 'closed'} className="sunrice-collapse">
            <div className={cn('min-h-0 overflow-hidden', className)} inert={!open} aria-hidden={!open}>{children}</div>
        </div>
    );
}

/** The chevron button that folds a CollapseRegion: "Collapse" or "Expand", described by what it folds (`describedBy`). */
export function CollapseToggle({ open, controls, describedBy, onToggle, className }: {
    open: boolean;
    controls: string;
    describedBy?: string;
    onToggle: () => void;
    className?: string;
}) {
    const label = open ? t('Collapse') : t('Expand');

    return (
        <button
            type="button"
            aria-expanded={open}
            aria-controls={controls}
            aria-describedby={describedBy}
            aria-label={label}
            title={label}
            onClick={onToggle}
            className={cn('relative z-10 grid size-6 shrink-0 cursor-pointer place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-4 focus-visible:ring-ring-glow focus-visible:outline-none', className)}
        >
            <ChevronDown aria-hidden="true" className={cn('size-4 transition-transform duration-200 ease-out motion-reduce:transition-none', !open && '-rotate-90')} />
        </button>
    );
}
