/*
 * The DatePicker's value is the string a native date input would give:
 * "2026-10-19", or "2026-10-19T14:30" with a time. These read and write it
 * without time zones getting in the way (the date is local, as typed).
 */
import { format } from 'date-fns';

const PATTERN = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/;

export interface DateParts {
    /** Midnight, local time, or null when the value isn't a date. */
    date: Date | null;
    /** "HH:mm", or "" when the value has no time. */
    time: string;
}

export function parseDateValue(value: string | null | undefined): DateParts {
    const match = typeof value === 'string' ? PATTERN.exec(value) : null;
    if (!match) return { date: null, time: '' };
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    if (Number.isNaN(date.getTime())) return { date: null, time: '' };

    return { date, time: match[4] !== undefined ? `${match[4]}:${match[5]}` : '' };
}

/** The value to store: null without a date; the time only with `withTime` (midnight when none was chosen). */
export function formatDateValue(date: Date | null, time: string, withTime: boolean): string | null {
    if (!date) return null;
    const day = format(date, 'yyyy-MM-dd');

    return withTime ? `${day}T${/^\d{2}:\d{2}$/.test(time) ? time : '00:00'}` : day;
}

/** What the picker's button says: "19 Oct 2026" or "19 Oct 2026, 14:30"; "" for no value. */
export function dateLabel(value: string | null | undefined, withTime: boolean): string {
    const { date, time } = parseDateValue(value);
    if (!date) return '';
    const day = format(date, 'd MMM yyyy');

    return withTime ? `${day}, ${time || '00:00'}` : day;
}
