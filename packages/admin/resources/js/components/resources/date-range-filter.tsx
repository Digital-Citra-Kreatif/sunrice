import { DatePicker } from '@/components/app/date-picker';
import { formatDateRange, parseDateRange } from '@/lib/date-range';
import type { FilterInfo } from './types';

/** A list filter between two dates, either end open (Filter::dateRange). */
export function DateRangeFilter({ filter, value, onChange }: { filter: FilterInfo; value: string | undefined; onChange: (value: string) => void }) {
    const { from, to } = parseDateRange(value);

    return (
        <div role="group" aria-label={filter.label} className="flex items-center gap-1">
            <DatePicker className="w-40" placeholder={`${filter.label} from`} value={from} onChange={(next) => onChange(formatDateRange(next, to))} />
            <span className="text-sm text-muted-foreground" aria-hidden>–</span>
            <DatePicker className="w-40" placeholder="to" value={to} onChange={(next) => onChange(formatDateRange(from, next))} />
        </div>
    );
}
