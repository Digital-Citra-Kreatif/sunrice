import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { loadCmsModule, render } from './support/render.ts';

type Lookup = { own: { view: string; exists: boolean } | null; fallbacks: { view: string; exists: boolean }[]; choices?: string[] };
type Module = {
    TemplateField: ComponentType<any>; TemplateOrder: ComponentType<any>;
    usedTemplate: (value: string, lookup: Lookup | null) => string | null; missingTemplate: (value: string, lookup: Lookup | null) => boolean; shortViewFile: (view: string) => string;
};

test('knows which template file a page uses, and when a typed one has no file yet', async () => {
    const { usedTemplate, missingTemplate, shortViewFile } = await loadCmsModule<Module>('components/TemplateHelp.tsx');
    const lookup: Lookup = { own: null, fallbacks: [{ view: 'cms.demo', exists: true }, { view: 'cms.entry', exists: false }], choices: ['cms.demo', 'cms.special'] };

    assert.equal(shortViewFile('cms.demo-landing'), 'cms/demo-landing.blade.php');
    assert.equal(usedTemplate('', lookup), 'cms.demo');
    assert.equal(usedTemplate('', { own: null, fallbacks: [{ view: 'cms.entry', exists: false }] }), null);
    // Its own file missing: the first fallback that exists, as the site does.
    assert.equal(usedTemplate('cms.special', { ...lookup, own: { view: 'cms.special', exists: false } }), 'cms.demo');
    assert.equal(usedTemplate('cms.special', { ...lookup, own: { view: 'cms.special', exists: true } }), 'cms.special');
    // Picked, not saved yet: one of the views that exist.
    assert.equal(usedTemplate('cms.special', lookup), 'cms.special');
    assert.equal(missingTemplate('cms.special', lookup), false);
    assert.equal(missingTemplate('cms.not-yet', lookup), true);
    assert.equal(usedTemplate('cms.not-yet', lookup), 'cms.demo');
    assert.equal(missingTemplate('', lookup), false);
});

test('a template setting is a choice with two helper lines and the file in use; the order is in a popover', async () => {
    const { TemplateField, TemplateOrder } = await loadCmsModule<Module>('components/TemplateHelp.tsx');
    const lookup: Lookup = { own: null, fallbacks: [{ view: 'cms.collections.demo.show', exists: true }, { view: 'cms.entry', exists: false }], choices: ['cms.collections.demo.show'] };

    const html = render(TemplateField, { id: 'template', value: '', onChange: () => {}, lookup, example: 'cms.demo-landing', automatic: "Automatic uses the collection's template." });
    assert.match(html, /role="combobox"[^>]*>.*Automatic/);
    // Names → files first, then what Automatic uses.
    assert.match(html, /<p>Files are under resources\/views; a name like <code>cms.demo-landing<\/code> is <code>cms\/demo-landing.blade.php<\/code>.<\/p><p>Automatic uses the collection&#x27;s template.<\/p>/);
    // The file in use on one line, the whole path in a tooltip.
    assert.match(html, /Uses<\/span><code title="cms\/collections\/demo\/show.blade.php"[^>]*truncate[^>]*>cms\/collections\/demo\/show.blade.php<\/code>/);
    assert.match(html, />How is it chosen\?<\/button>/);
    assert.doesNotMatch(html, /<details/);
    assert.doesNotMatch(html, /No such file yet/);
    assert.match(render(TemplateField, { id: 'template', value: 'cms.nope', onChange: () => {}, lookup, example: 'cms.x', automatic: '' }), /No such file yet/);

    const order = render(TemplateOrder, { value: '', lookup });
    assert.match(order, /<li[^>]*><span[^>]*>1\.<\/span><code title="cms\/collections\/demo\/show.blade.php"[^>]*>cms\/collections\/demo\/show.blade.php<\/code><span[^>]*>in use<\/span><\/li>/);
    assert.match(order, /<li[^>]*><span[^>]*>2\.<\/span><code[^>]*>cms\/entry.blade.php<\/code><\/li>/);
    assert.match(order, /3\.<\/span><span[^>]*>the built-in template<\/span><\/li>/);
    assert.doesNotMatch(order, /resources\/views/);
});

test('adds the views to pick from to a lookup once they are loaded', async () => {
    const { withTemplateChoices } = await loadCmsModule<{ withTemplateChoices: (lookup: Lookup | null, choices: string[] | undefined) => Lookup | null }>('components/TemplateHelp.tsx');
    const lookup: Lookup = { own: null, fallbacks: [] };

    assert.equal(withTemplateChoices(lookup, undefined), lookup);
    assert.equal(withTemplateChoices(null, ['cms.x']), null);
    assert.deepEqual(withTemplateChoices(lookup, ['cms.x']), { own: null, fallbacks: [], choices: ['cms.x'] });
});
