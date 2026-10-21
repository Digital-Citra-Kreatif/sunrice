import { Check, Minus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { CellValue, ColumnInfo } from './types';
import { t } from '@/lib/i18n';

/** A list cell, as the column displays it. */
export function Cell({ column, value }: { column: ColumnInfo; value: CellValue }) {
    if (column.display === 'boolean') {
        return value ? <Check className="size-4 text-foreground" aria-label={t('Yes')} /> : <Minus className="size-4 text-muted-foreground" aria-label={t('No')} />;
    }
    if (value === null || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }
    if (column.display === 'badge') {
        return <Badge variant="secondary">{String(value)}</Badge>;
    }

    return <>{String(value)}</>;
}
