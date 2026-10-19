import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatBytes, mediaKind, uploadProblem } from '../../packages/admin/resources/js/components/media/format.ts';

test('formats file sizes', () => {
    assert.equal(formatBytes(512), '512 B');
    assert.equal(formatBytes(1536), '1.5 KB');
    assert.equal(formatBytes(20 * 1024 * 1024), '20 MB');
});

test('checks uploads before sending them', () => {
    const accept = '.jpg,.png,.pdf';
    assert.equal(uploadProblem({ name: 'photo.JPG', size: 1000 }, 10, accept), null);
    assert.equal(uploadProblem({ name: 'shell.php', size: 10 }, 10, accept), '.php files can\'t be uploaded.');
    assert.equal(uploadProblem({ name: 'README', size: 10 }, 10, accept), 'Files without an extension can\'t be uploaded.');
    assert.equal(uploadProblem({ name: 'big.pdf', size: 11 * 1024 }, 10, accept), 'Larger than the 10 KB limit.');
});

test('tells the kind of a file', () => {
    assert.equal(mediaKind('image/svg+xml'), 'image');
    assert.equal(mediaKind('video/mp4'), 'video');
    assert.equal(mediaKind('application/pdf'), 'document');
});
