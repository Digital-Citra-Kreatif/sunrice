import type * as React from 'react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';

/** A list's title while it shows the trash: the name with a red Trash badge. */
export function TrashTitle({ children }: { children: React.ReactNode }) {
    return (
        <span className="inline-flex items-center gap-3">
            {children}
            <Badge variant="destructive" className="text-sm"><Trash2 /> {t('Trash')}</Badge>
        </span>
    );
}

/**
 * Above a list showing the trash: says the items are deleted and show nowhere
 * until restored, with a way back. `items` names them ("blog posts").
 */
export function TrashBanner({ items, onBack, backLabel }: { items: string; onBack: () => void; backLabel?: string }) {
    return (
        <div role="status" className="flex flex-wrap items-center gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm">
            <Trash2 className="size-5 shrink-0 text-destructive" aria-hidden="true" />
            <p className="min-w-48 flex-1">
                {t('You\'re looking at the trash. These :items are deleted: they show nowhere until they\'re restored. Permanently deleting one can\'t be undone.', { items })}
            </p>
            <Button variant="outline" size="sm" onClick={onBack}><ArrowLeft /> {backLabel ?? t('Back to the list')}</Button>
        </div>
    );
}
