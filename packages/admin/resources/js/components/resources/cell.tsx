import { Check, Minus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { CellValue, ColumnInfo } from './types';

/** A list cell, as the column displays it. */
export function Cell({ column, value }: { column: ColumnInfo; value: CellValue }) {
    if (column.display === 'boolean') {
        return value ? <Check className="size-4 text-foreground" aria-label="Yes" /> : <Minus className="size-4 text-muted-foreground" aria-label="No" />;
    }
    if (value === null || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }
    if (column.display === 'badge') {
        return <Badge variant="secondary">{String(value)}</Badge>;
    }

    return <>{String(value)}</>;
}
