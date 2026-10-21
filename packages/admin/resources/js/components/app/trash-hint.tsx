import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';

/**
 * An empty list's note that there's more in the trash: "1 is in the trash."
 * with a button to it, so an empty "All" doesn't look like nothing is there.
 */
export function TrashHint({ count, onShow }: { count: number; onShow: () => void }) {
    if (count <= 0) return null;

    return (
        <span className="inline-flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
            {count === 1 ? t('1 is in the trash.') : t(':count are in the trash.', { count })}
            <Button type="button" variant="link" size="sm" className="h-auto p-0" onClick={onShow}><Trash2 />{t('Show the trash')}</Button>
        </span>
    );
}
