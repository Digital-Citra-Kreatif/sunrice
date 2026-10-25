import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

const source = (path: string) => readFile(new URL(`../../packages/admin/resources/js/${path}`, import.meta.url), 'utf8');

test('the confirm dialog says "Continue" unless given a label', async () => {
    const confirm = await source('components/resources/confirm.tsx');
    assert.equal((confirm.match(/pending\??\.label \?\? t\('Continue'\)/g) ?? []).length, 2);
    assert.doesNotMatch(confirm, />\{t\('Continue'\)\}</);
});

test('permanently deleting from the trash is confirmed with "Permanently delete", not "Continue"', async () => {
    // A file (its details, the bulk bar) and a resource's records (a row, the bulk bar).
    assert.match(await source('components/media/media-details.tsx'), /onSuccess: onClose \}\), true, \[\], t\('Permanently delete'\)\)/);
    assert.match(await source('pages/Media/Index.tsx'), /bulk\('delete'\), true, \[\], t\('Permanently delete'\)\)/);
    const resources = await source('pages/Resources/Index.tsx');
    assert.match(resources, /\/force`, \{ preserveScroll: true \}\), true, \[\], t\('Permanently delete'\)\)/);
    assert.match(resources, /trashed && action\.key === 'force-delete' \? t\('Permanently delete'\) : undefined/);
});
