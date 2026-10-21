import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { loadAdminModule, render } from './support/render.ts';

type Notice = ComponentType<{ by: string | null; onTakeOver?: () => void }>;

test('the viewing notice offers Take over when it can', async () => {
    const { ViewingNotice } = await loadAdminModule<{ ViewingNotice: Notice }>('components/app/edit-lock.tsx');

    const html = render(ViewingNotice, { by: 'Budi', onTakeOver: () => {} });
    assert.match(html, /Budi is editing this\. You&#x27;re viewing it; saving is off\./);
    assert.match(html, />Take over<\/button>/);
    assert.doesNotMatch(render(ViewingNotice, { by: 'Budi' }), /Take over/);
    assert.equal(render(ViewingNotice, { by: null }), '');
});
