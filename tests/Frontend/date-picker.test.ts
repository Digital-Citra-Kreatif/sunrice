import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { dateLabel, formatDateValue, parseDateValue, parseTimeInput } from '../../packages/admin/resources/js/lib/date-value.ts';
import { enGB } from 'date-fns/locale/en-GB';
import { enUS } from 'date-fns/locale/en-US';
import { firstDayOfWeek, monthWeeks, moveFocus, tabbableDay, weekdays } from '../../packages/admin/resources/js/lib/calendar-grid.ts';
import { loadAdminModule, render } from './support/render.ts';

test('reads the strings native date inputs give, as local dates', () => {
    assert.deepEqual(parseDateValue('2026-10-19'), { date: new Date(2026, 9, 19), time: '' });
    assert.deepEqual(parseDateValue('2026-10-19T14:30'), { date: new Date(2026, 9, 19), time: '14:30' });
    assert.deepEqual(parseDateValue('2026-10-19T14:30:00'), { date: new Date(2026, 9, 19), time: '14:30' });
    assert.deepEqual(parseDateValue('2026-10-19 09:05'), { date: new Date(2026, 9, 19), time: '09:05' });
    assert.deepEqual(parseDateValue('soon'), { date: null, time: '' });
    assert.deepEqual(parseDateValue(null), { date: null, time: '' });
});

test('reads times as typed, on the 24-hour clock', () => {
    assert.equal(parseTimeInput('14:30'), '14:30');
    assert.equal(parseTimeInput(' 9:05 '), '09:05');
    assert.equal(parseTimeInput('0930'), '09:30');
    assert.equal(parseTimeInput('21.15'), '21:15');
    assert.equal(parseTimeInput('9'), '09:00');
    assert.equal(parseTimeInput('24:00'), null);
    assert.equal(parseTimeInput('12:60'), null);
    assert.equal(parseTimeInput('5:12 PM'), null);
});

test('writes the same strings back', () => {
    assert.equal(formatDateValue(new Date(2026, 9, 19), '', false), '2026-10-19');
    assert.equal(formatDateValue(new Date(2026, 9, 19), '14:30', false), '2026-10-19');
    assert.equal(formatDateValue(new Date(2026, 9, 19), '14:30', true), '2026-10-19T14:30');
    assert.equal(formatDateValue(new Date(2026, 0, 5), '', true), '2026-01-05T00:00');
    assert.equal(formatDateValue(null, '14:30', true), null);
});

test('labels a value for people', () => {
    assert.equal(dateLabel('2026-10-19', false), '19 Oct 2026');
    assert.equal(dateLabel('2026-10-19T14:30', true), '19 Oct 2026, 14:30');
    assert.equal(dateLabel('2026-10-19', true), '19 Oct 2026, 00:00');
    assert.equal(dateLabel(null, false), '');
});

test('renders a labelled trigger button that carries the value and the field id', async () => {
    const { DatePicker } = await loadAdminModule<{ DatePicker: ComponentType<any> }>('components/app/date-picker.tsx');

    const empty = render(DatePicker, { id: 'field-published_at', value: null, onChange: () => undefined });
    assert.match(empty, /<button[^>]*id="field-published_at"[^>]*type="button"/);
    assert.match(empty, /data-empty="true"/);
    assert.match(empty, /Pick a date/);

    const filled = render(DatePicker, { id: 'f', value: '2026-10-19T14:30', withTime: true, invalid: true, onChange: () => undefined });
    assert.match(filled, /aria-invalid="true"/);
    assert.match(filled, /data-empty="false"/);
    assert.match(filled, /19 Oct 2026, 14:30/);
});

test('lays a month out in whole weeks from the first day of the week', () => {
    const october = monthWeeks(new Date(2026, 9, 19));
    assert.equal(october.length, 5);
    assert.ok(october.every((week) => week.length === 7));
    assert.deepEqual(october[0]![0], new Date(2026, 8, 27), 'Sunday 27 September opens the first week');
    assert.deepEqual(october[4]![6], new Date(2026, 9, 31), 'Saturday 31 October closes the last');

    const mondays = monthWeeks(new Date(2026, 9, 1), 1);
    assert.deepEqual(mondays[0]![0], new Date(2026, 8, 28));
    assert.deepEqual(mondays.at(-1)![6], new Date(2026, 10, 1));

    // February 2026 starts on a Sunday and fills exactly four weeks.
    assert.equal(monthWeeks(new Date(2026, 1, 1)).length, 4);
});

test('takes the first day of the week from the date-fns locale', () => {
    assert.equal(firstDayOfWeek(), 0);
    assert.equal(firstDayOfWeek(enUS), 0);
    assert.equal(firstDayOfWeek(enGB), 1);
    assert.deepEqual(weekdays(1).map((day) => day.getDay()), [1, 2, 3, 4, 5, 6, 0]);
    assert.deepEqual(weekdays().map((day) => day.getDay()), [0, 1, 2, 3, 4, 5, 6]);
});

test('moves focus by day, week, month and year, and to the ends of the week', () => {
    const day = new Date(2026, 9, 21); // a Wednesday
    const move = (key: string, shiftKey = false, weekStartsOn: 0 | 1 = 0) => moveFocus(day, { key, shiftKey }, weekStartsOn);

    assert.deepEqual(move('ArrowLeft'), new Date(2026, 9, 20));
    assert.deepEqual(move('ArrowRight'), new Date(2026, 9, 22));
    assert.deepEqual(move('ArrowUp'), new Date(2026, 9, 14));
    assert.deepEqual(move('ArrowDown'), new Date(2026, 9, 28));
    assert.deepEqual(move('PageUp'), new Date(2026, 8, 21));
    assert.deepEqual(move('PageDown'), new Date(2026, 10, 21));
    assert.deepEqual(move('PageUp', true), new Date(2025, 9, 21));
    assert.deepEqual(move('PageDown', true), new Date(2027, 9, 21));
    assert.deepEqual(move('Home'), new Date(2026, 9, 18));
    assert.deepEqual(move('End'), new Date(2026, 9, 24));
    assert.deepEqual(move('Home', false, 1), new Date(2026, 9, 19));
    assert.deepEqual(move('End', false, 1), new Date(2026, 9, 25));
    assert.equal(move('Enter'), null);
    assert.equal(move('a'), null);
    // Across month ends, and a month later from the 31st lands on the month's last day.
    assert.deepEqual(moveFocus(new Date(2026, 9, 31), { key: 'ArrowRight' }), new Date(2026, 10, 1));
    assert.deepEqual(moveFocus(new Date(2026, 0, 31), { key: 'PageDown' }), new Date(2026, 1, 28));
});

test('keeps one day in the tab order: focused, else selected, else today, else the first', () => {
    const october = new Date(2026, 9, 1);
    const today = new Date(2026, 9, 20);

    assert.deepEqual(tabbableDay(october, new Date(2026, 9, 5), new Date(2026, 9, 9), today), new Date(2026, 9, 5));
    assert.deepEqual(tabbableDay(october, null, new Date(2026, 9, 9), today), new Date(2026, 9, 9));
    assert.deepEqual(tabbableDay(october, null, new Date(2026, 8, 9), today), today);
    assert.deepEqual(tabbableDay(new Date(2026, 11, 1), null, null, today), new Date(2026, 11, 1));
});

test('renders the calendar as a labelled grid with the selected day and today marked', async () => {
    const { Calendar } = await loadAdminModule<{ Calendar: ComponentType<any> }>('components/ui/calendar.tsx');
    const html = render(Calendar, { selected: new Date(2026, 9, 19), onSelect: () => undefined });

    const captionId = /<span id="([^"]+)"[^>]*>October 2026<\/span>/.exec(html)?.[1];
    assert.ok(captionId);
    assert.match(html, new RegExp(`<table role="grid" aria-labelledby="${captionId}"`));
    assert.match(html, /<th scope="col" aria-label="Sunday"[^>]*>Su<\/th>/);
    assert.match(html, /aria-label="Go to the previous month"/);
    assert.match(html, /aria-label="Go to the next month"/);
    assert.equal(html.match(/role="gridcell"/g)?.length, 35);
    assert.match(html, /<td role="gridcell" aria-selected="true"[^>]*><button[^>]*data-day="2026-10-19"[^>]*data-selected-single="true"[^>]*tabindex="0"[^>]*aria-label="Monday, October 19th, 2026"/);
    assert.equal(html.match(/tabindex="0"/g)?.length, 1, 'one day in the tab order');
    assert.equal(html.match(/tabindex="-1"/g)?.length, 34);
    assert.match(html, /data-day="2026-09-27"[^>]*data-outside="true"/);

    const now = new Date();
    const thisMonth = render(Calendar, {});
    const todayKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    assert.match(thisMonth, new RegExp(`data-day="${todayKey}" data-today="true"[^>]*tabindex="0"[^>]*aria-current="date"`));
});
