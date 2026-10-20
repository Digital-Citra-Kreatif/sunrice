/*
 * The pure parts of the panel's calendar (components/ui/calendar.tsx): the
 * weeks a month shows, the weekday header, and where the keys move focus.
 * On date-fns, so the first day of the week follows the locale.
 */
import {
    addDays, addMonths, addYears, eachDayOfInterval, endOfMonth, endOfWeek, isSameMonth, startOfDay, startOfMonth, startOfWeek, type Day, type Locale,
} from 'date-fns';
import { enUS } from 'date-fns/locale/en-US';

/** The locale's first day of the week (0 = Sunday); Sunday when it doesn't say. */
export function firstDayOfWeek(locale: Locale = enUS): Day {
    return locale.options?.weekStartsOn ?? 0;
}

/** The month's weeks, whole: days from the neighbouring months fill the first and last. */
export function monthWeeks(month: Date, weekStartsOn: Day = 0): Date[][] {
    const days = eachDayOfInterval({
        start: startOfWeek(startOfMonth(month), { weekStartsOn }),
        end: endOfWeek(endOfMonth(month), { weekStartsOn }),
    });
    const weeks: Date[][] = [];
    for (let index = 0; index < days.length; index += 7) weeks.push(days.slice(index, index + 7));

    return weeks;
}

/** The seven weekdays in header order, as dates of one week (format them for the labels). */
export function weekdays(weekStartsOn: Day = 0): Date[] {
    const start = startOfWeek(new Date(2026, 0, 7), { weekStartsOn });

    return Array.from({ length: 7 }, (_, index) => addDays(start, index));
}

export interface CalendarKey {
    key: string;
    shiftKey?: boolean;
}

/**
 * The day focus moves to for a key in the grid, or null when the key isn't
 * the grid's: arrows by a day or a week, PageUp/PageDown by a month (a year
 * with Shift), Home/End to the start or end of the week.
 */
export function moveFocus(day: Date, { key, shiftKey = false }: CalendarKey, weekStartsOn: Day = 0): Date | null {
    switch (key) {
        case 'ArrowLeft': return addDays(day, -1);
        case 'ArrowRight': return addDays(day, 1);
        case 'ArrowUp': return addDays(day, -7);
        case 'ArrowDown': return addDays(day, 7);
        case 'PageUp': return shiftKey ? addYears(day, -1) : addMonths(day, -1);
        case 'PageDown': return shiftKey ? addYears(day, 1) : addMonths(day, 1);
        case 'Home': return startOfWeek(day, { weekStartsOn });
        case 'End': return startOfDay(endOfWeek(day, { weekStartsOn }));
        default: return null;
    }
}

/**
 * The one day of the grid that's in the tab order: the day focus last moved
 * to, else the selected day, else today, when in the month shown; else the
 * month's first day.
 */
export function tabbableDay(month: Date, focused: Date | null, selected: Date | null, today: Date): Date {
    return [focused, selected, today].find((day): day is Date => day !== null && isSameMonth(day, month)) ?? startOfMonth(month);
}
