import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { REL_OPTIONS, type RelOption } from '@/lib/rich-link';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';

export interface LinkAttributesProps {
    /** Opens in a new tab. */
    newTab: boolean;
    onNewTabChange: (newTab: boolean) => void;
    /** The rel choices on (some of nofollow, sponsored, ugc). */
    rel: readonly string[];
    /** The choices after one was turned on or off, in their order. */
    onRelChange: (rel: RelOption[]) => void;
    /** The group's name for screen readers ("Call to action: link attributes"). */
    label?: string;
    disabled?: boolean;
    className?: string;
}

/** What each rel choice is for, as its tooltip says. */
export function relHelp(option: RelOption): string {
    return {
        nofollow: t('nofollow: search engines don\'t follow the link or count it as an endorsement.'),
        sponsored: t('sponsored: a paid link, an advert or a sponsorship.'),
        ugc: t('ugc: a link in what visitors wrote, such as comments.'),
    }[option];
}

/**
 * A link's "Open in a new tab" box, its nofollow, sponsored and ugc
 * toggles (each with a tooltip saying what it's for) and a note that new
 * tabs always open safely: the rich text editor's link menu and the CMS's
 * Link fields show the same.
 */
export function LinkAttributes({ newTab, onNewTabChange, rel, onRelChange, label, disabled = false, className }: LinkAttributesProps) {
    const id = React.useId();
    const toggle = (option: RelOption) => {
        const next = rel.includes(option) ? rel.filter((token) => token !== option) : [...rel, option];
        onRelChange(REL_OPTIONS.filter((token) => next.includes(token)));
    };

    return (
        <TooltipProvider>
            <div role="group" aria-label={label ?? t('Link attributes')} className={cn('flex flex-col gap-1.5', className)}>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <label htmlFor={`${id}-tab`} className="flex items-center gap-2 text-sm">
                        <Checkbox id={`${id}-tab`} checked={newTab} disabled={disabled} onCheckedChange={(checked) => onNewTabChange(checked === true)} />
                        {t('Open in a new tab')}
                    </label>
                    <div className="flex flex-wrap items-center gap-1">
                        {REL_OPTIONS.map((option) => (
                            <Tooltip key={option}>
                                <TooltipTrigger asChild>
                                    <Button type="button" size="sm" variant={rel.includes(option) ? 'secondary' : 'outline'} className="h-7 px-2 text-xs" disabled={disabled}
                                        aria-pressed={rel.includes(option)} aria-describedby={`${id}-${option}`} onClick={() => toggle(option)}>
                                        {option}
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent className="max-w-64">{relHelp(option)}</TooltipContent>
                            </Tooltip>
                        ))}
                    </div>
                </div>
                <p className="text-xs text-muted-foreground">{t('Links that open in a new tab always do so safely (noopener noreferrer).')}</p>
                {REL_OPTIONS.map((option) => <span key={option} id={`${id}-${option}`} className="sr-only">{relHelp(option)}</span>)}
            </div>
        </TooltipProvider>
    );
}
