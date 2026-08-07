#!/usr/bin/env node

import { access, chmod, lstat, mkdir, readFile, writeFile } from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import { createHash } from 'node:crypto';
import { createRequire } from 'node:module';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 },
];

function parseArguments(argv) {
  const options = { baseUrl: '', outputDir: '' };
  const fields = new Map([
    ['--base-url', 'baseUrl'],
    ['--output-dir', 'outputDir'],
  ]);
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--help' || argument === '-h') {
      console.log('Usage: node scripts/public-mutation-deployment-browser-qa.mjs --base-url https://localhost:18443 --output-dir /tmp/evidence');
      process.exit(0);
    }
    if (!fields.has(argument) || !argv[index + 1]) {
      throw new Error(`Unknown or incomplete argument: ${argument}`);
    }
    options[fields.get(argument)] = argv[index + 1];
    index += 1;
  }
  if (options.baseUrl === '' || options.outputDir === '') {
    throw new Error('Base URL and output directory are required.');
  }
  const base = new URL(options.baseUrl);
  if (base.protocol !== 'https:' || base.pathname !== '/' || base.search !== '' || base.hash !== '') {
    throw new Error('Base URL must be one canonical HTTPS origin with no path, query, or fragment.');
  }
  options.baseUrl = base.href;
  options.origin = base.origin;
  options.outputDir = path.resolve(options.outputDir);
  return options;
}

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
  const errors = [];
  for (const candidate of candidates) {
    try {
      const loaded = require(candidate);
      if (loaded?.chromium) return loaded;
    } catch (error) {
      errors.push(`${candidate}: ${error.message}`);
    }
  }
  throw new Error(`Playwright is unavailable. ${errors.join(' | ')}`);
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
      // Try the next approved local browser.
    }
  }
  return undefined;
}

async function sha256File(filePath) {
  const bytes = await readFile(filePath);
  return createHash('sha256').update(bytes).digest('hex');
}

function hasOpaqueToken(value) {
  return /\b[a-f0-9]{64}\b/iu.test(String(value ?? ''))
    || String(value ?? '').includes('redcms_public_mutation_subject');
}

async function inspectViewport(browser, options, viewport) {
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    reducedMotion: 'reduce',
    viewport: { width: viewport.width, height: viewport.height },
  });
  const page = await context.newPage();
  const consoleErrors = [];
  const pageErrors = [];
  const requestFailures = [];
  const badResponses = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('requestfailed', (request) => {
    requestFailures.push({ url: request.url(), error: request.failure()?.errorText ?? 'request failed' });
  });
  page.on('response', (response) => {
    if (response.status() >= 400) {
      badResponses.push({ url: response.url(), status: response.status() });
    }
  });

  let response = null;
  let bodyText = '';
  let documentCookie = '';
  let responseHeaders = {};
  try {
    response = await page.goto(options.baseUrl, {
      waitUntil: 'domcontentloaded',
      timeout: 20_000,
    });
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => {});
    bodyText = await page.locator('body').innerText();
    documentCookie = await page.evaluate(() => document.cookie);
    responseHeaders = response?.headers() ?? {};
  } catch (error) {
    pageErrors.push(error.message);
  }

  const screenshotPath = path.join(options.outputDir, `${viewport.name}.png`);
  await page.screenshot({ path: screenshotPath, fullPage: true });
  const evidenceSHA256 = await sha256File(screenshotPath);
  const cookies = await context.cookies();
  const finalUrl = page.url();
  const httpsLoaded = finalUrl.startsWith(options.baseUrl)
    && new URL(finalUrl).protocol === 'https:'
    && (response?.status() ?? 0) === 200;
  const responseHeadersMatched = responseHeaders['cache-control'] === 'no-store'
    && responseHeaders['x-content-type-options'] === 'nosniff'
    && !Object.hasOwn(responseHeaders, 'set-cookie');
  const cookiePolicyMatched = cookies.length === 0
    && documentCookie === ''
    && !Object.hasOwn(responseHeaders, 'set-cookie');
  const networkErrors = requestFailures.length + badResponses.length;
  const result = {
    viewportWidth: viewport.width,
    viewportHeight: viewport.height,
    httpsLoaded,
    statusCode: response?.status() ?? 0,
    consoleErrors: consoleErrors.length,
    networkErrors,
    responseHeadersMatched,
    cookiePolicyMatched,
    tokenAbsentFromBody: !hasOpaqueToken(bodyText),
    evidenceSHA256,
    finalUrl,
    consoleErrorDetails: consoleErrors,
    pageErrorDetails: pageErrors,
    requestFailureDetails: requestFailures,
    badResponseDetails: badResponses,
    passed: httpsLoaded
      && (response?.status() ?? 0) === 200
      && consoleErrors.length === 0
      && pageErrors.length === 0
      && networkErrors === 0
      && responseHeadersMatched
      && cookiePolicyMatched
      && !hasOpaqueToken(bodyText),
  };
  await page.close();
  await context.close();
  return result;
}

async function main() {
  const options = parseArguments(process.argv.slice(2));
  await mkdir(options.outputDir, { recursive: true, mode: 0o700 });
  const stats = await lstat(options.outputDir);
  if (!stats.isDirectory() || stats.isSymbolicLink()) {
    throw new Error('The browser evidence directory must be a non-symbolic-link directory.');
  }

  const playwright = loadPlaywright();
  const executablePath = await chromeExecutable();
  const browser = await playwright.chromium.launch({ headless: true, executablePath });
  const results = [];
  for (const viewport of VIEWPORTS) {
    results.push(await inspectViewport(browser, options, viewport));
  }
  await browser.close();

  const report = {
    schemaVersion: 1,
    baseUrl: options.baseUrl,
    origin: options.origin,
    desktop: results[0],
    mobile: results[1],
    evidenceOutsideStarter: true,
    dispatcherLinked: false,
    publicMutationEndpointExercised: false,
    clientStateChanged: false,
    passed: results.every((result) => result.passed),
  };
  const reportPath = path.join(options.outputDir, 'report.json');
  await writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`, { mode: 0o600 });
  await chmod(reportPath, 0o600);
  if (!report.passed) {
    throw new Error(`Browser deployment evidence failed; see ${reportPath}`);
  }
  console.log(`Browser deployment evidence passed: ${reportPath}`);
}

main().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});
