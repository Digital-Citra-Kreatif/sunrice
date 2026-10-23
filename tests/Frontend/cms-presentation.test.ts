import { test } from 'node:test';
import assert from 'node:assert/strict';
import { escapeHtml, inlineMarkdown, isSafeHref } from '../../packages/cms/resources/js/lib/inline-markdown.ts';
import { affixesOf, hintOf } from '../../packages/cms/resources/js/lib/presentation.ts';

test('escapes everything that isn\'t one of the allowed marks', () => {
    assert.equal(escapeHtml(`<a href="x">'&'</a>`), '&lt;a href=&quot;x&quot;&gt;&#39;&amp;&#39;&lt;/a&gt;');
    assert.equal(inlineMarkdown('<script>alert(1)</script>'), '&lt;script&gt;alert(1)&lt;/script&gt;');
    assert.equal(inlineMarkdown('<b>raw</b> & <img src=x onerror=alert(1)>'), '&lt;b&gt;raw&lt;/b&gt; &amp; &lt;img src=x onerror=alert(1)&gt;');
    assert.equal(inlineMarkdown('Plain words, event_day and 2 * 3.'), 'Plain words, event_day and 2 * 3.');
    assert.equal(inlineMarkdown(''), '');
});

test('renders bold, italic and code', () => {
    assert.equal(inlineMarkdown('Use **short** words, *please*.'), 'Use <strong>short</strong> words, <em>please</em>.');
    assert.equal(inlineMarkdown('**bold *and italic* too**'), '<strong>bold <em>and italic</em> too</strong>');
    // Nothing inside code is Markdown, and it's escaped.
    assert.equal(inlineMarkdown('Type `**<b>**` here'), 'Type <code>**&lt;b&gt;**</code> here');
    assert.equal(inlineMarkdown('**<i>x</i>**'), '<strong>&lt;i&gt;x&lt;/i&gt;</strong>');
});

test('makes safe links that open in a new tab', () => {
    assert.equal(inlineMarkdown('See the [style guide](https://example.com/guide?a=1&b=2).'),
        'See the <a href="https://example.com/guide?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">style guide</a>.');
    assert.equal(inlineMarkdown('[**Prices**](/prices)'), '<a href="/prices" target="_blank" rel="noopener noreferrer"><strong>Prices</strong></a>');
    assert.equal(inlineMarkdown('[mail](mailto:team@example.com)'), '<a href="mailto:team@example.com" target="_blank" rel="noopener noreferrer">mail</a>');
    // A quote can't leave the attribute.
    assert.ok(!inlineMarkdown('[x](https://example.com/"onmouseover="alert(1))').includes('" onmouseover'));
});

test('refuses links that could run script or leave the site oddly: their words only', () => {
    // Not even a link: parentheses in an address aren't read as one.
    assert.equal(inlineMarkdown('[click](javascript:alert(1))'), '[click](javascript:alert(1))');
    assert.equal(inlineMarkdown('[click](javascript:alert)'), 'click');
    assert.equal(inlineMarkdown('[click](JaVaScRiPt:alert)'), 'click');
    assert.equal(inlineMarkdown('[img](data:text/html,hi)'), 'img');
    assert.equal(inlineMarkdown('[x](//evil.example)'), 'x');
    for (const href of ['javascript:x', 'vbscript:x', 'data:x', 'java\tscript:x', '//x', 'about', '/\\evil']) {
        assert.equal(isSafeHref(href), false, href);
    }
    for (const href of ['https://example.com', 'http://example.com', '/about', '#prices', 'mailto:a@example.com', 'tel:+62123']) {
        assert.equal(isSafeHref(href), true, href);
    }
});

test('reads a field\'s hint, tooltip, prefix and suffix', () => {
    assert.deepEqual(hintOf({}), { hint: null, tooltip: null });
    assert.deepEqual(hintOf({ hint: ' Before tax ', hint_tooltip: '' }), { hint: 'Before tax', tooltip: null });
    assert.deepEqual(hintOf({ hint: null, hint_tooltip: 'Longer' }), { hint: null, tooltip: 'Longer' });
    assert.deepEqual(affixesOf({ type: 'number', config: { prefix: 'Rp', suffix: '' } }), { prefix: 'Rp', suffix: null });
    assert.deepEqual(affixesOf({ type: 'text', config: { suffix: 'm²' } }), { prefix: null, suffix: 'm²' });
    // Only text and number inputs carry them.
    assert.deepEqual(affixesOf({ type: 'textarea', config: { prefix: 'Rp' } }), { prefix: null, suffix: null });
    assert.deepEqual(affixesOf({ type: 'number' }), { prefix: null, suffix: null });
});
