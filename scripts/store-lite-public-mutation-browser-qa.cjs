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
const mutationPath = '/addons/redcms/store-lite/cart-intent';
const mutationUrl = `${baseUrl}${mutationPath}`;

if (!/^https:\/\/localhost:\d+$/.test(baseUrl)
    || !path.isAbsolute(evidenceDir)
    || !fs.existsSync(evidenceDir)
    || !username
    || !password
) {
    throw new Error('Store Lite public-mutation browser QA environment is incomplete.');
}

const report = {
    baseUrl,
    browser: 'Google Chrome',
    package: 'redcms.store-lite@0.1.19',
    checks: [],
};

function check(condition, name, detail = '') {
    if (!condition) {
        throw new Error(`${name}${detail ? `: ${detail}` : ''}`);
    }
    report.checks.push({name, passed: true, detail});
}

async function verifyCart(page, definition, populated) {
    const cart = page.locator(
        '[data-red-addon-component="redcms.store-lite/cart"]'
    );
    await cart.waitFor({state: 'visible'});
    check(await cart.getByRole('heading', {
        name: 'Shopping cart',
        exact: true,
    }).count() === 1,
    `${definition.name} renders the placed Cart component`);
    const content = await cart.textContent() || '';
    if (!populated) {
        check(content.includes('Your cart is empty.')
            && content.includes('Items')
            && content.includes('0')
            && content.includes('Total')
            && content.includes('USD 0.00'),
        `${definition.name} starts with an empty subject-owned Cart`);
        check(await cart.locator(
            '.red-addon-component__collection'
        ).count() === 0,
        `${definition.name} empty Cart has no line collection`);
    } else {
        check(content.includes(definition.cartSummary)
            && content.includes(definition.productTitle)
            && content.includes(`Quantity${definition.quantity}`)
            && content.includes(`Unit price${definition.unitPrice}`)
            && content.includes(`Line total${definition.lineTotal}`),
        `${definition.name} Cart renders server-derived line and total facts`);
        if (definition.variantLabel) {
            check(content.includes(`Options${definition.variantLabel}`),
                `${definition.name} Cart renders the selected variant labels`);
        }
        const collection = cart.locator(
            '.red-addon-component__collection[aria-label="Cart items"]'
        );
        check(await collection.count() === 1
            && await collection.locator('li').count() === 1,
        `${definition.name} Cart renders exactly one semantic line item`);
    }
    const overflow = await cart.evaluate((element) => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
    }));
    check(overflow.scrollWidth <= overflow.clientWidth + 1,
        `${definition.name} Cart has no horizontal overflow`,
        JSON.stringify(overflow));
    await cart.screenshot({
        path: path.join(
            evidenceDir,
            `${definition.name}-cart-${populated ? 'populated' : 'empty'}.png`
        ),
    });
}

async function runCase(browser, definition) {
    const context = await browser.newContext({
        viewport: definition.viewport,
        reducedMotion: 'reduce',
        ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    const failedRequests = [];
    const unexpectedBadResponses = [];
    let mutationRequest = null;

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
    page.on('request', (request) => {
        if (request.url() === mutationUrl && request.method() === 'POST') {
            mutationRequest = {
                body: request.postData(),
                headers: request.headers(),
            };
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 400) {
            unexpectedBadResponses.push({
                url: response.url(),
                status: response.status(),
            });
        }
    });

    const homepage = await page.goto(`${baseUrl}/`, {waitUntil: 'networkidle'});
    check(homepage && homepage.status() === 200,
        `${definition.name} homepage returns HTTP 200`);

    const product = page.locator(
        '[data-red-addon-component="redcms.store-lite/product"]'
    ).filter({
        has: page.getByRole('heading', {
            name: definition.productTitle,
            exact: true,
        }),
    });
    await product.waitFor({state: 'visible'});
    check(await product.getByRole('heading', {
        name: definition.productTitle,
        exact: true,
    }).count() === 1,
    `${definition.name} renders the package-owned published product`);
    await verifyCart(page, definition, false);

    const form = product.locator('[data-red-addon-public-mutation-form]');
    await form.waitFor({state: 'visible'});
    check(await form.getAttribute('action') === mutationPath,
        `${definition.name} form targets the declared Store Lite route`);
    check(await form.getAttribute('method') === 'post',
        `${definition.name} form uses POST`);
    check(await form.getAttribute('enctype')
        === 'application/x-www-form-urlencoded',
    `${definition.name} form uses the declared encoding`);
    check(await form.getByLabel('Quantity').count() === 1,
        `${definition.name} quantity control has an accessible label`);
    check(await form.getByRole('button', {name: 'Add to cart'}).count() === 1,
        `${definition.name} Add-to-cart control has an accessible name`);
    if (definition.variantLabel) {
        const options = form.getByLabel('Options');
        check(await options.count() === 1,
            `${definition.name} variant control has an accessible label`);
        await options.selectOption({label: definition.variantLabel});
        check(await options.locator('option:checked').textContent()
            === definition.variantLabel,
        `${definition.name} selects the exact visible product variant`);
    } else {
        check(await form.getByLabel('Options').count() === 0,
            `${definition.name} simple product has no variant control`);
    }
    const status = form.locator('[data-red-addon-public-mutation-status]');
    check(await status.getAttribute('role') === 'status'
        && await status.getAttribute('aria-live') === 'polite',
    `${definition.name} result is announced through a polite status region`);

    const overflow = await product.evaluate((element) => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
    }));
    check(overflow.scrollWidth <= overflow.clientWidth + 1,
        `${definition.name} product and form have no horizontal overflow`,
        JSON.stringify(overflow));
    await product.screenshot({
        path: path.join(evidenceDir, `${definition.name}-store-lite-form.png`),
    });

    const cookiesBefore = await context.cookies(baseUrl);
    const subjectsBefore = cookiesBefore.filter(
        (cookie) => cookie.name === 'redcms_public_mutation_subject'
    );
    check(subjectsBefore.length === 1,
        `${definition.name} receives exactly one anonymous subject cookie`);
    const subject = subjectsBefore[0];
    check(subject.secure === true
        && subject.httpOnly === true
        && subject.sameSite === 'Strict'
        && subject.path === '/',
    `${definition.name} subject cookie uses Secure HttpOnly Strict host scope`);
    check(subject.domain === 'localhost',
        `${definition.name} subject cookie does not declare a wider domain`,
        subject.domain);
    check(/^[a-f0-9]{64}$/.test(subject.value),
        `${definition.name} subject cookie is one bounded opaque token`);

    await form.getByLabel('Quantity').fill(String(definition.quantity));
    const acceptedResponsePromise = page.waitForResponse(
        (response) => response.url() === mutationUrl
            && response.request().method() === 'POST'
    );
    if (definition.keyboard) {
        await form.getByLabel('Quantity').press('Enter');
    } else {
        await form.getByRole('button', {name: 'Add to cart'}).click();
    }
    const acceptedResponse = await acceptedResponsePromise;
    check(acceptedResponse.status() === 200,
        `${definition.name} real endpoint accepts the cart mutation`);
    check((await acceptedResponse.text())
        === '{"ok":true,"outcome":"accepted"}',
    `${definition.name} receives the core-owned fixed accepted response`);
    await status.getByText('Added to cart.', {exact: true}).waitFor({
        state: 'visible',
    });
    check(await form.getAttribute('data-red-addon-public-mutation-frozen')
        === 'true',
    `${definition.name} controller freezes the completed command`);
    check(await form.getByRole('button', {name: 'Add to cart'}).isDisabled(),
        `${definition.name} controller disables duplicate submission`);
    check(mutationRequest
        && typeof mutationRequest.body === 'string'
        && /^[a-z][a-z0-9-]*=/.test(mutationRequest.body),
    `${definition.name} captures the canonical browser command for retry`);

    const csrf = mutationRequest.headers['x-red-cms-csrf'];
    const idempotency = mutationRequest.headers['idempotency-key'];
    check(/^[a-f0-9]{64}$/.test(csrf || '')
        && /^[a-f0-9]{64}$/.test(idempotency || ''),
    `${definition.name} browser sends core-issued CSRF and idempotency evidence`);
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
        Origin: baseUrl,
        'X-RED-CMS-CSRF': csrf,
        'Idempotency-Key': idempotency,
    };
    const retry = await context.request.post(mutationUrl, {
        headers,
        data: mutationRequest.body,
    });
    check(retry.status() === 200
        && await retry.text() === '{"ok":true,"outcome":"accepted"}',
    `${definition.name} exact network retry replays the accepted result`);

    const conflictBody = new URLSearchParams(mutationRequest.body);
    conflictBody.set('quantity', String(definition.quantity + 1));
    const conflict = await context.request.post(mutationUrl, {
        headers,
        data: conflictBody.toString(),
    });
    check(conflict.status() === 409
        && await conflict.text()
            === '{"ok":false,"reason":"request_conflict"}',
    `${definition.name} changed command with the same key fails closed`);

    const invalidBody = new URLSearchParams(mutationRequest.body);
    invalidBody.set('quantity', '0');
    const invalid = await context.request.post(mutationUrl, {
        headers,
        data: invalidBody.toString(),
    });
    check(invalid.status() === 400
        && await invalid.text() === '{"ok":false,"reason":"invalid_request"}',
    `${definition.name} out-of-contract quantity is refused without disclosure`);

    const cookiesAfter = await context.cookies(baseUrl);
    const subjectsAfter = cookiesAfter.filter(
        (cookie) => cookie.name === 'redcms_public_mutation_subject'
    );
    check(subjectsAfter.length === 1
        && subjectsAfter[0].value === subject.value,
    `${definition.name} mutation and retries do not rotate or duplicate the subject`);
    await page.reload({waitUntil: 'networkidle'});
    await verifyCart(page, definition, true);
    check(consoleErrors.length === 0,
        `${definition.name} console has no errors`,
        JSON.stringify(consoleErrors));
    check(pageErrors.length === 0,
        `${definition.name} page has no uncaught errors`,
        JSON.stringify(pageErrors));
    check(failedRequests.length === 0,
        `${definition.name} browser has no failed requests`,
        JSON.stringify(failedRequests));
    check(unexpectedBadResponses.length === 0,
        `${definition.name} rendered page has no HTTP error responses`,
        JSON.stringify(unexpectedBadResponses));

    await context.close();
}

async function prepareVariableProduct(browser) {
    const context = await browser.newContext({
        viewport: {width: 1280, height: 900},
        reducedMotion: 'reduce',
        ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();
    const login = await page.request.post(`${baseUrl}/bin/login.php`, {
        form: {username, password},
    });
    check(login.status() === 200 && (await login.text()).trim() === 'yes',
        'variable-product setup authenticates only the disposable administrator');

    await page.goto(`${baseUrl}/`, {waitUntil: 'networkidle'});
    await page.locator('#content_second a').click();
    const card = page.locator(
        '[data-red-addon-component-add]'
            + '[data-component-id="redcms.store-lite/product"]:visible'
    );
    await card.waitFor({state: 'visible'});
    await card.click();
    const create = page.locator(
        '[data-red-addon-component-create-workspace]:visible'
    );
    await create.waitFor({state: 'visible'});
    await create.locator('[name="Title"]').fill(
        'Store Lite variable product'
    );
    await create.locator('[name="componentValues[product-id]"]').fill(
        'classic-shirt'
    );
    await create.getByRole('button', {name: 'Create component'}).click();
    const placement = page.locator('[data-red-addon-placement-form]:visible');
    await placement.waitFor({state: 'visible'});
    await placement.locator('[name="TargetPageRecordID"]').selectOption('0');
    await placement.locator('[name="PagePosition"]').selectOption('1');
    await placement.locator('[name="PagePositionOrder"]').fill('91');
    await placement.getByRole('button', {name: 'Place component'}).click();
    await placement.getByText('Component placed and published.').waitFor({
        state: 'visible',
    });
    check(await placement.getAttribute('data-red-addon-placement-complete')
        === 'true',
    'variable-product setup places the T-shirt through the real core runner');
    await placement.screenshot({
        path: path.join(evidenceDir, 'variable-product-placement.png'),
    });
    await context.close();
}

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: chromePath,
    });
    try {
        await prepareVariableProduct(browser);
        await runCase(browser, {
            name: 'desktop',
            viewport: {width: 1280, height: 900},
            quantity: 2,
            keyboard: false,
            productTitle: 'Banana bunch browser-verified',
            variantLabel: '',
            cartSummary: '2 items · USD 12.98',
            unitPrice: 'USD 6.49',
            lineTotal: 'USD 12.98',
        });
        await runCase(browser, {
            name: 'mobile',
            viewport: {width: 390, height: 844},
            quantity: 1,
            keyboard: true,
            productTitle: 'Classic T-shirt',
            variantLabel: 'Size: Small · Color: Black',
            cartSummary: '1 item · USD 24.99',
            unitPrice: 'USD 24.99',
            lineTotal: 'USD 24.99',
        });
    } finally {
        await browser.close();
    }
    report.summary = {passed: report.checks.length, failed: 0};
    fs.writeFileSync(
        path.join(evidenceDir, 'public-mutation-report.json'),
        `${JSON.stringify(report, null, 2)}\n`,
        {mode: 0o600}
    );
    process.stdout.write(
        `Store Lite public-mutation browser QA passed ${report.checks.length} checks.\n`
    );
})().catch((error) => {
    report.summary = {passed: report.checks.length, failed: 1};
    report.failure = error.stack || error.message;
    fs.writeFileSync(
        path.join(evidenceDir, 'public-mutation-report.json'),
        `${JSON.stringify(report, null, 2)}\n`,
        {mode: 0o600}
    );
    process.stderr.write(`${error.stack || error.message}\n`);
    process.exit(1);
});
