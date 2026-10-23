import assert from 'node:assert/strict';
import { test } from 'node:test';
import { fieldGroups } from '../../packages/admin/resources/js/lib/field-groups.ts';
import { isShownBy } from '../../packages/admin/resources/js/lib/show-when.ts';

test('groups a section\'s fields by their group, in order, leaving out hidden ones', () => {
    const fields = [
        { name: 'title' },
        { name: 'single_1', group: 'Posts', showWhen: { field: 'collections', value: '1' } },
        { name: 'term_pages_1', group: 'Posts', showWhen: { field: 'collections', value: '1' } },
        { name: 'single_2', group: 'Pages', showWhen: { field: 'collections', value: '2' } },
        { name: 'template', group: '' },
    ];
    const names = (groups: ReturnType<typeof fieldGroups<(typeof fields)[number]>>) => groups.map((g) => [g.title, g.fields.map((f) => f.name)]);

    assert.deepEqual(names(fieldGroups(fields)), [[null, ['title']], ['Posts', ['single_1', 'term_pages_1']], ['Pages', ['single_2']], [null, ['template']]]);
    // Only Pages ticked: Posts' group is gone with its fields.
    const shown = fields.filter((f) => isShownBy(f.showWhen ?? null, { collections: ['2'] }));
    assert.deepEqual(names(fieldGroups(shown)), [[null, ['title']], ['Pages', ['single_2']], [null, ['template']]]);
    assert.deepEqual(fieldGroups([]), []);
});
