'use strict';

const fs = require('fs');
const path = require('path');

const playwrightModule = process.env.RED_PLAYWRIGHT_MODULE || 'playwright';
const {chromium} = require(playwrightModule);

const baseUrl = process.env.RED_STORE_LITE_BASE_URL || '';
const evidenceDir = process.env.RED_STORE_LITE_EVIDENCE_DIR || '';
const username = process.env.RED_STORE_LITE_USERNAME || '';
const password = process.env.RED_STORE_LITE_PASSWORD || '';
const chromePath = process.env.RED_CHROME_BIN
    || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(baseUrl)
    || !path.isAbsolute(evidenceDir)
    || !username
    || !password
) {
    throw new Error('Store Lite browser QA environment is incomplete.');
}

fs.mkdirSync(evidenceDir, {recursive: true, mode: 0o700});

const report = {
    baseUrl,
    browser: 'Google Chrome',
    checks: [],
};

function check(condition, name, detail = '') {
    if (!condition) {
        throw new Error(`${name}${detail ? `: ${detail}` : ''}`);
    }
    report.checks.push({name, passed: true, detail});
}

async function login(page) {
    const response = await page.request.post(`${baseUrl}/bin/login.php`, {
        form: {
            username,
            password,
        },
    });
    check(response.status() === 200, 'login HTTP status is 200');
    check((await response.text()).trim() === 'yes', 'disposable administrator login succeeds');
}

async function openProducts(page, persistedDesktopChange) {
    await page.goto(`${baseUrl}/`, {waitUntil: 'networkidle'});
    await page.locator('#content_third a').click();
    await page.locator('#toolscontent').waitFor({state: 'visible'});
    const products = page.locator(
        '[data-addon-tool="redcms.store-lite/products"]:visible'
    );
    await products.waitFor({state: 'visible'});
    check(await products.count() === 1, 'Products add-on tool is visible once');
    check(
        await page.locator(
            '[data-addon-tool="redcms.store-lite/orders"]:visible'
        ).count() === 0,
        'undeclared administrator capability keeps Orders tool hidden'
    );
    await products.locator('a').click();
    const tool = page.locator(
        '.red-admin-addon-tool[data-addon-tool="redcms.store-lite/products"]:visible'
    );
    await tool.waitFor({state: 'visible'});
    await page.waitForTimeout(250);
    check(await tool.locator('[data-red-addon-admin-form-target]').count() === 2,
        'Products tool lists exactly two editable targets');
    const bananaTitle = persistedDesktopChange
        ? 'Banana bunch browser-verified'
        : 'Banana bunch';
    const bananaPrice = persistedDesktopChange
        ? 'USD 649 minor units'
        : 'USD 599 minor units';
    check(await tool.getByRole('heading', {
        name: bananaTitle,
        exact: true,
    }).count() === 1,
        'simple banana target is listed');
    check(await tool.getByRole('heading', {name: 'Classic T-shirt'}).count() === 1,
        'variable T-shirt target is listed');
    const targetFacts = (await tool.textContent()) || '';
    check(targetFacts.includes(bananaPrice),
        'simple product minor-unit price is explicit');
    check(targetFacts.includes('USD 2,499 minor units–USD 2,699 minor units'),
        'variable product price range is explicit');
    return tool;
}

async function runCase(browser, definition) {
    const context = await browser.newContext({
        viewport: definition.viewport,
        reducedMotion: 'reduce',
    });
    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    const failedRequests = [];
    const badResponses = [];
    page.on('console', (message) => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('requestfailed', (request) => {
        failedRequests.push({
            url: request.url(),
            error: request.failure() ? request.failure().errorText : 'unknown',
        });
    });
    page.on('response', (response) => {
        if (response.status() >= 400) {
            badResponses.push({url: response.url(), status: response.status()});
        }
    });

    await login(page);
    let tool = await openProducts(page, !definition.mutate);
    await tool.screenshot({
        path: path.join(evidenceDir, `${definition.name}-products.png`),
    });

    const toolOverflow = await tool.evaluate((element) => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
    }));
    check(toolOverflow.scrollWidth <= toolOverflow.clientWidth + 1,
        `${definition.name} product target list has no horizontal overflow`,
        JSON.stringify(toolOverflow));

    const bananaTarget = tool.locator('.red-admin-addon-tool__target').filter({
        hasText: 'Banana bunch',
    });
    await bananaTarget.getByRole('button', {name: 'Edit'}).click();
    let workspace = page.locator('[data-red-addon-admin-form-workspace]');
    await workspace.waitFor({state: 'visible'});
    const field = (key) => workspace.locator(
        `[data-red-addon-admin-form-field][data-field-key="${key}"] `
            + '[data-red-addon-admin-form-control]'
    );
    check(await field('id').inputValue() === 'banana-bunch',
        `${definition.name} editor loads the stable product ID`);
    check(await field('type').inputValue() === 'simple',
        `${definition.name} editor loads the simple product type`);
    check(await field('currency').inputValue() === 'USD',
        `${definition.name} editor loads the installation currency`);
    check(await field('sku').inputValue() === 'BANANA-BUNCH',
        `${definition.name} editor loads the SKU`);

    if (definition.mutate) {
        check(await field('title').inputValue() === 'Banana bunch',
            'desktop editor loads the original title');
        check(await field('price-minor').inputValue() === '599',
            'desktop editor loads the original minor-unit price');
        check(await field('stock').inputValue() === '40',
            'desktop editor loads the original stock');
        await field('title').fill('Banana bunch browser-verified');
        await field('price-minor').fill('649');
        await field('stock').fill('39');
        await workspace.getByRole('button', {name: 'Save changes'}).click();
        workspace = page.locator('[data-red-addon-admin-form-workspace]');
        await workspace.getByText('Changes saved.').waitFor({state: 'visible'});
        check(await workspace.locator(
            '[data-field-key="title"] [data-red-addon-admin-form-control]'
        ).inputValue() === 'Banana bunch browser-verified',
        'desktop Save reloads the persisted title');
        check(await workspace.locator(
            '[data-field-key="price-minor"] [data-red-addon-admin-form-control]'
        ).inputValue() === '649',
        'desktop Save reloads the persisted minor-unit price');
        check(await workspace.locator(
            '[data-field-key="stock"] [data-red-addon-admin-form-control]'
        ).inputValue() === '39',
        'desktop Save reloads the persisted stock');
    } else {
        check(await field('title').inputValue() === 'Banana bunch browser-verified',
            'mobile editor observes the persisted desktop title');
        check(await field('price-minor').inputValue() === '649',
            'mobile editor observes the persisted desktop price');
        check(await field('stock').inputValue() === '39',
            'mobile editor observes the persisted desktop stock');
    }

    await workspace.screenshot({
        path: path.join(evidenceDir, `${definition.name}-editor.png`),
    });
    const workspaceOverflow = await workspace.evaluate((element) => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
    }));
    check(workspaceOverflow.scrollWidth <= workspaceOverflow.clientWidth + 1,
        `${definition.name} editor has no horizontal overflow`,
        JSON.stringify(workspaceOverflow));

    check(consoleErrors.length === 0,
        `${definition.name} console has no errors`,
        JSON.stringify(consoleErrors));
    check(pageErrors.length === 0,
        `${definition.name} page has no uncaught errors`,
        JSON.stringify(pageErrors));
    check(failedRequests.length === 0,
        `${definition.name} network has no failed requests`,
        JSON.stringify(failedRequests));
    check(badResponses.length === 0,
        `${definition.name} network has no HTTP error responses`,
        JSON.stringify(badResponses));

    await context.close();
}

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: chromePath,
    });
    try {
        await runCase(browser, {
            name: 'desktop',
            viewport: {width: 1280, height: 900},
            mutate: true,
        });
        await runCase(browser, {
            name: 'mobile',
            viewport: {width: 390, height: 844},
            mutate: false,
        });
    } finally {
        await browser.close();
    }
    report.summary = {
        passed: report.checks.length,
        failed: 0,
    };
    fs.writeFileSync(
        path.join(evidenceDir, 'report.json'),
        `${JSON.stringify(report, null, 2)}\n`,
        {mode: 0o600}
    );
    process.stdout.write(
        `Store Lite browser rehearsal passed ${report.checks.length} checks.\n`
    );
})().catch((error) => {
    report.summary = {passed: report.checks.length, failed: 1};
    report.failure = error.stack || error.message;
    fs.writeFileSync(
        path.join(evidenceDir, 'report.json'),
        `${JSON.stringify(report, null, 2)}\n`,
        {mode: 0o600}
    );
    process.stderr.write(`${error.stack || error.message}\n`);
    process.exit(1);
});
