import type * as React from 'react';
import { Plus } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** A language as a LanguageTabs tab shows it. */
export interface LanguageTab {
    code: string;
    /** Its name ("Bahasa Indonesia"); the code when not given. */
    name?: string;
    /** The main language: said in its tooltip ("English (main)"). */
    main?: boolean;
    /**
     * Nothing in this language yet: shown muted with a plus. Choosing it calls
     * `onAdd` when given (to create it), else opens it like any other tab.
     */
    missing?: boolean;
    /**
     * A dot after the name and what it means, which the tooltip and screen
     * readers say: `success` (green: published, say) or `warning` (amber:
     * outdated, say).
     */
    status?: { tone: 'success' | 'warning'; label: string } | null;
    /** More about it for the tooltip and screen readers ("Draft"), without a dot. */
    description?: string | null;
}

/** What a language tab says in its tooltip and to screen readers: "English (main): Published", "Add Bahasa Indonesia". */
export function languageTabTitle(tab: LanguageTab, adding: boolean): string {
    const name = tab.name ?? tab.code;
    if (tab.missing) {
        return adding ? t('Add :language', { language: name }) : t(':language: not translated yet', { language: name });
    }
    const label = tab.main ? t(':language (main)', { language: name }) : name;
    const about = [tab.status?.label, tab.description].filter((part): part is string => typeof part === 'string' && part !== '');

    return about.length > 0 ? `${label}: ${about.join(', ')}` : label;
}

const DOT = { success: 'bg-emerald-500', warning: 'bg-amber-500' } as const;

/**
 * The panel's one way to choose which language's content is shown or
 * edited: a row of tabs, one per language by its code ("EN", its name and
 * whether it's the main one in the tooltip),
 * missing ones muted with a plus (choosing one adds it with `onAdd`), an
 * optional status dot each, and `actions` at the end of the row. With
 * fewer than two languages there are no tabs (only `actions`).
 *
 * Two uses:
 * - Switching pages (`?locale=id`): no children; `onValueChange` visits the
 *   other language. Tabs then activate on Enter/Space, not on arrow keys,
 *   so moving along the row doesn't load pages.
 * - Switching in place: `children(code)` gives each language's panel, all
 *   kept mounted (hidden ones stay in the form).
 *
 * Public API (`@imajiner/admin-sdk/ui`).
 */
export function LanguageTabs({ languages, value, onValueChange, onAdd, actions, children, label, className }: {
    languages: LanguageTab[];
    /** The language shown. */
    value: string;
    onValueChange: (code: string) => void;
    /** Adds a missing language; without it, missing ones open like the others. */
    onAdd?: (code: string) => void;
    /** After the tabs, at the end of the row ("Remove this language"). */
    actions?: React.ReactNode;
    /** Each language's panel, to switch in place. */
    children?: (code: string) => React.ReactNode;
    /** The tab list's name for screen readers; "Language" by default. */
    label?: string;
    className?: string;
}) {
    if (languages.length < 2) {
        return (
            <>
                {actions && <div className={cn('flex items-center justify-end gap-2', className)}>{actions}</div>}
                {children?.(value)}
            </>
        );
    }

    const choose = (code: string) => {
        if (code === value) return;
        const tab = languages.find((each) => each.code === code);
        if (tab?.missing && onAdd) {
            onAdd(code);
        } else {
            onValueChange(code);
        }
    };

    return (
        <Tabs value={value} onValueChange={choose} activationMode={children ? 'automatic' : 'manual'} className={cn('min-w-0 gap-4', className)}>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
                <TabsList aria-label={label ?? t('Language')} className="min-w-0 justify-start">
                    {languages.map((tab) => {
                        const title = languageTabTitle(tab, onAdd !== undefined);
                        return (
                            <TabsTrigger key={tab.code} value={tab.code} title={title} aria-label={title}
                                // Switching pages: there is no panel here to point at.
                                {...(children ? {} : { 'aria-controls': undefined })}
                                className={cn('flex-none px-3', tab.missing && 'border-dashed border-muted-foreground/40 text-muted-foreground')}>
                                {tab.missing && <Plus aria-hidden="true" />}
                                <span className="font-medium uppercase">{tab.code}</span>
                                {tab.status && !tab.missing && <span aria-hidden="true" className={cn('size-2 rounded-full', DOT[tab.status.tone])} />}
                            </TabsTrigger>
                        );
                    })}
                </TabsList>
                {actions && <div className="ml-auto flex items-center gap-2">{actions}</div>}
            </div>
            {children && languages.map((tab) => (
                <TabsContent key={tab.code} value={tab.code} forceMount className="min-w-0 data-[state=inactive]:hidden">
                    {children(tab.code)}
                </TabsContent>
            ))}
        </Tabs>
    );
}
