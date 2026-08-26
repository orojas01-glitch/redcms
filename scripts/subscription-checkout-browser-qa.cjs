'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require(
  process.env.RED_PLAYWRIGHT_MODULE
    || '/Users/oscarrojas/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'
);

const baseUrl = process.env.RED_SUBSCRIPTION_BROWSER_BASE_URL
  || 'http://127.0.0.1:8056';
const chromePath = process.env.RED_CHROME_BIN
  || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const outputDir = process.env.RED_SUBSCRIPTION_BROWSER_OUTPUT
  || '/tmp/redcms-subscription-checkout-browser';
const checkoutUrl = 'https://checkout.stripe.com/c/pay/'
  + 'cs_test_SubscriptionBrowserRehearsal1234#synthetic-browser';

function body(url) {
  return JSON.stringify({
    ok: true,
    outcome: 'subscription_checkout_ready',
    checkoutUrl: url,
    navigationMode: 'location.assign'
  });
}

async function runCase(browser, name, viewport, responseUrl, expectedReady) {
  const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
  const page = await context.newPage();
  const consoleErrors = [];
  const failedRequests = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => consoleErrors.push(error.message));
  page.on('requestfailed', (request) => {
    failedRequests.push(`${request.method()} ${request.url()}`);
  });
  await page.route('**/addons/redcms/store-lite/subscription-intent', async (route) => {
    const payload = body(responseUrl);
    await route.fulfill({
      status: 200,
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'Cache-Control': 'no-store',
        'X-Content-Type-Options': 'nosniff',
        'Content-Length': String(Buffer.byteLength(payload))
      },
      body: payload
    });
  });
  const fixtureUrl = `${baseUrl}/scripts/subscription-checkout-browser-fixture.html`;
  await page.goto(fixtureUrl, { waitUntil: 'networkidle' });
  await page.getByRole('button', { name: 'Subscribe monthly' }).focus();
  await page.keyboard.press('Enter');
  await page.waitForTimeout(250);

  const state = await page.evaluate(() => ({
    url: window.location.href,
    checkoutUrl: document.documentElement.dataset.checkoutUrl || '',
    navigationPrevented:
      document.documentElement.dataset.navigationPrevented || '',
    status: document.querySelector(
      '[data-red-addon-public-mutation-status]'
    ).textContent,
    controller: document.querySelector('form').getAttribute(
      'data-red-addon-public-mutation-controller'
    ),
    frozen: document.querySelector('form').getAttribute(
      'data-red-addon-public-mutation-frozen'
    ),
    busy: document.querySelector('form').getAttribute('aria-busy'),
    disabled: document.querySelector('button').disabled,
    overflow: document.documentElement.scrollWidth > window.innerWidth
  }));

  if (consoleErrors.length || failedRequests.length) {
    throw new Error(`${name}: browser errors: ${JSON.stringify({consoleErrors, failedRequests})}`);
  }
  if (state.url !== fixtureUrl || state.overflow || state.controller !== 'ready') {
    throw new Error(`${name}: unexpected page state ${JSON.stringify(state)}`);
  }
  if (expectedReady) {
    if (state.checkoutUrl !== checkoutUrl
      || state.navigationPrevented !== 'true'
      || state.status !== 'Redirecting to secure checkout…'
      || state.frozen !== 'true'
      || state.busy !== 'false'
      || state.disabled !== true
    ) {
      throw new Error(`${name}: valid handoff failed ${JSON.stringify(state)}`);
    }
  } else if (state.checkoutUrl !== ''
    || state.navigationPrevented !== ''
    || state.status !== 'Could not complete this update. Refresh the page.'
  ) {
    throw new Error(`${name}: invalid handoff did not fail closed ${JSON.stringify(state)}`);
  }
  await page.screenshot({
    path: path.join(outputDir, `${name}.png`),
    fullPage: true
  });
  await context.close();
  return state;
}

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true, executablePath: chromePath });
  try {
    const desktop = await runCase(
      browser,
      'desktop-valid',
      { width: 1440, height: 1000 },
      checkoutUrl,
      true
    );
    const mobile = await runCase(
      browser,
      'mobile-valid',
      { width: 390, height: 844 },
      checkoutUrl,
      true
    );
    const refused = await runCase(
      browser,
      'desktop-foreign-origin-refused',
      { width: 1440, height: 1000 },
      checkoutUrl.replace('checkout.stripe.com', 'evil.example.test'),
      false
    );
    process.stdout.write(JSON.stringify({
      ok: true,
      desktop,
      mobile,
      refused,
      providerContact: false,
      browserNavigation: false,
      outputDir
    }, null, 2) + '\n');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  process.stderr.write(error.stack + '\n');
  process.exit(1);
});
