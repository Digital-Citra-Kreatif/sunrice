import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatList, looksValid, networkOf, parseList } from '../../packages/admin/resources/js/lib/networks.ts';

test('names the site a profile address is on, by its host', () => {
    assert.equal(networkOf('https://www.instagram.com/lumina')?.name, 'Instagram');
    assert.equal(networkOf('https://id.linkedin.com/company/lumina')?.name, 'LinkedIn');
    assert.equal(networkOf('https://twitter.com/lumina')?.name, 'X');
    assert.equal(networkOf('https://x.com/lumina')?.name, 'X');
    assert.equal(networkOf('https://youtu.be/abc')?.name, 'YouTube');
    assert.equal(networkOf('https://www.tiktok.com/@lumina')?.name, 'TikTok');
    assert.equal(networkOf('https://en.wikipedia.org/wiki/Lumina')?.name, 'Wikipedia');
    assert.equal(networkOf('https://facebook.com/lumina')?.name, 'Facebook');
    assert.equal(networkOf('https://notinstagram.com/lumina'), null);
    assert.equal(networkOf('https://example.com'), null);
    assert.equal(networkOf('not an address'), null);
});

test('splits a list field\'s text into rows and joins them back', () => {
    assert.deepEqual(parseList(''), []);
    assert.deepEqual(parseList('a\nb\r\nc'), ['a', 'b', 'c']);
    assert.deepEqual(parseList('a@example.com, b@example.com;c@example.com', true), ['a@example.com', 'b@example.com', 'c@example.com']);
    assert.equal(formatList(['a', '', 'b']), 'a\n\nb');
});

test('tells which rows look wrong, to mark them after a refused save', () => {
    assert.equal(looksValid('https://instagram.com/acme', 'url', true), true);
    assert.equal(looksValid('http://example.com', 'url', true), false);
    assert.equal(looksValid('http://example.com', 'url'), true);
    assert.equal(looksValid('instagram', 'url'), false);
    assert.equal(looksValid('a@example.com', 'email'), true);
    assert.equal(looksValid('nope', 'email'), false);
    assert.equal(looksValid('', 'email'), true);
});
