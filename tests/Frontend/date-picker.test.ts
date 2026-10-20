import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { dateLabel, formatDateValue, parseDateValue } from '../../packages/admin/resources/js/lib/date-value.ts';
import { loadAdminModule, render } from './support/render.ts';

test('reads the strings native date inputs give, as local dates', () => {
    assert.deepEqual(parseDateValue('2026-10-19'), { date: new Date(2026, 9, 19), time: '' });
    assert.deepEqual(parseDateValue('2026-10-19T14:30'), { date: new Date(2026, 9, 19), time: '14:30' });
    assert.deepEqual(parseDateValue('2026-10-19T14:30:00'), { date: new Date(2026, 9, 19), time: '14:30' });
    assert.deepEqual(parseDateValue('2026-10-19 09:05'), { date: new Date(2026, 9, 19), time: '09:05' });
    assert.deepEqual(parseDateValue('soon'), { date: null, time: '' });
    assert.deepEqual(parseDateValue(null), { date: null, time: '' });
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
