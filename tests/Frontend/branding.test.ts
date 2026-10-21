import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initials, isHexColor, luminance, normalizeHex, pageTitle, readableOn } from '../../packages/admin/resources/js/lib/branding.ts';

test('makes up to two initials from the name, else the email', () => {
    assert.equal(initials('Rina Wijaya', 'rina@example.com'), 'RW');
    assert.equal(initials('rina', null), 'R');
    assert.equal(initials('Anna Maria Lopez'), 'AM');
    assert.equal(initials(null, 'budi.santoso@example.com'), 'BS');
    assert.equal(initials('  ', 'x@example.com'), 'XE');
    assert.equal(initials('élan vital'), 'ÉV');
    assert.equal(initials(null, null), '?');
});

test('recognises #rrggbb colours only', () => {
    assert.equal(isHexColor('#1e66f5'), true);
    assert.equal(isHexColor('#1E66F5'), true);
    assert.equal(isHexColor('#fff'), false);
    assert.equal(isHexColor('red'), false);
    assert.equal(isHexColor('#1e66f5;}'), false);
});

test('picks black or white text by contrast, as the server does', () => {
    assert.equal(luminance('#ffffff'), 1);
    assert.equal(luminance('#000000'), 0);
    assert.equal(readableOn('#ffffff'), '#111111');
    assert.equal(readableOn('#000000'), '#ffffff');
    assert.equal(readableOn('#1e66f5'), '#ffffff');
    assert.equal(readableOn('#facc15'), '#111111');
    assert.equal(readableOn('#22c55e'), '#111111');
    assert.equal(readableOn('#7c3aed'), '#ffffff');
});

test('names the panel once in the document title', () => {
    assert.equal(pageTitle('Users', 'Lumina Estates'), 'Users · Lumina Estates');
    assert.equal(pageTitle('Log in to Lumina Estates', 'Lumina Estates'), 'Log in to Lumina Estates');
    assert.equal(pageTitle('', 'Lumina Estates'), 'Lumina Estates');
    assert.equal(pageTitle(undefined, 'Lumina Estates'), 'Lumina Estates');
});

test('gives six typed hex digits their # and keeps anything else as typed', () => {
    assert.equal(normalizeHex(' 0F4C4C '), '#0F4C4C');
    assert.equal(normalizeHex('#0F4C4C'), '#0F4C4C');
    assert.equal(normalizeHex('#0F4C4C00'), '#0F4C4C00');
    assert.equal(normalizeHex('teal'), 'teal');
});
