import assert from 'node:assert/strict';
import { test } from 'node:test';
import { t } from '../../packages/admin/resources/js/lib/i18n.ts';

test('says the panel\'s words in its language, with Laravel\'s placeholders', () => {
    (globalThis as { sunriceTranslations?: unknown }).sunriceTranslations = { Save: 'Simpan', 'Delete :title?': 'Hapus :title?', ':Name joined': ':Name bergabung' };

    assert.equal(t('Save'), 'Simpan');
    assert.equal(t('Not translated'), 'Not translated');
    assert.equal(t('Delete :title?', { title: 'News' }), 'Hapus News?');
    assert.equal(t(':Name joined', { name: 'rina' }), 'Rina bergabung');
    assert.equal(t('Hi :name, :names', { name: 'A', names: 'B' }), 'Hi A, B');

    delete (globalThis as { sunriceTranslations?: unknown }).sunriceTranslations;
    assert.equal(t('Save'), 'Save');
});
