#!/usr/bin/env node
// Real bundled editor + production configurations; synthetic content, loopback only.
import {createServer} from 'node:http';
import {readFile, mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
import path from 'node:path';
import os from 'node:os';
import {fileURLToPath} from 'node:url';
const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const require = createRequire(import.meta.url);
let playwright;
for (const name of [process.env.RED_PLAYWRIGHT_MODULE, 'playwright', path.join(os.homedir(), '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')].filter(Boolean)) {
    try { playwright = require(name); break; } catch {}
}
if (!playwright) throw new Error('Playwright unavailable; no packages installed automatically.');
const php = process.env.RED_PHP_BIN || '/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php';
const output = process.env.RED_TINYMCE_QA_OUTPUT || '/private/tmp/redcms-tinymce-browser-qa';
await mkdir(output, {recursive: true});
const configs = {};
// Real generated compatibility policy; all existing editor checks run enforced.
const csp = execFileSync(php, ['-r', 'require $argv[1]; echo red_csp_policy();', path.join(root, 'includes/csp_helpers.php')], {encoding: 'utf8'});
for (const [profile, file] of [['article', 'new-article-form.js'], ['video', 'video-form.js']]) {
    const source = await readFile(path.join(root, 'admin/assets/js', file), 'utf8');
    const start = source.indexOf('    function editorConfig(');
    configs[profile] = source.slice(start, source.indexOf('\n    function ', start + 10));
}
for (const [profile, file] of [['gallery-new', 'new_gallery.php'], ['gallery-edit', 'edit_gallery.php'], ['advanced', 'edit_advanced.php']]) {
    const source = await readFile(path.join(root, 'admin/bin', file), 'utf8');
    const start = source.indexOf('tinymce.init({');
    const snippet = source.slice(start, source.indexOf('\n});', start) + 4);
    configs[profile] = execFileSync(php, ['-r', '$row=["GalleryType"=>"Banner"]; $Type="Banner"; ?>' + snippet], {encoding: 'utf8'});
}
const shell = '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/cp.css"></head><body><main id="advanced"><h1>RED-CMS editor compatibility</h1><div class="red-admin-editor-field"><textarea id="field">&lt;p&gt;Initial Ω&lt;/p&gt;</textarea><textarea id="independent">Independent Article field</textarea><textarea id="ShortDesc">&lt;p&gt;Legacy description&lt;/p&gt;</textarea></div></main><script src="/vendor/tinymce.min.js"></script></body></html>';
const server = createServer(async (req, res) => {
    try {
        const url = new URL(req.url, 'http://localhost');
        let bytes, type = 'text/html';
        if (url.pathname === '/') bytes = shell;
        else if (url.pathname.startsWith('/other/')) {
            const mode = url.pathname.slice(7);
            if (!['visual', 'normal', 'mismatch'].includes(mode)) throw new Error('mode');
            bytes = execFileSync(php, [path.join(root, 'scripts/other-content-browser-fixture.php'), mode], {encoding: 'utf8'}).replace('</head>', '<link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/cp.css"></head>').replace('</head>', '<script src="/vendor/tinymce.min.js"></script><script>window.jQuery=function(){return {serialize:function(){return "";}}};window.jQuery.ajax=function(){};</script></head>');
        } else if (url.pathname === '/bootstrap.css') { bytes = await readFile(path.join(root, 'css/bootstrap.min.css')); type = 'text/css'; }
        else if (url.pathname === '/reset.css') { bytes = await readFile(path.join(root, 'admin/assets/css/reset.css')); type = 'text/css'; }
        else if (url.pathname === '/cp.css') { bytes = await readFile(path.join(root, 'admin/assets/css/cp.css')); type = 'text/css'; }
        else if (url.pathname === '/admin/assets/js/other-form.js') { bytes = await readFile(path.join(root, 'admin/assets/js/other-form.js')); type = 'text/javascript'; }
        else if (url.pathname === '/favicon.ico') { res.writeHead(204); res.end(); return; }
        else if (url.pathname === '/fixture.svg') { bytes = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>'; type = 'image/svg+xml'; }
        else if (url.pathname.startsWith('/vendor/')) {
            const relative = url.pathname.slice(8);
            if (relative.includes('..') || !/^[a-zA-Z0-9_./-]+$/.test(relative)) throw new Error('path');
            bytes = await readFile(path.join(root, 'admin/assets/js/tiny_mce', relative));
            type = ({'.js':'text/javascript','.css':'text/css','.svg':'image/svg+xml','.woff':'font/woff','.woff2':'font/woff2'})[path.extname(relative)] || 'application/octet-stream';
        } else throw new Error('route');
        res.writeHead(200, {'Content-Type': type, 'Content-Security-Policy': csp}); res.end(bytes);
    } catch { res.writeHead(404); res.end('fixture unavailable'); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = 'http://127.0.0.1:' + server.address().port;
const browser = await playwright.chromium.launch({headless: true, executablePath: process.env.RED_CHROME_EXECUTABLE || (process.platform === 'darwin' ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : undefined)});
let count = 0;
function check(value, message) { if (!value) throw new Error(message); count++; }
try {
    for (const viewport of [{width:1440,height:1000}, {width:390,height:844}]) {
        for (const profile of ['article', 'video', 'gallery-new', 'gallery-edit', 'advanced', 'other']) {
            const context = await browser.newContext({viewport});
            const page = await context.newPage();
            const errors = [], external = [];
            page.on('pageerror', e => errors.push(e.message));
            page.on('console', m => { if (['error','warning'].includes(m.type())) errors.push(m.text()); });
            await page.route('**/*', route => { if (route.request().url().startsWith(origin + '/')) route.continue(); else { external.push(route.request().url()); route.abort(); } });
            await page.goto(origin + (profile === 'other' ? '/other/visual' : '/'));
            if (profile !== 'other') {
                await page.evaluate(async ({profile, source}) => {
                    if (profile === 'article' || profile === 'video') {
                        const factory = new Function(source + '; return editorConfig;')();
                        window.qaConfig = profile === 'article' ? factory('#field', 390, () => {}) : factory('#field', () => {});
                        await tinymce.init(window.qaConfig);
                    } else {
                        // Capture the exact inline configuration without needing any database state.
                        new Function('tinymce', source)({init: config => { window.qaConfig = config; }});
                        if (profile === 'advanced') {
                            await tinymce.init(window.qaConfig);
                            if (tinymce.get().length) throw new Error('Advanced unexpectedly selected a field');
                            window.qaConfig.selector = '#field';
                        }
                        await tinymce.init(window.qaConfig);
                    }
                }, {profile, source:configs[profile]});
            }
            await page.waitForFunction(() => tinymce.activeEditor?.initialized, {timeout:15000});
            const result = await page.evaluate(() => {
                const ed = tinymce.activeEditor;
                ed.setContent('<p>Round trip Ω <strong>bold</strong> <a href="/relative/path">link</a></p>');
                ed.undoManager.clear(); ed.undoManager.add();
                const before = ed.getContent();
                ed.undoManager.transact(() => ed.insertContent('<p>Added content</p>'));
                ed.undoManager.undo(); const undo = ed.getContent() === before;
                ed.undoManager.redo(); const redo = ed.getContent().includes('Added content');
                tinymce.triggerSave(); const saved = document.getElementById(ed.id).value === ed.getContent();
                ed.setContent('<p>Safe</p><img src="/fixture.svg" onerror="window.bad=true"><a href="javascript:alert(1)">bad</a>');
                const sanitized = !/onerror|javascript:/i.test(ed.getContent()) && !window.bad;
                ed.setContent('<p>Table test</p>');
                ed.execCommand('mceInsertTable', false, {rows:2,columns:2});
                const table = ed.getContent().includes('<table');
                return {version:tinymce.majorVersion+'.'+tinymce.minorVersion,editable:!ed.mode.isReadOnly(),undo,redo,saved,sanitized,table,license:ed.options.get('license_key')};
            });
            check(result.version === '8.9.3' && result.license === 'gpl' && result.editable, `${profile}: GPL editor is editable`);
            check(result.undo && result.redo && result.saved, `${profile}: undo/redo/save`);
            if (profile !== 'other') {
                await page.evaluate(() => tinymce.activeEditor.execCommand('mceCodeEditor'));
                const dialog = page.getByRole('dialog');
                await dialog.locator('textarea').fill('<p>Dialog saved Ω</p>');
                await dialog.getByRole('button', {name:'Save', exact:true}).click();
                check(await page.evaluate(() => tinymce.activeEditor.getContent({format:'text'}).includes('Dialog saved Ω')), `${profile}: source dialog save`);
            }
            await page.locator('h1,h2').first().click({force:true});
            await page.evaluate(() => scrollTo(0,0));
            await page.screenshot({path:path.join(output, `${profile}-${viewport.width}.png`), fullPage:true});
            if (profile === 'other') {
                check(await page.evaluate(() => {
                    const ed = tinymce.activeEditor;
                    ed.dispatch('change');
                    const form = document.querySelector('[data-red-other-form]');
                    RedAdminOtherForm.submit(form);
                    const saved = new TextDecoder().decode(Uint8Array.from(atob(form.querySelector('[data-other-content-base64]').value), c => c.charCodeAt(0)));
                    const parsed = new DOMParser().parseFromString(saved, 'text/html');
                    return saved === form.querySelector('[data-other-html-source]').value && parsed.querySelectorAll('td').length === 4 && parsed.body.textContent.includes('Table test');
                }), 'Other visual edit reaches canonical save payload');
            }
            check(result.sanitized && result.table, `${profile}: sanitization and tables`);
            if (profile === 'article') check(await page.locator('#independent').inputValue() === 'Independent Article field', 'Article fields remain independent');
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${profile}: no horizontal overflow at ${viewport.width}`);
            check(errors.length === 0 && external.length === 0, `${profile}: unexpected errors/requests: ${errors.join(' | ')} ${external.join(' | ')}`);
            if (profile !== 'other') {
                check(await page.evaluate(async () => {
                    tinymce.remove();
                    await tinymce.init(window.qaConfig);
                    return tinymce.get().length === 1 && tinymce.activeEditor.initialized;
                }), `${profile}: teardown and reinitialization`);
            }
            await context.close();
        }
    }
} finally { await browser.close(); await new Promise(resolve => server.close(resolve)); }
console.log(`TinyMCE browser QA passed: ${count} assertions; screenshots: ${output}`);
