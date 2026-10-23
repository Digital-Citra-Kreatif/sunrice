import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isPasswordRules, meetsPasswordRules, passwordChecks } from '../../packages/admin/resources/js/lib/password-rules.ts';

const strong = { min: 12, mixedCase: true, letters: false, numbers: true, symbols: true };

test('ticks each strong rule as it is met', () => {
    assert.deepEqual(passwordChecks(strong, ''), [
        { key: 'length', met: false }, { key: 'mixedCase', met: false }, { key: 'numbers', met: false }, { key: 'symbols', met: false },
    ]);
    assert.deepEqual(passwordChecks(strong, 'plum tree'), [
        { key: 'length', met: false }, { key: 'mixedCase', met: false }, { key: 'numbers', met: false }, { key: 'symbols', met: true },
    ]);
    assert.deepEqual(passwordChecks(strong, 'Plum-tree-Sky').map((check) => check.met), [true, true, false, true]);
    assert.equal(meetsPasswordRules(strong, 'Plum-tree-Sky-42!'), true);
    assert.equal(meetsPasswordRules(strong, 'PLUM-TREE-SKY-42!'), false);
});

test('counts and matches like the server: characters, Unicode cases, numbers and symbols', () => {
    // 12 characters, not 13 UTF-16 code units.
    assert.equal(passwordChecks({ ...strong, min: 12 }, 'Ébène-ÇA-ç2😀')[0]?.met, true);
    assert.equal(passwordChecks({ ...strong, min: 13 }, 'Ébène-ÇA-ç2😀')[0]?.met, false);
    assert.equal(meetsPasswordRules(strong, 'Ébène-ÇA-ça-2026'), true);
    assert.equal(passwordChecks(strong, 'abc٣')[2]?.met, true); // an Arabic-Indic digit is a number
});

test('shows only what the app\'s own rules ask for when strong passwords are off', () => {
    assert.deepEqual(passwordChecks({ min: 8, mixedCase: false, letters: false, numbers: false, symbols: false }, 'secret'), [{ key: 'length', met: false }]);
    assert.deepEqual(passwordChecks({ min: 8, mixedCase: false, letters: true, numbers: true, symbols: false }, '12345678'), [
        { key: 'length', met: true }, { key: 'letters', met: false }, { key: 'numbers', met: true },
    ]);
    assert.deepEqual(passwordChecks(null, 'anything'), []);
    assert.deepEqual(passwordChecks(undefined, 'anything'), []);
});

test('recognises the rules sent by the server', () => {
    assert.equal(isPasswordRules(strong), true);
    assert.equal(isPasswordRules(null), false);
    assert.equal(isPasswordRules({ min: '12' }), false);
    assert.equal(isPasswordRules({ min: 12, mixedCase: true }), false);
});
