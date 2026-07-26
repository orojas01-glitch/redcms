#!/usr/bin/env node

import { access, lstat, mkdir, readFile, writeFile } from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import { createRequire } from 'node:module';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';

const VIEWPORTS = [
  { name: 'desktop', width: 1512, height: 699 },
  { name: 'mobile', width: 390, height: 844 },
];

function usage() {
  return `Usage:
  node scripts/seo-metadata-browser-qa.mjs \\
    --base-url http://127.0.0.1:8061 \\
    --manifest /absolute/seo-import-manifest.json \\
    --output-dir /absolute/evidence/browser
`;
}

function parseArguments(argv) {
  const options = { baseUrl: '', manifest: '', outputDir: '', help: false };
  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--help' || argument === '-h') {
      options.help = true;
      continue;
    }
    const fields = new Map([
      ['--base-url', 'baseUrl'],
      ['--manifest', 'manifest'],
      ['--output-dir', 'outputDir'],
    ]);
    if (!fields.has(argument) || !argv[index + 1]) {
      throw new Error(`Unknown or incomplete argument: ${argument}`);
    }
    options[fields.get(argument)] = argv[index + 1];
    index += 1;
  }
  if (!options.help && Object.values(options).some((value) => value === '')) {
    throw new Error('Base URL, manifest, and output directory are required.');
  }
  options.manifest = path.resolve(options.manifest);
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
      if (loaded?.chromium && loaded?.request) return loaded;
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

function normalizeText(value) {
  return String(value ?? '').replace(/\s+/gu, ' ').trim();
}

function routeSlug(routePath) {
  if (routePath === '/') return 'home';
  return routePath.replace(/^\/+|\/+$/gu, '').replace(/[^a-z0-9_-]+/giu, '-');
}

function expectedImageUrl(reference, baseUrl) {
  return reference ? new URL(reference, baseUrl).href : '';
}

function expectedSchemaType(entry) {
  if (entry.metadata.SchemaType) return entry.metadata.SchemaType;
  return entry.routePath === '/' ? 'WebSite' : 'WebPage';
}

async function warmPage(page) {
  await page.evaluate(async () => {
    await document.fonts?.ready?.catch(() => {});
    const step = Math.max(360, Math.floor(window.innerHeight * 0.8));
    const height = Math.max(document.documentElement.scrollHeight, document.body?.scrollHeight ?? 0);
    for (let y = 0; y < height; y += step) {
      window.scrollTo(0, y);
      await new Promise((resolve) => window.setTimeout(resolve, 20));
    }
    window.scrollTo(0, 0);
    await Promise.all(Array.from(document.images).map((image) => {
      if (image.complete) return Promise.resolve();
      return new Promise((resolve) => {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
        window.setTimeout(resolve, 2500);
      });
    }));
  });
  await page.waitForTimeout(100);
}

async function inspectPage(page) {
  return page.evaluate(() => {
    const contents = (selector, attribute = 'content') => Array.from(document.querySelectorAll(selector))
      .map((element) => element.getAttribute(attribute) ?? '');
    const schemas = Array.from(document.querySelectorAll('script[type="application/ld+json"]'))
      .map((element) => {
        try {
          return { valid: true, value: JSON.parse(element.textContent || '') };
        } catch (error) {
          return { valid: false, error: error.message };
        }
      });
    const root = document.documentElement;
    const body = document.body;
    const main = document.querySelector('main#main-content');
    const mainStyle = main ? getComputedStyle(main) : null;
    const mainRect = main?.getBoundingClientRect();
    return {
      title: document.title,
      descriptions: contents('meta[name="description"]'),
      robots: contents('meta[name="robots"]'),
      canonicals: contents('link[rel="canonical"]', 'href'),
      ogLocale: contents('meta[property="og:locale"]'),
      ogType: contents('meta[property="og:type"]'),
      ogTitle: contents('meta[property="og:title"]'),
      ogDescription: contents('meta[property="og:description"]'),
      ogUrl: contents('meta[property="og:url"]'),
      ogImage: contents('meta[property="og:image"]'),
      ogImageAlt: contents('meta[property="og:image:alt"]'),
      xCard: contents('meta[name="twitter:card"]'),
      xTitle: contents('meta[name="twitter:title"]'),
      xDescription: contents('meta[name="twitter:description"]'),
      xImage: contents('meta[name="twitter:image"]'),
      xImageAlt: contents('meta[name="twitter:image:alt"]'),
      schemas,
      h1Count: document.querySelectorAll('h1').length,
      mainCount: document.querySelectorAll('main#main-content').length,
      mainVisible: Boolean(
        main
        && mainStyle?.display !== 'none'
        && mainStyle?.visibility !== 'hidden'
        && mainRect?.width > 0
        && mainRect?.height > 0
      ),
      overflowPixels: Math.max(
        0,
        Math.max(root.scrollWidth, body?.scrollWidth ?? 0) - root.clientWidth
      ),
      brokenImages: Array.from(document.images)
        .filter((image) => !image.complete || image.naturalWidth === 0 || image.naturalHeight === 0)
        .map((image) => ({ src: image.currentSrc || image.src, alt: image.alt })),
    };
  });
}

function compareOne(actual, expected, label, failures) {
  if (!Array.isArray(actual) || actual.length !== 1 || actual[0] !== expected) {
    failures.push({ code: label, expected, actual });
  }
}

function evaluateRoute(entry, document, runtime, baseUrl) {
  const failures = [];
  const metadata = entry.metadata;
  if (runtime.status !== 200) failures.push({ code: 'http-status', actual: runtime.status });
  if (runtime.finalUrl !== new URL(entry.routePath, baseUrl).href) {
    failures.push({ code: 'final-url', expected: new URL(entry.routePath, baseUrl).href, actual: runtime.finalUrl });
  }
  if (document.title !== metadata.SEO_Title) {
    failures.push({ code: 'title', expected: metadata.SEO_Title, actual: document.title });
  }
  compareOne(document.descriptions, metadata.MetaDescription, 'description', failures);
  compareOne(document.robots, 'index, follow', 'robots', failures);
  compareOne(document.canonicals, metadata.CanonicalURL, 'canonical', failures);
  compareOne(document.ogLocale, metadata.OGLocale || 'es_ES', 'og-locale', failures);
  compareOne(document.ogType, metadata.OGType || (entry.routePath === '/' ? 'website' : 'article'), 'og-type', failures);
  compareOne(document.ogTitle, metadata.OGTitle || metadata.SEO_Title, 'og-title', failures);
  compareOne(document.ogDescription, metadata.OGDescription || metadata.MetaDescription, 'og-description', failures);
  compareOne(document.ogUrl, metadata.CanonicalURL, 'og-url', failures);
  compareOne(document.ogImage, expectedImageUrl(metadata.OGImage, baseUrl), 'og-image', failures);
  if (document.ogImageAlt.length !== 1 || normalizeText(document.ogImageAlt[0]) === '') {
    failures.push({ code: 'og-image-alt', actual: document.ogImageAlt });
  }
  compareOne(document.xCard, metadata.XCard || 'summary_large_image', 'x-card', failures);
  compareOne(document.xTitle, metadata.XTitle || metadata.OGTitle || metadata.SEO_Title, 'x-title', failures);
  compareOne(
    document.xDescription,
    metadata.XDescription || metadata.OGDescription || metadata.MetaDescription,
    'x-description',
    failures
  );
  compareOne(
    document.xImage,
    expectedImageUrl(metadata.XImage || metadata.OGImage, baseUrl),
    'x-image',
    failures
  );
  if (document.xImageAlt.length !== 1 || normalizeText(document.xImageAlt[0]) === '') {
    failures.push({ code: 'x-image-alt', actual: document.xImageAlt });
  }
  if (document.schemas.length !== 1 || !document.schemas[0].valid) {
    failures.push({ code: 'jsonld-count-or-parse', actual: document.schemas });
  } else {
    const schema = document.schemas[0].value;
    if (schema['@type'] !== expectedSchemaType(entry)
      || schema.name !== metadata.SEO_Title
      || schema.url !== metadata.CanonicalURL
      || schema.description !== metadata.MetaDescription) {
      failures.push({
        code: 'jsonld-values',
        expected: {
          type: expectedSchemaType(entry),
          name: metadata.SEO_Title,
          url: metadata.CanonicalURL,
          description: metadata.MetaDescription,
        },
        actual: schema,
      });
    }
  }
  if (document.h1Count !== 1) failures.push({ code: 'h1-count', actual: document.h1Count });
  if (document.mainCount !== 1 || !document.mainVisible) {
    failures.push({ code: 'main-content', count: document.mainCount, visible: document.mainVisible });
  }
  if (document.overflowPixels > 1) failures.push({ code: 'horizontal-overflow', actual: document.overflowPixels });
  if (document.brokenImages.length > 0) failures.push({ code: 'broken-images', actual: document.brokenImages });
  if (runtime.consoleErrors.length > 0) failures.push({ code: 'console-errors', actual: runtime.consoleErrors });
  if (runtime.pageErrors.length > 0) failures.push({ code: 'page-errors', actual: runtime.pageErrors });
  if (runtime.sameOriginFailures.length > 0) {
    failures.push({ code: 'same-origin-network', actual: runtime.sameOriginFailures });
  }
  return failures;
}

async function main() {
  const options = parseArguments(process.argv.slice(2));
  if (options.help) {
    process.stdout.write(usage());
    return;
  }
  const base = new URL(options.baseUrl);
  if (base.protocol !== 'http:' || base.hostname !== '127.0.0.1' || base.pathname !== '/') {
    throw new Error('The QA base URL must be an HTTP loopback origin with a root path.');
  }
  const manifestStats = await lstat(options.manifest);
  if (!manifestStats.isFile() || manifestStats.isSymbolicLink()) {
    throw new Error('The manifest must be a regular non-symbolic-link file.');
  }
  const manifest = JSON.parse(await readFile(options.manifest, 'utf8'));
  if (manifest.schemaVersion !== 1 || !Array.isArray(manifest.entries) || manifest.entries.length < 1) {
    throw new Error('The SEO migration manifest is invalid.');
  }
  await mkdir(options.outputDir, { recursive: true, mode: 0o700 });
  const outputStats = await lstat(options.outputDir);
  if (!outputStats.isDirectory() || outputStats.isSymbolicLink()) {
    throw new Error('The output directory must be a non-symbolic-link directory.');
  }

  const playwright = loadPlaywright();
  const executablePath = await chromeExecutable();
  const browser = await playwright.chromium.launch({ headless: true, executablePath });
  const results = [];
  for (const viewport of VIEWPORTS) {
    const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
    for (const entry of manifest.entries) {
      const page = await context.newPage();
      const consoleErrors = [];
      const pageErrors = [];
      const sameOriginFailures = [];
      const externalFailures = [];
      page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
      });
      page.on('pageerror', (error) => pageErrors.push(error.message));
      page.on('requestfailed', (request) => {
        const item = { url: request.url(), error: request.failure()?.errorText ?? 'request failed' };
        (new URL(request.url()).origin === base.origin ? sameOriginFailures : externalFailures).push(item);
      });
      page.on('response', (response) => {
        if (response.status() < 400) return;
        const item = { url: response.url(), status: response.status() };
        (new URL(response.url()).origin === base.origin ? sameOriginFailures : externalFailures).push(item);
      });
      const response = await page.goto(new URL(entry.routePath, base).href, {
        waitUntil: 'domcontentloaded',
        timeout: 20_000,
      });
      await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => {});
      await warmPage(page);
      const document = await inspectPage(page);
      const runtime = {
        status: response?.status() ?? 0,
        finalUrl: page.url(),
        consoleErrors,
        pageErrors,
        sameOriginFailures,
        externalFailures,
      };
      const failures = evaluateRoute(entry, document, runtime, base.href);
      const screenshot = path.join(
        options.outputDir,
        `${viewport.name}-${routeSlug(entry.routePath)}.png`
      );
      await page.screenshot({ path: screenshot, fullPage: true });
      results.push({
        viewport,
        source: entry.source,
        routePath: entry.routePath,
        runtime,
        document,
        screenshot,
        failures,
        passed: failures.length === 0,
      });
      await page.close();
    }
    await context.close();
  }

  const requestContext = await playwright.request.newContext({ baseURL: base.href });
  const redirects = [];
  for (const entry of manifest.entries) {
    const sourcePath = entry.source === 'index.html' ? '/index.html' : `/${entry.source}`;
    const response = await requestContext.get(sourcePath, { maxRedirects: 0 });
    const location = response.headers().location ?? '';
    const resolved = location ? new URL(location, base).pathname : '';
    redirects.push({
      sourcePath,
      routePath: entry.routePath,
      status: response.status(),
      location,
      resolved,
      passed: response.status() === 308 && resolved === entry.routePath,
    });
    await response.dispose();
  }
  const sitemapResponse = await requestContext.get('/sitemap.xml');
  const sitemapText = await sitemapResponse.text();
  const sitemapUrls = [...sitemapText.matchAll(/<loc>([^<]+)<\/loc>/gu)].map((match) => match[1]);
  const expectedUrls = manifest.entries.map((entry) => entry.metadata.CanonicalURL);
  const sitemap = {
    status: sitemapResponse.status(),
    urls: sitemapUrls,
    missing: expectedUrls.filter((url) => !sitemapUrls.includes(url)),
    unexpected: sitemapUrls.filter((url) => !expectedUrls.includes(url)),
  };
  sitemap.passed = sitemap.status === 200
    && sitemap.urls.length === expectedUrls.length
    && sitemap.missing.length === 0
    && sitemap.unexpected.length === 0;
  const robotsResponse = await requestContext.get('/robots.txt');
  const robotsText = await robotsResponse.text();
  const robots = {
    status: robotsResponse.status(),
    sitemapDeclared: robotsText.includes(`Sitemap: ${manifest.siteOrigin}/sitemap.xml`),
    sitewideBlock: /^Disallow:\s*\/\s*$/imu.test(robotsText),
  };
  robots.passed = robots.status === 200 && robots.sitemapDeclared && !robots.sitewideBlock;
  await requestContext.dispose();
  await browser.close();

  const report = {
    schemaVersion: 1,
    baseUrl: base.href,
    manifest: options.manifest,
    viewports: VIEWPORTS,
    summary: {
      routes: manifest.entries.length,
      browserChecks: results.length,
      passedBrowserChecks: results.filter((result) => result.passed).length,
      failedBrowserChecks: results.filter((result) => !result.passed).length,
      redirects: redirects.length,
      passedRedirects: redirects.filter((redirect) => redirect.passed).length,
      externalNetworkWarnings: results.reduce(
        (count, result) => count + result.runtime.externalFailures.length,
        0
      ),
      screenshots: results.length,
    },
    sitemap,
    robots,
    redirects,
    results,
  };
  const reportPath = path.join(options.outputDir, 'report.json');
  await writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`, { mode: 0o600 });
  const passed = report.summary.failedBrowserChecks === 0
    && report.summary.passedRedirects === report.summary.redirects
    && sitemap.passed
    && robots.passed;
  process.stdout.write(`${JSON.stringify({ passed, report: reportPath, summary: report.summary, sitemap, robots }, null, 2)}\n`);
  if (!passed) process.exitCode = 1;
}

main().catch((error) => {
  process.stderr.write(`SEO browser QA failed: ${error.stack || error.message}\n`);
  process.exitCode = 1;
});
