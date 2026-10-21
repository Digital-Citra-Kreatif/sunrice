import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isShown } from '../../packages/cms/resources/js/lib/conditions.ts';

const field = (operator: 'equals' | 'not_equals' | 'filled' | 'empty', value: unknown = null) =>
    ({ handle: 'video', type: 'text', condition: { field: 'layout', operator, value } }) as never;

test('shows fields without a condition', () => {
    assert.equal(isShown({ handle: 'title', type: 'text' }, {}), true);
});

test('compares with the other field, as the server does', () => {
    assert.equal(isShown(field('equals', 'video'), { layout: 'video' }), true);
    assert.equal(isShown(field('equals', 'video'), { layout: 'image' }), false);
    assert.equal(isShown(field('equals', 'video'), { layout: ['image', 'video'] }), true);
    assert.equal(isShown(field('not_equals', 'video'), { layout: 'image' }), true);
    assert.equal(isShown(field('equals', 'true'), { layout: true }), true);
    assert.equal(isShown(field('equals', 3), { layout: '3' }), true);
});

test('knows filled from empty', () => {
    for (const empty of [null, undefined, '', false, []]) {
        assert.equal(isShown(field('filled'), { layout: empty }), false);
        assert.equal(isShown(field('empty'), { layout: empty }), true);
    }
    assert.equal(isShown(field('filled'), { layout: 'x' }), true);
    assert.equal(isShown(field('filled'), { layout: 0 }), true);
});

test('shows a field by several rules, all or any of them', async () => {
    const rules = [{ field: 'layout', operator: 'equals', value: 'video' }, { field: 'featured', operator: 'equals', value: true }];
    const all = { handle: 'video', type: 'text', condition: { match: 'all', rules } } as never;
    const any = { handle: 'video', type: 'text', condition: { match: 'any', rules } } as never;

    assert.equal(isShown(all, { layout: 'video', featured: true }), true);
    assert.equal(isShown(all, { layout: 'video', featured: false }), false);
    assert.equal(isShown(any, { layout: 'image', featured: true }), true);
    assert.equal(isShown(any, { layout: 'image', featured: false }), false);
});

test('stores no rule as none, one rule as it was, several with how they combine', async () => {
    const { conditionFrom, conditionRules, conditionMatch } = await import('../../packages/cms/resources/js/lib/conditions.ts');
    const rule = { field: 'layout', operator: 'filled' as const };

    assert.equal(conditionFrom([], 'any'), null);
    assert.deepEqual(conditionFrom([rule], 'any'), rule);
    assert.deepEqual(conditionFrom([rule, rule], 'any'), { match: 'any', rules: [rule, rule] });
    assert.deepEqual(conditionRules(rule), [rule]);
    assert.deepEqual(conditionRules(null), []);
    assert.equal(conditionMatch(rule), 'all');
    assert.equal(conditionMatch({ match: 'any', rules: [rule] }), 'any');
});

test('chooses a rule\'s value with an input that fits the other field', async () => {
    const { conditionValueInput, startValue } = await import('../../packages/cms/resources/js/lib/conditions.ts');

    assert.deepEqual(conditionValueInput({ type: 'select', config: { options: [{ value: 'video', label: 'Video' }, { value: 'image' }, 'plain'] } }),
        { kind: 'options', options: [{ value: 'video', label: 'Video' }, { value: 'image', label: 'image' }, { value: 'plain', label: 'plain' }] });
    assert.deepEqual(conditionValueInput({ type: 'select', config: {} }), { kind: 'text' });
    assert.deepEqual(conditionValueInput({ type: 'toggle' }), { kind: 'toggle' });
    assert.deepEqual(conditionValueInput({ type: 'terms', config: { taxonomy: 'topics' } }), { kind: 'terms', taxonomy: 'topics' });
    assert.deepEqual(conditionValueInput({ type: 'entries', config: { collections: ['blog'] } }), { kind: 'entries', collections: ['blog'] });
    assert.deepEqual(conditionValueInput({ type: 'number' }), { kind: 'number' });
    assert.deepEqual(conditionValueInput({ type: 'text' }), { kind: 'text' });
    assert.equal(startValue({ kind: 'toggle' }), true);
    assert.equal(startValue({ kind: 'options', options: [{ value: 'video', label: 'Video' }] }), 'video');
    assert.equal(startValue({ kind: 'text' }), '');
});
