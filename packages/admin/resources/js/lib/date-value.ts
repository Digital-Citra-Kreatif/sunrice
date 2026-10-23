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

/**
 * What the picker's button says, in the panel's language and as the CMS's
 * dates read: "19 Oct 2026, 14:30" (English), "19 Okt 2026, 14.30"
 * (Indonesian); "" for no value.
 */
export function dateLabel(value: string | null | undefined, withTime: boolean, locale?: string): string {
    const { date, time } = parseDateValue(value);
    if (!date) return '';
    if (!withTime) return date.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' });
    const [hours, minutes] = (time || '00:00').split(':').map(Number);
    date.setHours(hours ?? 0, minutes ?? 0);

    return date.toLocaleString(locale, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
}

/**
 * A time as typed, on the 24-hour clock: "14:30", "9:05", "0930", "21.15"
 * or "9" (on the hour), as "HH:mm"; null when it isn't one.
 */
export function parseTimeInput(text: string): string | null {
    const match = /^(\d{1,2})(?:[:.h]?(\d{2}))?$/.exec(text.trim());
    if (!match) return null;
    const hours = Number(match[1]);
    const minutes = match[2] === undefined ? 0 : Number(match[2]);
    if (hours > 23 || minutes > 59) return null;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}
