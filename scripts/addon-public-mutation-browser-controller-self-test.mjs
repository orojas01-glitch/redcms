#!/usr/bin/env node

import {access} from 'node:fs/promises';
import {constants as fsConstants} from 'node:fs';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
import os from 'node:os';
import path from 'node:path';

const require = createRequire(import.meta.url);
const projectRoot = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const controllerPath = path.join(projectRoot, 'js', 'public-addon-mutation.js');
const csrfToken = 'a'.repeat(64);
const idempotencyKey = 'b'.repeat(64);
const actionPath = '/addons/redcms/controller-fixture/cart-intent';
const origin = 'http://controller.test';
let assertions = 0;

function assert(condition, message) {
    assertions += 1;
    if (!condition) {
        throw new Error(`Assertion failed: ${message}`);
    }
}

function loadPlaywright() {
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
        } catch (error) {
            // Try the next configured local runtime.
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
        } catch (error) {
            // Try the next approved local browser.
        }
    }
    return undefined;
}

function formMarkup(action = actionPath, csrf = csrfToken) {
    return `<!doctype html><html><body>
        <form data-red-addon-public-mutation-form
            data-red-csrf-header="X-RED-CMS-CSRF"
            data-red-csrf-token="${csrf}"
            data-red-idempotency-header="Idempotency-Key"
            data-red-idempotency-key="${idempotencyKey}"
            action="${action}" method="post"
            enctype="application/x-www-form-urlencoded">
            <input type="hidden" name="product" value="studio~shirt">
            <label for="quantity">Quantity</label>
            <input id="quantity" type="number" name="quantity" value="2">
            <label for="variant">Options</label>
            <select id="variant" name="variant">
                <option value="shirt-small">Small</option>
                <option value="shirt-medium" selected>Medium</option>
            </select>
            <button type="submit">Add to cart</button>
            <p data-red-addon-public-mutation-status role="status"></p>
        </form>
    </body></html>`;
}

function richFormMarkup() {
    return `<!doctype html><html><body>
        <form data-red-addon-public-mutation-form
            data-red-csrf-header="X-RED-CMS-CSRF"
            data-red-csrf-token="${csrfToken}"
            data-red-idempotency-header="Idempotency-Key"
            data-red-idempotency-key="${idempotencyKey}"
            action="${actionPath}" method="post"
            enctype="application/x-www-form-urlencoded">
            <label for="contact-name">Name</label>
            <input id="contact-name" type="text" name="contact-name"
                value="Ana María" maxlength="120" required>
            <label for="contact-email">Email</label>
            <input id="contact-email" type="email" name="contact-email"
                value="ana@example.com" maxlength="254" required>
            <div data-red-addon-public-mutation-field="contact-phone"
                data-red-required-when-field="response-method"
                data-red-required-when-equals="onsite">
                <label for="contact-phone">Phone</label>
                <input id="contact-phone" type="tel" name="contact-phone"
                    value="" maxlength="32">
            </div>
            <div data-red-addon-public-mutation-field="location-instructions"
                data-red-visible-when-field="response-method"
                data-red-visible-when-equals="onsite">
                <label for="location-instructions">Instructions</label>
                <textarea id="location-instructions"
                    name="location-instructions" maxlength="500"></textarea>
            </div>
            <label for="response-method">Response method</label>
            <select id="response-method" name="response-method">
                <option value="remote" selected>Remote</option>
                <option value="onsite">On site</option>
            </select>
            <button type="submit">Submit response</button>
            <p data-red-addon-public-mutation-status role="status"></p>
        </form>
    </body></html>`;
}

function jsonResponse(status, body) {
    return {
        status,
        headers: {
            'Content-Type': 'application/json; charset=UTF-8',
            'Cache-Control': 'no-store',
            'X-Content-Type-Options': 'nosniff',
            'Content-Length': String(Buffer.byteLength(body)),
        },
        body,
    };
}

async function fixturePage(browser, viewport, endpointHandler, markup) {
    const context = await browser.newContext({viewport});
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    await page.route(`${origin}/**`, async (route) => {
        const request = route.request();
        if (new URL(request.url()).pathname === actionPath) {
            await endpointHandler(route, request);
            return;
        }
        await route.fulfill({
            status: 200,
            contentType: 'text/html; charset=UTF-8',
            body: markup,
        });
    });
    await page.goto(`${origin}/`, {waitUntil: 'domcontentloaded'});
    await page.addScriptTag({path: controllerPath});
    await page.locator('form').waitFor();
    return {context, page, pageErrors};
}

const playwright = loadPlaywright();
const browser = await playwright.chromium.launch({
    headless: true,
    executablePath: await chromeExecutable(),
});

try {
    const acceptedRequests = [];
    const accepted = await fixturePage(
        browser,
        {width: 1440, height: 1000},
        async (route, request) => {
            acceptedRequests.push({
                body: request.postData(),
                headers: request.headers(),
            });
            await route.fulfill(jsonResponse(
                200,
                '{"ok":true,"outcome":"accepted"}'
            ));
        },
        formMarkup()
    );
    const acceptedForm = accepted.page.locator('form');
    assert(
        await acceptedForm.getAttribute(
            'data-red-addon-public-mutation-controller'
        ) === 'ready',
        'desktop form initializes one ready controller'
    );
    assert(
        await acceptedForm.getAttribute('data-red-csrf-token') === null
            && await acceptedForm.getAttribute('data-red-idempotency-key')
                === null,
        'opaque evidence is removed from DOM attributes after initialization'
    );
    const acceptedReload = accepted.page.waitForNavigation({
        waitUntil: 'domcontentloaded',
    });
    await accepted.page.getByRole('button', {name: 'Add to cart'}).click();
    await accepted.page.getByText('Update completed.', {exact: true}).waitFor();
    assert(acceptedRequests.length === 1, 'accepted form sends one request');
    assert(
        acceptedRequests[0].body
            === 'product=studio%7Eshirt&quantity=2&variant=shirt-medium',
        'browser sends canonical declared fields in DOM order'
    );
    assert(
        acceptedRequests[0].headers['x-red-cms-csrf'] === csrfToken
            && acceptedRequests[0].headers['idempotency-key']
                === idempotencyKey
            && acceptedRequests[0].headers['content-type']
                === 'application/x-www-form-urlencoded'
            && acceptedRequests[0].headers.accept === 'application/json',
        'browser sends only the fixed evidence and content headers'
    );
    assert(
        await accepted.page.getByRole('button', {name: 'Add to cart'}).isDisabled()
            && await accepted.page.locator('[name="quantity"]').isDisabled()
            && await accepted.page.locator('[name="variant"]').isDisabled(),
        'accepted command remains frozen and cannot reuse its key with new values'
    );
    await acceptedReload;
    assert(
        accepted.page.url() === `${origin}/`,
        'accepted command refreshes the same page after announcing completion'
    );
    assert(accepted.pageErrors.length === 0, 'desktop success has no page errors');
    await accepted.context.close();

    const retryRequests = [];
    const retry = await fixturePage(
        browser,
        {width: 390, height: 844},
        async (route, request) => {
            retryRequests.push(request.postData());
            if (retryRequests.length === 1) {
                await route.abort('failed');
                return;
            }
            await route.fulfill(jsonResponse(
                200,
                '{"ok":true,"outcome":"unchanged"}'
            ));
        },
        formMarkup()
    );
    await retry.page.getByRole('button', {name: 'Add to cart'}).click();
    await retry.page.getByText(
        'Could not complete this update. Try again.',
        {exact: true}
    ).waitFor();
    assert(
        !await retry.page.getByRole('button', {name: 'Add to cart'}).isDisabled()
            && await retry.page.locator('[name="quantity"]').isDisabled(),
        'mobile transient failure permits retry but keeps command fields frozen'
    );
    const unchangedReload = retry.page.waitForNavigation({
        waitUntil: 'domcontentloaded',
    });
    await retry.page.getByRole('button', {name: 'Add to cart'}).click();
    await retry.page.getByText(
        'No changes were needed.',
        {exact: true}
    ).waitFor();
    assert(
        retryRequests.length === 2 && retryRequests[0] === retryRequests[1],
        'retry reuses the exact original command body with the same evidence'
    );
    assert(
        await retry.page.getByRole('button', {name: 'Add to cart'}).isDisabled(),
        'successful retry closes the form command'
    );
    await unchangedReload;
    assert(
        retry.page.url() === `${origin}/`,
        'unchanged command refreshes the same page after announcing completion'
    );
    assert(retry.pageErrors.length === 0, 'mobile retry has no page errors');
    await retry.context.close();

    let conflictRequests = 0;
    const conflict = await fixturePage(
        browser,
        {width: 1440, height: 1000},
        async (route) => {
            conflictRequests += 1;
            await route.fulfill(jsonResponse(
                409,
                '{"ok":false,"reason":"request_conflict"}'
            ));
        },
        formMarkup()
    );
    await conflict.page.getByRole('button', {name: 'Add to cart'}).click();
    await conflict.page.getByText(
        'Could not complete this update. Refresh the page.',
        {exact: true}
    ).waitFor();
    assert(conflictRequests === 1, 'closed refusal sends one request');
    assert(
        await conflict.page.getByRole('button', {name: 'Add to cart'}).isDisabled(),
        'request conflict cannot resubmit the consumed command'
    );
    assert(
        !(await conflict.page.locator('[role="status"]').textContent())
            .includes(idempotencyKey),
        'generic refusal status discloses no opaque evidence'
    );
    assert(conflict.pageErrors.length === 0, 'conflict case has no page errors');
    await conflict.context.close();

    const richRequests = [];
    const rich = await fixturePage(
        browser,
        {width: 390, height: 844},
        async (route, request) => {
            richRequests.push(request.postData());
            await route.fulfill(jsonResponse(
                409,
                '{"ok":false,"reason":"request_conflict"}'
            ));
        },
        richFormMarkup()
    );
    const phone = rich.page.locator('[name="contact-phone"]');
    const instructions = rich.page.locator('[name="location-instructions"]');
    assert(
        !await phone.evaluate((control) => control.required)
            && !await instructions.isVisible(),
        'remote rich form initializes optional phone and hidden location facts'
    );
    await rich.page.selectOption('[name="response-method"]', 'onsite');
    assert(
        await phone.evaluate((control) => control.required)
            && await instructions.isVisible(),
        'declared select condition updates required and visible controls'
    );
    await instructions.fill('Temporary note');
    await rich.page.selectOption('[name="response-method"]', 'remote');
    assert(
        !await instructions.isVisible()
            && await instructions.inputValue() === '',
        'a newly hidden conditional value is cleared before command capture'
    );
    await rich.page.selectOption('[name="response-method"]', 'onsite');
    await phone.fill('+1 202-555-0199');
    await rich.page.getByRole('button', {name: 'Submit response'}).click();
    await rich.page.getByText(
        'Could not complete this update. Refresh the page.',
        {exact: true}
    ).waitFor();
    assert(
        richRequests.length === 1
            && richRequests[0]
                === 'contact-name=Ana+Mar%C3%ADa'
                    + '&contact-email=ana%40example.com'
                    + '&contact-phone=%2B1+202-555-0199'
                    + '&location-instructions='
                    + '&response-method=onsite',
        'rich values use canonical URLSearchParams bytes including explicit empty strings'
    );
    assert(rich.pageErrors.length === 0, 'rich field case has no page errors');
    await rich.context.close();

    let invalidRequests = 0;
    const invalid = await fixturePage(
        browser,
        {width: 390, height: 844},
        async (route) => {
            invalidRequests += 1;
            await route.abort('blockedbyclient');
        },
        formMarkup('https://foreign.example/add-to-cart', 'invalid-token')
    );
    assert(
        await invalid.page.locator('form').getAttribute(
            'data-red-addon-public-mutation-controller'
        ) === 'unavailable',
        'foreign action and malformed evidence fail controller configuration'
    );
    await invalid.page.getByRole('button', {name: 'Add to cart'}).click();
    await invalid.page.getByText(
        'This action is unavailable. Refresh the page.',
        {exact: true}
    ).waitFor();
    assert(invalidRequests === 0, 'invalid configuration sends no request');
    assert(
        await invalid.page.getByRole('button', {name: 'Add to cart'}).isDisabled()
            && await invalid.page.locator('[name="quantity"]').isDisabled(),
        'invalid configuration freezes the complete command'
    );
    assert(invalid.pageErrors.length === 0, 'invalid case has no page errors');
    await invalid.context.close();
} finally {
    await browser.close();
}

console.log(
    `Public mutation browser controller self-test passed (${assertions} assertions).`
);
