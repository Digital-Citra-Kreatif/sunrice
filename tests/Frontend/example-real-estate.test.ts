/*
 * The Lumina Estates example's own scripts (examples/real-estate/resources/js/enhance.js), with tiny fakes
 * instead of a browser.
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
// The example's scripts are plain JavaScript (tests/Frontend isn't type-checked).
import { dropEmptyFields, setPageInert, submitOnPointerChange } from '../../examples/real-estate/resources/js/enhance.js';

type Listener = (event: any) => void;

function fakeForm() {
    const listeners: Record<string, Listener[]> = {};
    let submits = 0;
    const form = {
        addEventListener: (type: string, listener: Listener) => { (listeners[type] ??= []).push(listener); },
        requestSubmit: () => { submits++; },
    };
    const fire = (type: string, event: object = {}) => listeners[type]?.forEach((listener) => listener(event));

    return { form, fire, submits: () => submits };
}

const select = { tagName: 'SELECT' };

test('filter selects never submit while a keyboard user arrows through them', () => {
    const { form, fire, submits } = fakeForm();
    submitOnPointerChange(form);

    fire('keydown');
    fire('change', { target: select });
    fire('change', { target: select });
    assert.equal(submits(), 0);
});

test('filter selects submit when changed with the mouse or touch, and only selects do', () => {
    const { form, fire, submits } = fakeForm();
    submitOnPointerChange(form);

    fire('pointerdown');
    fire('change', { target: select });
    assert.equal(submits(), 1);
    fire('change', { target: { tagName: 'INPUT' } });
    assert.equal(submits(), 1);
    // Back to the keyboard: no more submits on change.
    fire('keydown');
    fire('change', { target: select });
    assert.equal(submits(), 1);
});

test('a GET form leaves its empty fields out of the address', () => {
    const { form, fire } = fakeForm();
    dropEmptyFields(form);
    const formData = new FormData();
    formData.append('q', '');
    formData.append('listing_type', 'for-sale');
    formData.append('property_type', '');
    formData.append('sort', 'newest');

    fire('formdata', { formData });
    assert.deepEqual([...formData.entries()], [['listing_type', 'for-sale'], ['sort', 'newest']]);
});

test('the open mobile menu makes the rest of the page inert, and closing it gives it back', () => {
    const element = (name: string) => {
        const attributes = new Map<string, string>();
        return {
            name,
            attributes,
            contains: (other: unknown) => other === header,
            setAttribute: (key: string, value: string) => attributes.set(key, value),
            removeAttribute: (key: string) => attributes.delete(key),
        };
    };
    const skip = element('skip'); const main = element('main'); const footer = element('footer');
    const header: any = { ...element('header') };
    header.contains = () => false;
    header.ownerDocument = { body: { children: [skip, header, main, footer] } };
    skip.contains = main.contains = footer.contains = () => false;

    setPageInert(header, true);
    assert.deepEqual([skip, main, footer].map((el) => el.attributes.has('inert')), [true, true, true]);
    assert.equal(header.attributes.has('inert'), false);

    setPageInert(header, false);
    assert.deepEqual([skip, main, footer].map((el) => el.attributes.has('inert')), [false, false, false]);
});
