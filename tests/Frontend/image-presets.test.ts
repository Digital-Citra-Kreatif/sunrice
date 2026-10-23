import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { IMAGE_ALIGNMENTS, IMAGE_WIDTHS, imageClass, parseImageClass } from '../../packages/admin/resources/js/lib/image-presets.ts';

test('image presets become classes on the <img>, and none leaves it as it was', () => {
    assert.equal(imageClass({ width: '50', align: 'left' }), 'sunrice-img sunrice-img-w-50 sunrice-img-left');
    assert.equal(imageClass({ width: '100', align: null }), 'sunrice-img sunrice-img-w-100');
    assert.equal(imageClass({ width: null, align: 'center' }), 'sunrice-img sunrice-img-center');
    assert.equal(imageClass({ width: null, align: null }), null);
    assert.equal(imageClass({}), null);
    // Only the presets: no other width or alignment is written.
    assert.equal(imageClass({ width: '33', align: 'middle' }), null);
});

test('classes read back into presets, ignoring any other class', () => {
    assert.deepEqual(parseImageClass('sunrice-img sunrice-img-w-75 sunrice-img-right'), { width: '75', align: 'right' });
    assert.deepEqual(parseImageClass('hero sunrice-img-full'), { width: null, align: 'full' });
    assert.deepEqual(parseImageClass('sunrice-img-w-33 sunrice-img-middle'), { width: null, align: null });
    assert.deepEqual(parseImageClass(null), { width: null, align: null });
    assert.deepEqual(parseImageClass(''), { width: null, align: null });
});

test('every preset round-trips', () => {
    for (const width of [null, ...IMAGE_WIDTHS]) {
        for (const align of [null, ...IMAGE_ALIGNMENTS]) {
            assert.deepEqual(parseImageClass(imageClass({ width, align })), { width, align });
        }
    }
});

test('the server keeps the same classes the editor writes', async () => {
    const php = await readFile(new URL('../../packages/admin/src/Resources/Forms/RichTextImageClasses.php', import.meta.url), 'utf8');
    assert.match(php, new RegExp(`WIDTHS = \\[${IMAGE_WIDTHS.map((w) => `'${w}'`).join(', ')}\\]`));
    assert.match(php, new RegExp(`ALIGNMENTS = \\[${IMAGE_ALIGNMENTS.map((a) => `'${a}'`).join(', ')}\\]`));
});

test('the selected image has its own labelled toolbar with pressed states and an alt text warning', async () => {
    const editor = await readFile(new URL('../../packages/admin/resources/js/components/app/rich-text-editor.tsx', import.meta.url), 'utf8');
    assert.match(editor, /<BubbleMenu/);
    assert.match(editor, /aria-label=\{t\('Image options'\)\}/);
    assert.match(editor, /aria-pressed=/);
    assert.match(editor, /t\('No alt text'\)/);
    assert.match(editor, /e\.key === 'Escape'/);
    // A library image is inserted as one node with its alt and media id (setImage() then updateAttributes() lost the id).
    assert.match(editor, /insertContent\(\{ type: 'image', attrs: \{ src: item\.url, alt: item\.alt \?\? item\.title \?\? '', title: item\.title \?\? null, 'data-media-id': String\(item\.id\) \} \}\)/);
});
