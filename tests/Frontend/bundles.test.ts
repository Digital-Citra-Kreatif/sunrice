import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bundleFor, joinPath } from '../../packages/admin/resources/js/lib/bundles.ts';

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
