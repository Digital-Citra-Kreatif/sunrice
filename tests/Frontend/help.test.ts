import assert from 'node:assert/strict';
import { test } from 'node:test';
import { currentHeading, helpVisit } from '../../packages/admin/resources/js/lib/help.ts';

const here = 'https://example.test/admin/guide/panel/welcome';

test('visits other help pages through Inertia', () => {
    assert.equal(helpVisit('/admin/guide/website/entries#drafts', here, '/admin'), '/admin/guide/website/entries#drafts');
    assert.equal(helpVisit('https://example.test/admin/docs/cms/site', here, '/admin'), '/admin/docs/cms/site');
    assert.equal(helpVisit('/backend/docs/panel/resources', 'https://example.test/backend/docs', '/backend/'), '/backend/docs/panel/resources');
});

test('leaves anchors, other sites, images and the rest of the panel to the browser', () => {
    assert.equal(helpVisit('#signing-in', here, '/admin'), null);
    assert.equal(helpVisit('https://laravel.com/docs', here, '/admin'), null);
    assert.equal(helpVisit('/admin/guide/images/panel/login.webp', here, '/admin'), null);
    assert.equal(helpVisit('/admin/settings', here, '/admin'), null);
    assert.equal(helpVisit('/admin/guidebook', here, '/admin'), null);
    assert.equal(helpVisit('/docs/panel/resources', here, '/admin'), null);
});

test('marks the heading scrolled past last', () => {
    assert.equal(currentHeading([]), null);
    assert.equal(currentHeading([{ id: 'a', top: 300 }, { id: 'b', top: 900 }]), 'a');
    assert.equal(currentHeading([{ id: 'a', top: -400 }, { id: 'b', top: 40 }, { id: 'c', top: 500 }]), 'b');
});
