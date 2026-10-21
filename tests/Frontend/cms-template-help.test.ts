import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { loadCmsModule, render } from './support/render.ts';

type Lookup = { own: { view: string; exists: boolean } | null; fallbacks: { view: string; exists: boolean }[] };

test('a template setting says in one line what empty means, and lists the files tried, short, marking the one in use', async () => {
    const { TemplateHelp, usedTemplate, shortViewFile } = await loadCmsModule<{
        TemplateHelp: ComponentType<any>; usedTemplate: (value: string, lookup: Lookup | null) => string | null; shortViewFile: (view: string) => string;
    }>('components/TemplateHelp.tsx');
    const lookup: Lookup = { own: null, fallbacks: [{ view: 'cms.demo', exists: true }, { view: 'cms.entry', exists: false }] };

    assert.equal(shortViewFile('cms.demo-landing'), 'cms/demo-landing.blade.php');
    assert.equal(usedTemplate('', lookup), 'cms.demo');
    assert.equal(usedTemplate('', { own: null, fallbacks: [{ view: 'cms.entry', exists: false }] }), null);
    assert.equal(usedTemplate('cms.special', { ...lookup, own: { view: 'cms.special', exists: false } }), null);
    assert.equal(usedTemplate('cms.special', { ...lookup, own: { view: 'cms.special', exists: true } }), 'cms.special');

    const html = render(TemplateHelp, { example: 'cms.demo-landing', empty: "Leave empty to use the collection's template.", value: '', lookup });
    assert.match(html, /^<div[^>]*><p>Leave empty to use the collection&#x27;s template.<\/p><details/);
    assert.match(html, /<summary[^>]*>Which file\?<\/summary>/);
    // Relative to resources/views, breaking only after slashes, the one in use marked.
    assert.match(html, /<li><code[^>]*>cms\/<wbr\/>demo.blade.php<\/code><span[^>]*>in use<\/span><\/li>/);
    assert.match(html, /<li><code[^>]*>cms\/<wbr\/>entry.blade.php<\/code><\/li>/);
    assert.doesNotMatch(html, /resources\/views\/cms/);
    assert.doesNotMatch(html, /break-all/);
});
