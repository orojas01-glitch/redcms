#!/usr/bin/env node

import {execFileSync} from 'node:child_process';
import {access, mkdir, readFile} from 'node:fs/promises';
import {constants as fsConstants} from 'node:fs';
import {createRequire} from 'node:module';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import {fileURLToPath} from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.dirname(scriptDirectory);
const outputDirectory = process.env.RED_OTHER_BROWSER_OUTPUT || '/tmp/redcms-other-browser-qa';
const phpBinary = process.env.RED_PHP_BIN || '/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php';

function loadPlaywright() {
    const require = createRequire(import.meta.url);
    const candidates = [
        process.env.RED_PLAYWRIGHT_MODULE,
        'playwright',
        path.join(
            os.homedir(),
            '.cache',
            'codex-runtimes',
            'codex-primary-runtime',
            'dependencies',
            'node',
            'node_modules',
            'playwright'
        ),
    ].filter(Boolean);
    for (const candidate of candidates) {
        try {
            const loaded = require(candidate);
            if (loaded?.chromium) return loaded;
        } catch {
            // Try the next project-local or bundled runtime.
        }
    }
    throw new Error('Playwright is unavailable.');
}

async function chromeExecutable() {
    for (const candidate of [
        process.env.RED_CHROME_EXECUTABLE,
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
    ].filter(Boolean)) {
        try {
            await access(candidate, fsConstants.X_OK);
            return candidate;
        } catch {
            // Try the next local browser.
        }
    }
    return undefined;
}

function check(condition, message) {
    if (!condition) throw new Error(message);
}

function fixture(mode) {
    return execFileSync(
        phpBinary,
        [path.join(scriptDirectory, 'other-content-browser-fixture.php'), mode],
        {encoding: 'utf8'}
    );
}

const viewports = [
    {name: 'desktop', width: 1440, height: 1000},
    {name: 'mobile', width: 390, height: 844},
];
const expectedNormal = "<article data-source=\"dedicated\">\n  <iframe srcdoc=\"<p>Dedicated</p>\"></iframe>\n</article>";
const expectedShort = "<section data-source=\"listing\">\n  <template><x-card>Listing Ω</x-card></template>\n</section>";
const expectedLong = expectedNormal;
const advancedAppend = "\n<!-- exact browser bytes Ω -->";
const css = await readFile(path.join(projectRoot, 'admin/assets/css/cp.css'), 'utf8');
const browserScript = path.join(projectRoot, 'admin/assets/js/other-form.js');
const {chromium} = loadPlaywright();
const browser = await chromium.launch({
    headless: true,
    executablePath: await chromeExecutable(),
});

await mkdir(outputDirectory, {recursive: true});
let assertions = 0;
try {
    for (const viewport of viewports) {
        for (const mode of ['normal', 'mismatch']) {
            const context = await browser.newContext({viewport});
            const page = await context.newPage();
            const errors = [];
            page.on('console', (message) => {
                if (message.type() === 'error') errors.push(message.text());
            });
            page.on('pageerror', (error) => errors.push(error.message));
            await page.setContent(fixture(mode), {waitUntil: 'domcontentloaded'});
            await page.addStyleTag({content: css});
            await page.addScriptTag({content: `
                window.jQuery = function () {
                    return {serialize: function () { return ''; }};
                };
                window.jQuery.ajax = function () {};
            `});
            await page.addScriptTag({path: browserScript});

            const form = page.locator('form[data-red-other-form]');
            check(await form.count() === 1, `${mode} ${viewport.name} renders one Other form`);
            assertions++;
            check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `${mode} ${viewport.name} has no horizontal overflow`);
            assertions++;

            if (mode === 'normal') {
                const source = page.locator('[data-other-html-source]');
                check(await source.inputValue() === expectedNormal, `${viewport.name} normal source is byte-identical`);
                assertions++;
                await source.evaluate((element, append) => {
                    element.value += append;
                    element.dispatchEvent(new Event('input', {bubbles: true}));
                }, advancedAppend);
                await page.screenshot({
                    path: path.join(outputDirectory, `${mode}-${viewport.name}.png`),
                    fullPage: true,
                });
                await page.evaluate(() => window.RedAdminOtherForm.submit(document.querySelector('[data-red-other-form]')));
                const payload = await page.locator('[data-other-content-base64]').inputValue();
                check(await page.locator('[data-other-content-action]').inputValue() === 'update', `${viewport.name} normal edit declares content update`);
                assertions++;
                check(Buffer.from(payload, 'base64').toString('utf8') === expectedNormal + advancedAppend, `${viewport.name} normal edit preserves exact advanced bytes`);
                assertions++;
            } else {
                const choices = page.locator('[data-other-reconcile-source]');
                const states = page.locator('.red-admin-other-reconciliation__choice textarea');
                check(await choices.count() === 2 && await states.count() === 2, `${viewport.name} mismatch shows two explicit choices and states`);
                assertions++;
                check(await states.nth(0).inputValue() === expectedShort && await states.nth(1).inputValue() === expectedLong, `${viewport.name} mismatch shows both exact stored sources`);
                assertions++;
                check(await page.locator('[data-other-html-source]').count() === 0, `${viewport.name} mismatch exposes no silently editable canonical source`);
                assertions++;
                await choices.nth(1).check();
                await page.screenshot({
                    path: path.join(outputDirectory, `${mode}-${viewport.name}.png`),
                    fullPage: true,
                });
                await page.evaluate(() => window.RedAdminOtherForm.submit(document.querySelector('[data-red-other-form]')));
                check(await page.locator('[data-other-content-action]').inputValue() === 'reconcile', `${viewport.name} dedicated-page choice declares reconciliation`);
                assertions++;
                check(await page.locator('[data-other-content-base64]').inputValue() === '', `${viewport.name} reconciliation carries no submitted replacement HTML`);
                assertions++;
            }

            check(errors.length === 0, `${mode} ${viewport.name} has no console or page errors: ${errors.join(' | ')}`);
            assertions++;
            await context.close();
        }
    }
} finally {
    await browser.close();
}

console.log(`Other content browser QA passed: ${assertions} assertions; screenshots: ${outputDirectory}`);
