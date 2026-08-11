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
const mutationPaths = {
    add: '/addons/redcms/store-lite/cart-intent',
    quantity: '/addons/redcms/store-lite/cart-line-quantity',
    remove: '/addons/redcms/store-lite/cart-line-remove',
};
const mutationUrls = Object.fromEntries(
    Object.entries(mutationPaths).map(([key, value]) => [key, `${baseUrl}${value}`])
);

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
    package: 'redcms.store-lite@0.1.24',
    checks: [],
};

function check(condition, name, detail = '') {
    if (!condition) {
        throw new Error(`${name}${detail ? `: ${detail}` : ''}`);
    }
    report.checks.push({name, passed: true, detail});
}

async function verifyCart(page, definition, state) {
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
    if (state === 'empty') {
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
        const updated = state === 'updated';
        const expectedQuantity = updated
            ? definition.updatedQuantity
            : definition.quantity;
        const expectedSummary = updated
            ? definition.updatedCartSummary
            : definition.cartSummary;
        const expectedLineTotal = updated
            ? definition.updatedLineTotal
            : definition.lineTotal;
        check(content.includes(expectedSummary)
            && content.includes(definition.productTitle)
            && content.includes(`Quantity${expectedQuantity}`)
            && content.includes(`Unit price${definition.unitPrice}`)
            && content.includes(`Line total${expectedLineTotal}`),
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
            `${definition.name}-cart-${state}.png`
        ),
    });
}

async function cartControls(page, definition, expectedQuantity) {
    const cart = page.locator(
        '[data-red-addon-component="redcms.store-lite/cart"]'
    );
    const line = cart.locator(
        '.red-addon-component__collection[aria-label="Cart items"] li'
    );
    const actions = line.locator(
        '.red-addon-component__collection-actions'
    );
    const quantityForm = actions.locator(
        `form[action="${mutationPaths.quantity}"]`
    );
    const removeForm = actions.locator(
        `form[action="${mutationPaths.remove}"]`
    );
    await quantityForm.waitFor({state: 'visible'});
    await removeForm.waitFor({state: 'visible'});
    check(await actions.locator('form').count() === 2,
        `${definition.name} Cart line exposes exactly two core-owned controls`);
    check(await quantityForm.getByLabel('Quantity').count() === 1
        && await quantityForm.getByRole('button', {
            name: 'Update quantity',
        }).count() === 1,
    `${definition.name} quantity form has accessible core controls`);
    check(await removeForm.getByRole('button', {
        name: 'Remove item',
    }).count() === 1,
    `${definition.name} removal form has an accessible core control`);
    check(await quantityForm.getByLabel('Quantity').inputValue()
        === String(expectedQuantity),
    `${definition.name} quantity control reflects current server state`);
    const quantityLine = await quantityForm.locator('[name="line"]').inputValue();
    const removeLine = await removeForm.locator('[name="line"]').inputValue();
    check(/^line-[a-f0-9]{64}$/.test(quantityLine)
        && removeLine === quantityLine,
    `${definition.name} row controls share one bounded server-derived line handle`);
    for (const form of [quantityForm, removeForm]) {
        check(await form.getAttribute('method') === 'post'
            && await form.getAttribute('enctype')
                === 'application/x-www-form-urlencoded',
        `${definition.name} row control uses the declared POST encoding`);
        const status = form.locator('[data-red-addon-public-mutation-status]');
        check(await status.getAttribute('role') === 'status'
            && await status.getAttribute('aria-live') === 'polite',
        `${definition.name} row result uses a polite status region`);
    }
    const overflow = await actions.evaluate((element) => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
    }));
    check(overflow.scrollWidth <= overflow.clientWidth + 1,
        `${definition.name} Cart row controls have no horizontal overflow`,
        JSON.stringify(overflow));
    return {quantityForm, removeForm, lineHandle: quantityLine};
}

async function submitAndRefresh(page, definition, options) {
    let requestRecord = null;
    const requestPromise = page.waitForRequest((request) => {
        if (request.url() !== options.url || request.method() !== 'POST') {
            return false;
        }
        requestRecord = {
            body: request.postData(),
            headers: request.headers(),
        };
        return true;
    });
    const responsePromise = page.waitForResponse(
        (response) => response.url() === options.url
            && response.request().method() === 'POST'
    );
    const refreshPromise = page.waitForNavigation({waitUntil: 'networkidle'});
    await options.submit();
    await requestPromise;
    const response = await responsePromise;
    check(response.status() === 200
        && await response.text() === '{"ok":true,"outcome":"accepted"}',
    `${definition.name} ${options.name} reaches the real accepted endpoint`);
    await options.form.getByText('Update completed.', {exact: true}).waitFor({
        state: 'visible',
    });
    check(await options.form.getAttribute(
        'data-red-addon-public-mutation-frozen'
    ) === 'true'
        && await options.form.getByRole('button', {
            name: options.button,
        }).isDisabled(),
    `${definition.name} ${options.name} freezes its consumed command`);
    check(requestRecord
        && /^[a-z][a-z0-9-]*=/.test(requestRecord.body || '')
        && /^[a-f0-9]{64}$/.test(
            requestRecord.headers['x-red-cms-csrf'] || ''
        )
        && /^[a-f0-9]{64}$/.test(
            requestRecord.headers['idempotency-key'] || ''
        ),
    `${definition.name} ${options.name} sends canonical fields and core evidence`);
    await refreshPromise;
    check(page.url() === `${baseUrl}/`,
        `${definition.name} ${options.name} refreshes only the current page`);
    return requestRecord;
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
    await verifyCart(page, definition, 'empty');

    const form = product.locator('[data-red-addon-public-mutation-form]');
    await form.waitFor({state: 'visible'});
    check(await form.getAttribute('action') === mutationPaths.add,
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
    const addRequest = await submitAndRefresh(page, definition, {
        name: 'Add to cart',
        form,
        button: 'Add to cart',
        url: mutationUrls.add,
        submit: async () => {
            if (definition.keyboard) {
                await form.getByLabel('Quantity').press('Enter');
            } else {
                await form.getByRole('button', {name: 'Add to cart'}).click();
            }
        },
    });
    await verifyCart(page, definition, 'added');

    const csrf = addRequest.headers['x-red-cms-csrf'];
    const idempotency = addRequest.headers['idempotency-key'];
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
        Origin: baseUrl,
        'X-RED-CMS-CSRF': csrf,
        'Idempotency-Key': idempotency,
    };
    const retry = await context.request.post(mutationUrls.add, {
        headers,
        data: addRequest.body,
    });
    check(retry.status() === 200
        && await retry.text() === '{"ok":true,"outcome":"accepted"}',
    `${definition.name} exact network retry replays the accepted result`);

    const conflictBody = new URLSearchParams(addRequest.body);
    conflictBody.set('quantity', String(definition.quantity + 1));
    const conflict = await context.request.post(mutationUrls.add, {
        headers,
        data: conflictBody.toString(),
    });
    check(conflict.status() === 409
        && await conflict.text()
            === '{"ok":false,"reason":"request_conflict"}',
    `${definition.name} changed command with the same key fails closed`);

    const invalidBody = new URLSearchParams(addRequest.body);
    invalidBody.set('quantity', '0');
    const invalid = await context.request.post(mutationUrls.add, {
        headers,
        data: invalidBody.toString(),
    });
    check(invalid.status() === 400
        && await invalid.text() === '{"ok":false,"reason":"invalid_request"}',
    `${definition.name} out-of-contract quantity is refused without disclosure`);

    let controls = await cartControls(page, definition, definition.quantity);
    const originalLineHandle = controls.lineHandle;
    await controls.quantityForm.getByLabel('Quantity').fill(
        String(definition.updatedQuantity)
    );
    await submitAndRefresh(page, definition, {
        name: 'quantity update',
        form: controls.quantityForm,
        button: 'Update quantity',
        url: mutationUrls.quantity,
        submit: async () => {
            if (definition.keyboard) {
                await controls.quantityForm.getByLabel('Quantity').press('Enter');
            } else {
                await controls.quantityForm.getByRole('button', {
                    name: 'Update quantity',
                }).click();
            }
        },
    });
    await verifyCart(page, definition, 'updated');
    controls = await cartControls(
        page,
        definition,
        definition.updatedQuantity
    );
    check(controls.lineHandle === originalLineHandle,
        `${definition.name} quantity update preserves the current line identity`);
    await submitAndRefresh(page, definition, {
        name: 'line removal',
        form: controls.removeForm,
        button: 'Remove item',
        url: mutationUrls.remove,
        submit: async () => {
            await controls.removeForm.getByRole('button', {
                name: 'Remove item',
            }).click();
        },
    });
    await verifyCart(page, definition, 'empty');

    const cookiesAfter = await context.cookies(baseUrl);
    const subjectsAfter = cookiesAfter.filter(
        (cookie) => cookie.name === 'redcms_public_mutation_subject'
    );
    check(subjectsAfter.length === 1
        && subjectsAfter[0].value === subject.value,
    `${definition.name} add, retry, update, and removal reuse one subject`);
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
            updatedQuantity: 3,
            keyboard: false,
            productTitle: 'Banana bunch browser-verified',
            variantLabel: '',
            cartSummary: '2 items · USD 12.98',
            updatedCartSummary: '3 items · USD 19.47',
            unitPrice: 'USD 6.49',
            lineTotal: 'USD 12.98',
            updatedLineTotal: 'USD 19.47',
        });
        await runCase(browser, {
            name: 'mobile',
            viewport: {width: 390, height: 844},
            quantity: 1,
            updatedQuantity: 2,
            keyboard: true,
            productTitle: 'Classic T-shirt',
            variantLabel: 'Size: Small · Color: Black',
            cartSummary: '1 item · USD 24.99',
            updatedCartSummary: '2 items · USD 49.98',
            unitPrice: 'USD 24.99',
            lineTotal: 'USD 24.99',
            updatedLineTotal: 'USD 49.98',
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
