import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bundleFor, joinPath } from '../../packages/admin/resources/js/lib/bundles.ts';
import { loadAdminModule } from './support/render.ts';

const bundle = (id: string, ...pages: string[]) => ({ id, module: `/${id}.js`, styles: [], pages });

test('finds the bundle that provides a page, longest prefix first', () => {
    const bundles = [bundle('shop', 'Shop/'), bundle('reports', 'Shop/Reports/'), bundle('crm', 'Crm/')];

    assert.equal(bundleFor('Shop/Orders/Index', bundles)?.id, 'shop');
    assert.equal(bundleFor('Shop/Reports/Sales', bundles)?.id, 'reports');
    assert.equal(bundleFor('Crm/Contacts', bundles)?.id, 'crm');
    assert.equal(bundleFor('Dashboard', bundles), undefined);
    assert.equal(bundleFor('Shopping/Cart', bundles), undefined);
});

test('joins admin URLs', () => {
    assert.equal(joinPath('/admin'), '/admin');
    assert.equal(joinPath('/admin', 'shop/orders'), '/admin/shop/orders');
    assert.equal(joinPath('admin/', '/shop'), '/admin/shop');
    assert.equal(joinPath('/backend/cms', 'x'), '/backend/cms/x');
    assert.equal(joinPath('/', 'x'), '/x');
    assert.equal(joinPath('/admin', 'https://example.com/a'), 'https://example.com/a');
    assert.equal(joinPath('/admin', '//cdn.example.com/a'), '//cdn.example.com/a');
});

test('loads a lazy plugin page once, when it first opens', async () => {
    const { pluginPage, registerPageLoader, registerPage } = await loadAdminModule<typeof import('../../packages/admin/resources/js/lib/page-registry.ts')>('lib/page-registry.ts');
    // Its bundle has already run (it registered the loaders), so nothing more is fetched.
    const bundles = [{ id: 'lazy', module: null, styles: [], pages: ['Lazy/'] }];
    const Orders = () => null;
    const Show = () => null;
    let calls = 0;
    registerPageLoader('Lazy/Orders', async () => { calls++; return { default: Orders }; });
    registerPageLoader('Lazy/Show', async () => Show);
    registerPage('Lazy/Plain', Orders);

    const [first, second] = await Promise.all([pluginPage('Lazy/Orders', bundles), pluginPage('Lazy/Orders', bundles)]);
    assert.equal(first, Orders);
    assert.equal(second, Orders);
    assert.equal(await pluginPage('Lazy/Orders', bundles), Orders);
    assert.equal(calls, 1);
    assert.equal(await pluginPage('Lazy/Show', bundles), Show);
    assert.equal(await pluginPage('Lazy/Plain', bundles), Orders);
});

test('a lazy page that fails to load is tried again on the next visit', async () => {
    const { pluginPage, registerPageLoader } = await loadAdminModule<typeof import('../../packages/admin/resources/js/lib/page-registry.ts')>('lib/page-registry.ts');
    const bundles = [{ id: 'flaky', module: null, styles: [], pages: ['Flaky/'] }];
    const Page = () => null;
    let calls = 0;
    registerPageLoader('Flaky/Page', async () => {
        calls++;
        if (calls === 1) throw new Error('offline');
        return { default: Page };
    });
    registerPageLoader('Flaky/Empty', async () => ({}) as never);

    await assert.rejects(pluginPage('Flaky/Page', bundles), /offline/);
    assert.equal(await pluginPage('Flaky/Page', bundles), Page);
    await assert.rejects(pluginPage('Flaky/Empty', bundles), /no component as default export/);
});
