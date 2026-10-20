/**
 * A date range filter's value (Filter::dateRange): "from,to", dates as
 * 2026-01-31, either end empty; "" when both are.
 */
export function parseDateRange(value: string | undefined): { from: string | null; to: string | null } {
    const match = /^(\d{4}-\d{2}-\d{2})?,(\d{4}-\d{2}-\d{2})?$/.exec(value ?? '');

    return { from: match?.[1] ?? null, to: match?.[2] ?? null };
}

export function formatDateRange(from: string | null, to: string | null): string {
    const day = (value: string | null) => (value ?? '').slice(0, 10);

    return day(from) === '' && day(to) === '' ? '' : `${day(from)},${day(to)}`;
}
