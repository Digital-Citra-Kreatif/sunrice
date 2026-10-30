import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { IMAGE_ALIGNMENTS, IMAGE_WIDTHS, captionText, figureClasses, imageClass, imageStylesOf, isStyleClass, parseImageClass, toggleStyle } from '../../packages/admin/resources/js/lib/image-presets.ts';

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
    assert.deepEqual(parseImageClass('sunrice-img sunrice-img-w-75 sunrice-img-right'), { width: '75', align: 'right', styles: [] });
    assert.deepEqual(parseImageClass('sunrice-img-w-33 sunrice-img-middle'), { width: null, align: null, styles: [] });
    assert.deepEqual(parseImageClass(null), { width: null, align: null, styles: [] });
    assert.deepEqual(parseImageClass(''), { width: null, align: null, styles: [] });
});

test('other well-formed classes read back as image styles, each once', () => {
    assert.deepEqual(parseImageClass('hero sunrice-img-full'), { width: null, align: 'full', styles: ['hero'] });
    assert.deepEqual(parseImageClass('sunrice-img sunrice-img-w-50 rounded shadow rounded 9x'), { width: '50', align: null, styles: ['rounded', 'shadow'] });
});

test('styles are written after the presets, and alone without them', () => {
    assert.equal(imageClass({ width: '50', align: 'left', styles: ['rounded', 'shadow'] }), 'sunrice-img sunrice-img-w-50 sunrice-img-left rounded shadow');
    assert.equal(imageClass({ styles: ['rounded'] }), 'rounded');
    assert.equal(imageClass({ styles: [] }), null);
    // Not a class name, a preset's class, a duplicate or not a list: left out.
    assert.equal(imageClass({ styles: ['rounded', 'a b', '"x', 'sunrice-img-w-33', 'rounded', 7] }), 'rounded');
    assert.equal(imageClass({ styles: 'rounded' }), null);
});

test('a style class is a CSS class name that is not a preset\'s', () => {
    for (const name of ['rounded', '_x', 'Shadow-lg', 'a_b-1']) assert.equal(isStyleClass(name), true, name);
    for (const name of ['', '1x', '-x', 'a b', 'a.b', 'sunrice-img', 'sunrice-img-w-50', 'sunrice-imgx', null, 3]) assert.equal(isStyleClass(name), false, String(name));
});

test('the imageStyles prop is read defensively', () => {
    assert.deepEqual(imageStylesOf([{ class: 'rounded', label: 'Rounded' }, { class: 'shadow', label: '' }, { class: 'rounded', label: 'Again' }, { class: 'bad name', label: 'X' }, null, 'x']),
        [{ class: 'rounded', label: 'Rounded' }, { class: 'shadow', label: 'shadow' }]);
    assert.deepEqual(imageStylesOf(undefined), []);
    assert.deepEqual(imageStylesOf({ rounded: 'Rounded' }), []);
});

test('toggling a style keeps the configured order', () => {
    const order = ['rounded', 'shadow', 'border'];
    assert.deepEqual(toggleStyle([], 'shadow', order), ['shadow']);
    assert.deepEqual(toggleStyle(['shadow'], 'rounded', order), ['rounded', 'shadow']);
    assert.deepEqual(toggleStyle(['rounded', 'shadow'], 'rounded', order), ['shadow']);
    // A class no longer configured stays (the server drops it on save), after the configured ones.
    assert.deepEqual(toggleStyle(['old'], 'border', order), ['border', 'old']);
    assert.deepEqual(toggleStyle(['b'], 'a'), ['b', 'a']);
});

test('every preset and style combination round-trips', () => {
    for (const width of [null, ...IMAGE_WIDTHS]) {
        for (const align of [null, ...IMAGE_ALIGNMENTS]) {
            for (const styles of [[], ['rounded'], ['rounded', 'shadow']]) {
                assert.deepEqual(parseImageClass(imageClass({ width, align, styles })), { width, align, styles });
            }
        }
    }
});

test('the server keeps the same classes the editor writes', async () => {
    const php = await readFile(new URL('../../packages/admin/src/Resources/Forms/RichTextImageClasses.php', import.meta.url), 'utf8');
    assert.match(php, new RegExp(`WIDTHS = \\[${IMAGE_WIDTHS.map((w) => `'${w}'`).join(', ')}\\]`));
    assert.match(php, new RegExp(`ALIGNMENTS = \\[${IMAGE_ALIGNMENTS.map((a) => `'${a}'`).join(', ')}\\]`));
    assert.ok(php.includes("CLASS_PATTERN = '/^[A-Za-z_][A-Za-z0-9_-]*$/'"));
    assert.ok(php.includes("str_starts_with($class, 'sunrice-img')"));
});

test('the selected image has its own labelled toolbar with pressed states and an alt text warning', async () => {
    const editor = await readFile(new URL('../../packages/admin/resources/js/components/app/rich-text-editor.tsx', import.meta.url), 'utf8');
    assert.match(editor, /<BubbleMenu/);
    assert.match(editor, /aria-label=\{t\('Image options'\)\}/);
    assert.match(editor, /aria-pressed=/);
    assert.match(editor, /t\('No alt text'\)/);
    // The configured styles: toggles in their own labelled group, only when there are some.
    assert.match(editor, /styles\.length > 0 && \(/);
    assert.match(editor, /role="group" aria-label=\{t\('Style'\)\}/);
    assert.match(editor, /e\.key === 'Escape'/);
    // A library image is inserted as one node with its alt and media id (setImage() then updateAttributes() lost the id).
    assert.match(editor, /insertContent\(\{ type: 'image', attrs: \{ src: item\.url, alt: item\.alt \?\? item\.title \?\? '', title: item\.title \?\? null, 'data-media-id': String\(item\.id\) \} \}\)/);
});

test('a captioned image has its presets on the figure and its styles on the image', () => {
    assert.deepEqual(figureClasses({ width: '50', align: 'left', styles: ['rounded', 'shadow'] }), { figure: 'sunrice-img sunrice-img-w-50 sunrice-img-left', img: 'rounded shadow' });
    assert.deepEqual(figureClasses({ width: '25', align: null, styles: null }), { figure: 'sunrice-img sunrice-img-w-25', img: null });
    assert.deepEqual(figureClasses({ styles: ['rounded'] }), { figure: null, img: 'rounded' });
    assert.deepEqual(figureClasses({}), { figure: null, img: null });
    // Read back from the figure's and the image's classes together: the same presets and styles.
    assert.deepEqual(parseImageClass('sunrice-img sunrice-img-w-50 sunrice-img-left rounded shadow'), { width: '50', align: 'left', styles: ['rounded', 'shadow'] });
});

test('a caption is saved as one line of text, and an empty one is none', () => {
    assert.equal(captionText('  An old\n  saw  '), 'An old saw');
    assert.equal(captionText('Typing a '), 'Typing a');
    assert.equal(captionText('   '), null);
    assert.equal(captionText(''), null);
    assert.equal(captionText(null), null);
    assert.equal(captionText(42), null);
    // Its text as typed: the editor escapes it when it writes the HTML.
    assert.equal(captionText('<b>"A & B"</b>'), '<b>"A & B"</b>');
});
