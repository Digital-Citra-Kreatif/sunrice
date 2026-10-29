import { test } from 'node:test';
import assert from 'node:assert/strict';
import { brandHeader, brandPreviewQuery, initials, isHexColor, luminance, normalizeHex, pageTitle, readableOn } from '../../packages/admin/resources/js/lib/branding.ts';

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

test('lays out the sidebar header as the server resolved it, the collapsed one always with the favicon\'s mark', () => {
    const wide = { logo: '/wide.png', logoWide: true };
    const square = { logo: '/square.png', logoWide: false };

    // Logo and name: a square logo beside the name; a wide one gives way to the mark (the icon, else the lettermark).
    assert.deepEqual(brandHeader({ ...square, display: 'logo_name', mark: '/square.png' }),
        { display: 'logo_name', wordmark: null, showMark: true, mark: '/square.png', showName: true, collapsedMark: '/square.png' });
    assert.deepEqual(brandHeader({ ...square, display: 'logo_name', mark: '/icon.png' }),
        { display: 'logo_name', wordmark: null, showMark: true, mark: '/square.png', showName: true, collapsedMark: '/icon.png' });
    assert.deepEqual(brandHeader({ ...wide, display: 'logo_name', mark: '/icon.png' }),
        { display: 'logo_name', wordmark: null, showMark: true, mark: '/icon.png', showName: true, collapsedMark: '/icon.png' });
    assert.equal(brandHeader({ ...wide, display: 'logo_name', mark: null }).mark, null);

    // Logo only: the wide logo alone, never squashed into the collapsed square.
    assert.deepEqual(brandHeader({ ...wide, display: 'logo', mark: null }),
        { display: 'logo', wordmark: '/wide.png', showMark: false, mark: null, showName: false, collapsedMark: null });
    assert.equal(brandHeader({ ...wide, display: 'logo', mark: '/icon.png' }).collapsedMark, '/icon.png');

    // Icon and name: the icon even when the logo is square.
    assert.deepEqual(brandHeader({ ...square, display: 'icon_name', mark: '/icon.png' }),
        { display: 'icon_name', wordmark: null, showMark: true, mark: '/icon.png', showName: true, collapsedMark: '/icon.png' });

    // Name only: no mark beside the name, the mark when collapsed.
    assert.deepEqual(brandHeader({ logo: null, display: 'name', mark: '/icon.png' }),
        { display: 'name', wordmark: null, showMark: false, mark: '/icon.png', showName: true, collapsedMark: '/icon.png' });
});

test('keeps today\'s header for page data without display and mark', () => {
    assert.deepEqual(brandHeader({ logo: '/wide.png', logoWide: true }),
        { display: 'logo', wordmark: '/wide.png', showMark: false, mark: null, showName: false, collapsedMark: null });
    assert.deepEqual(brandHeader({ logo: '/square.png' }),
        { display: 'logo_name', wordmark: null, showMark: true, mark: '/square.png', showName: true, collapsedMark: '/square.png' });
    assert.deepEqual(brandHeader({ logo: null }),
        { display: 'logo_name', wordmark: null, showMark: true, mark: null, showName: true, collapsedMark: null });
});

test('sends the preview only the values it shows', () => {
    assert.deepEqual(brandPreviewQuery({ name: 'Northline', tagline: '', logo: 4, favicon: null, color: '#1e66f5', loginText: 'secret', brandDisplay: 'logo' }),
        { name: 'Northline', tagline: '', logo: 4, favicon: null, color: '#1e66f5' });
    assert.deepEqual(brandPreviewQuery({ logo: '4', favicon: 2.5 }), { name: null, tagline: null, logo: null, favicon: null, color: null });
});
