#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFile, readdir} from 'node:fs/promises';
import {createHash} from 'node:crypto';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const vendor = path.join(root, 'admin/assets/js/tiny_mce');
const manifest = JSON.parse(await readFile(path.join(root, 'release/tinymce-vendor-manifest.json'), 'utf8'));
const actual = [];
async function walk(dir) {
    for (const entry of await readdir(dir, {withFileTypes:true})) {
        assert(!entry.isSymbolicLink(), 'Vendor symlink refused');
        const file = path.join(dir, entry.name);
        if (entry.isDirectory()) await walk(file);
        else actual.push(path.relative(vendor, file).split(path.sep).join('/'));
    }
}
await walk(vendor);
assert.deepEqual(actual.sort(), Object.keys(manifest.files).sort(), 'Exact upstream inventory');
for (const file of actual) assert.equal(createHash('sha256').update(await readFile(path.join(vendor, file))).digest('hex'), manifest.files[file], 'Vendor integrity: '+file);
const pkg = JSON.parse(await readFile(path.join(vendor, 'package.json'), 'utf8'));
assert.equal(pkg.version, '8.9.0');
assert(!pkg.scripts && !pkg.dependencies, 'No install scripts or runtime npm dependencies');
const bom = JSON.parse(await readFile(path.join(root, 'release/bundled-dependencies.cdx.json'), 'utf8'));
assert.equal(bom.components.find(c => c.name === 'tinymce').version, pkg.version);
const files = ['admin/assets/js/new-article-form.js', 'admin/assets/js/other-form.js', 'admin/assets/js/video-form.js', 'admin/bin/new_gallery.php', 'admin/bin/edit_gallery.php', 'admin/bin/edit_advanced.php'];
for (const file of files) {
    const source = await readFile(path.join(root, file), 'utf8');
    assert.match(source, /license_key:\s*['"]gpl['"]/);
    assert(!source.includes('styleselect'), 'Current toolbar identifiers: '+file);
    const group = source.match(/plugins:\s*\[([\s\S]*?)\]\.join\(' '\)/);
    assert(group, 'Flattened plugin list: '+file);
    const plugins = [...group[1].matchAll(/['"]([^'"]+)['"]/g)].flatMap(m => m[1].split(/\s+/));
    for (const plugin of plugins) assert(actual.includes(`plugins/${plugin}/plugin.min.js`), 'Bundled plugin: '+plugin);
}
console.log(`TinyMCE vendor check passed: ${actual.length} exact upstream files, six GPL/core configurations, matching dependency inventory.`);
