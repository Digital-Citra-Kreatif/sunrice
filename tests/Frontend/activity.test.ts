import assert from 'node:assert/strict';
import { test } from 'node:test';
import { showsSourceBadge } from '../../packages/admin/resources/js/lib/activity.ts';

test('says "System" once on a system line without a person, and badges every other source', () => {
    assert.equal(showsSourceBadge({ source: 'system', userId: null, user: null }), false);
    assert.equal(showsSourceBadge({ source: 'system', userId: '3', user: 'Rina' }), true);
    assert.equal(showsSourceBadge({ source: 'admin', userId: '3', user: 'Rina' }), false);
    assert.equal(showsSourceBadge({ source: 'security', userId: null, user: null }), true);
    assert.equal(showsSourceBadge({ source: 'ai', userId: '3', user: 'Rina' }), true);
});
