// Writes a .gz and a .br copy next to each built script and stylesheet in the
// given dist/ folders, for web servers that serve precompressed files (nginx
// gzip_static / brotli_static, Caddy's precompressed, Apache rewrites): no
// compressing per request. Node's own zlib, no build plugin.
//
//     node scripts/precompress.mjs packages/admin/dist packages/cms/dist
import { readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { brotliCompressSync, constants, gzipSync } from 'node:zlib';

const COMPRESSIBLE = /\.(js|css)$/;

function* files(directory) {
    for (const name of readdirSync(directory)) {
        const path = join(directory, name);
        if (statSync(path).isDirectory()) {
            yield* files(path);
        } else {
            yield path;
        }
    }
}

for (const directory of process.argv.slice(2)) {
    let before = 0;
    let gzipped = 0;
    let brotli = 0;
    for (const path of [...files(directory)]) {
        // Copies of files that are gone, or from an older build.
        if (/\.(gz|br)$/.test(path)) {
            rmSync(path);
            continue;
        }
        if (!COMPRESSIBLE.test(path)) {
            continue;
        }
        const content = readFileSync(path);
        const gz = gzipSync(content, { level: 9 });
        const br = brotliCompressSync(content, { params: { [constants.BROTLI_PARAM_QUALITY]: 11, [constants.BROTLI_PARAM_SIZE_HINT]: content.length } });
        writeFileSync(`${path}.gz`, gz);
        writeFileSync(`${path}.br`, br);
        before += content.length;
        gzipped += gz.length;
        brotli += br.length;
    }
    const kb = (bytes) => `${(bytes / 1024).toFixed(0)} kB`;
    console.log(`${directory}: scripts and styles ${kb(before)}, gzip ${kb(gzipped)}, brotli ${kb(brotli)}`);
}
