#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { constants as fsConstants } from 'node:fs';
import { access, lstat, mkdir, readFile, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';

const DEFAULT_STATE_FILE = '/private/tmp/redcms-adriana-28-current.state';
const MANIFEST_RELATIVE_PATH = path.join(
  'content-migrations',
  'adriana-granobles-v4',
  'routes.json'
);
const MAPPING_RELATIVE_PATH = path.join(
  'content-migrations',
  'adriana-granobles-v4',
  'pages',
  'site.json'
);
const RUNTIME_MARKER_NAME = '.redcms-adriana-28-runtime';
const STATE_KEYS = [
  'STATE_VERSION',
  'STATE_MARKER',
  'DATABASE',
  'PRIMARY_DATABASE',
  'APP_ACCOUNT_USER',
  'APP_ACCOUNT_HOST',
  'RUN_ROOT',
  'WEBROOT',
  'PHP_LOG',
  'PHP_PID',
  'PORT',
  'PRIMARY_SNAPSHOT',
  'MANIFEST_SHA256',
  'CREATED_AT',
];
const EXPECTED_THEME_CLASS = 'red-standard-theme--adriana-granobles';
const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 },
];

function usage() {
  return `Usage:
  node scripts/adriana-route-browser-qa.mjs \\
    --base-url http://127.0.0.1:8060 \\
    --output-dir docs/adriana-content-qa/disposable-run

Options:
  --base-url URL              Disposable RED-CMS origin (required).
  --output-dir PATH           Screenshot and JSON evidence directory (required).
  --state-file PATH           Disposable lifecycle state (default: /private/tmp/redcms-adriana-28-current.state).
  --manifest PATH             Manifest override (default: the recorded disposable webroot manifest).
  --mapping PATH              Pagewise mapping override (default: the recorded disposable webroot mapping).
  --chrome-executable PATH    Chrome/Chromium executable override.
  --playwright-module PATH    Playwright module override.
  --headed                    Show the browser while auditing.
  --help                      Show this help.
`;
}

function parseArguments(argv) {
  const options = {
    baseUrl: '',
    outputDir: '',
    stateFile: DEFAULT_STATE_FILE,
    manifest: '',
    mapping: '',
    chromeExecutable: '',
    playwrightModule: '',
    headless: true,
    help: false,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--help' || argument === '-h') {
      options.help = true;
      continue;
    }
    if (argument === '--headed') {
      options.headless = false;
      continue;
    }

    const valueOptions = new Map([
      ['--base-url', 'baseUrl'],
      ['--output-dir', 'outputDir'],
      ['--state-file', 'stateFile'],
      ['--manifest', 'manifest'],
      ['--mapping', 'mapping'],
      ['--chrome-executable', 'chromeExecutable'],
      ['--playwright-module', 'playwrightModule'],
    ]);
    if (!valueOptions.has(argument)) {
      throw new Error(`Unknown argument: ${argument}`);
    }
    if (index + 1 >= argv.length || argv[index + 1].startsWith('--')) {
      throw new Error(`Missing value for ${argument}`);
    }
    options[valueOptions.get(argument)] = argv[index + 1];
    index += 1;
  }

  if (!options.help && options.baseUrl === '') {
    throw new Error('--base-url is required.');
  }
  if (!options.help && options.outputDir === '') {
    throw new Error('--output-dir is required.');
  }

  options.stateFile = path.resolve(options.stateFile);
  options.manifest = options.manifest === '' ? '' : path.resolve(options.manifest);
  options.mapping = options.mapping === '' ? '' : path.resolve(options.mapping);
  options.outputDir = options.outputDir === '' ? '' : path.resolve(options.outputDir);
  if (options.chromeExecutable !== '') {
    options.chromeExecutable = path.resolve(options.chromeExecutable);
  }
  if (options.playwrightModule !== '' && options.playwrightModule !== 'playwright') {
    options.playwrightModule = path.resolve(options.playwrightModule);
  }

  return options;
}

function normalizeText(value) {
  return String(value ?? '').replace(/\s+/gu, ' ').trim();
}

function normalizeComparison(value) {
  return normalizeText(value).toLocaleLowerCase('es');
}

function titleMatches(actualTitle, expectedTitle) {
  const actual = normalizeText(actualTitle);
  const expected = normalizeText(expectedTitle);
  if (actual === expected) {
    return { matches: true, mode: 'exact' };
  }
  if (normalizeComparison(actual) === normalizeComparison(expected)) {
    return { matches: true, mode: 'legacy-case-normalized-exact' };
  }
  return { matches: false, mode: 'none' };
}

function safeUrl(rawUrl) {
  try {
    const parsed = new URL(rawUrl);
    parsed.username = '';
    parsed.password = '';
    parsed.hash = '';
    if (parsed.search !== '') {
      parsed.search = '?redacted';
    }
    return parsed.href;
  } catch {
    return normalizeText(rawUrl).slice(0, 500);
  }
}

function safeText(rawText) {
  return normalizeText(rawText)
    .replace(/https?:\/\/[^\s"'<>]+/gu, (match) => safeUrl(match))
    .slice(0, 1000);
}

function urlWithoutHash(rawUrl) {
  const parsed = new URL(rawUrl);
  parsed.hash = '';
  return parsed.href;
}

function isSameOrigin(rawUrl, origin) {
  try {
    return new URL(rawUrl).origin === origin;
  } catch {
    return false;
  }
}

function requestEvidence(request, extra = {}) {
  return {
    method: request.method(),
    resourceType: request.resourceType(),
    url: safeUrl(request.url()),
    ...extra,
  };
}

function routeSlug(route) {
  if (route.path === '/') return 'home';
  return route.path.replace(/^\/+|\/+$/gu, '').replace(/[^a-z0-9_-]+/giu, '-');
}

function failure(code, message, evidence = null) {
  return { code, message, evidence };
}

function sha256(buffer) {
  return createHash('sha256').update(buffer).digest('hex');
}

function permissionMode(stats) {
  return stats.mode & 0o777;
}

function assertOwnedByCurrentUser(stats, label) {
  if (typeof process.getuid === 'function' && stats.uid !== process.getuid()) {
    throw new Error(`${label} is not owned by the current user.`);
  }
}

async function requireRegularFile(filePath, expectedMode, label) {
  let stats;
  try {
    stats = await lstat(filePath);
  } catch (error) {
    throw new Error(`${label} is unavailable: ${safeText(error.message)}`);
  }
  if (!stats.isFile() || stats.isSymbolicLink()) {
    throw new Error(`${label} must be a regular non-symbolic-link file.`);
  }
  assertOwnedByCurrentUser(stats, label);
  if (expectedMode !== null && permissionMode(stats) !== expectedMode) {
    throw new Error(`${label} must have mode ${expectedMode.toString(8)}.`);
  }
  return stats;
}

async function requireDirectory(directoryPath, expectedMode, label) {
  let stats;
  try {
    stats = await lstat(directoryPath);
  } catch (error) {
    throw new Error(`${label} is unavailable: ${safeText(error.message)}`);
  }
  if (!stats.isDirectory() || stats.isSymbolicLink()) {
    throw new Error(`${label} must be a non-symbolic-link directory.`);
  }
  assertOwnedByCurrentUser(stats, label);
  if (permissionMode(stats) !== expectedMode) {
    throw new Error(`${label} must have mode ${expectedMode.toString(8)}.`);
  }
}

function parseStateText(stateText) {
  if (stateText.includes('\0')) {
    throw new Error('Disposable runtime state contains a NUL byte.');
  }
  const lines = stateText.split(/\r?\n/u);
  if (lines.at(-1) === '') lines.pop();
  const state = Object.create(null);
  for (const line of lines) {
    const separator = line.indexOf('=');
    if (separator <= 0) {
      throw new Error('Disposable runtime state contains a malformed line.');
    }
    const key = line.slice(0, separator);
    const value = line.slice(separator + 1);
    if (!STATE_KEYS.includes(key)) {
      throw new Error(`Disposable runtime state contains an unknown key: ${key}`);
    }
    if (Object.hasOwn(state, key)) {
      throw new Error(`Disposable runtime state repeats a key: ${key}`);
    }
    if (value === '') {
      throw new Error(`Disposable runtime state contains an empty value: ${key}`);
    }
    state[key] = value;
  }
  for (const key of STATE_KEYS) {
    if (!Object.hasOwn(state, key)) {
      throw new Error(`Disposable runtime state is missing a key: ${key}`);
    }
  }
  return state;
}

async function loadAndValidateState(stateFile) {
  if (!/^\/private\/tmp\/redcms-adriana-28-[A-Za-z0-9_.-]+\.state$/u.test(stateFile)) {
    throw new Error('--state-file must use the guarded /private/tmp/redcms-adriana-28-*.state path.');
  }
  await requireRegularFile(stateFile, 0o600, 'Disposable runtime state');
  const stateBuffer = await readFile(stateFile);
  if (stateBuffer.length === 0 || stateBuffer.length > 65_536) {
    throw new Error('Disposable runtime state has an invalid size.');
  }
  const state = parseStateText(stateBuffer.toString('utf8'));

  if (state.STATE_VERSION !== '1' || state.STATE_MARKER !== 'redcms-adriana-28-runtime') {
    throw new Error('Disposable runtime state version or marker is invalid.');
  }
  if (!/^redcms_adriana_28_[A-Za-z0-9_]+$/u.test(state.DATABASE) || state.DATABASE.length > 64) {
    throw new Error('Disposable runtime database is outside the guarded prefix.');
  }
  if (!/^[A-Za-z0-9_]+$/u.test(state.PRIMARY_DATABASE) || state.DATABASE === state.PRIMARY_DATABASE) {
    throw new Error('Disposable and primary database identities are unsafe.');
  }
  if (!/^[A-Za-z0-9_.-]+$/u.test(state.APP_ACCOUNT_USER)
    || !/^[A-Za-z0-9_.:%-]+$/u.test(state.APP_ACCOUNT_HOST)) {
    throw new Error('Disposable runtime application account identity is unsafe.');
  }
  if (!/^\/private\/tmp\/redcms-adriana-28-runtime\.[A-Za-z0-9]+$/u.test(state.RUN_ROOT)) {
    throw new Error('Disposable runtime root is outside the guarded prefix.');
  }
  if (state.WEBROOT !== path.join(state.RUN_ROOT, 'webroot')
    || state.PHP_LOG !== path.join(state.RUN_ROOT, 'php.log')) {
    throw new Error('Disposable runtime paths do not match the guarded root layout.');
  }

  const phpPid = Number(state.PHP_PID);
  const port = Number(state.PORT);
  const createdAt = Number(state.CREATED_AT);
  if (!/^[0-9]+$/u.test(state.PHP_PID) || !Number.isSafeInteger(phpPid) || phpPid <= 1
    || !/^[0-9]+$/u.test(state.PORT) || !Number.isSafeInteger(port) || port < 8060 || port > 65_535
    || port === 8055
    || !/^[0-9]+$/u.test(state.CREATED_AT) || !Number.isSafeInteger(createdAt) || createdAt <= 0
    || !/^[a-f0-9]{64}$/u.test(state.PRIMARY_SNAPSHOT)
    || !/^[a-f0-9]{64}$/u.test(state.MANIFEST_SHA256)) {
    throw new Error('Disposable runtime numeric or checksum state is invalid.');
  }

  await requireDirectory(state.RUN_ROOT, 0o700, 'Disposable runtime root');
  await requireDirectory(state.WEBROOT, 0o700, 'Disposable webroot');
  await requireRegularFile(state.PHP_LOG, 0o600, 'Disposable PHP log');
  const markerPath = path.join(state.RUN_ROOT, RUNTIME_MARKER_NAME);
  await requireRegularFile(markerPath, 0o600, 'Disposable runtime marker');
  const markerLines = (await readFile(markerPath, 'utf8')).trimEnd().split(/\r?\n/u);
  if (markerLines.length !== 2
    || markerLines[0] !== 'redcms-adriana-28-runtime-v1'
    || markerLines[1] !== `database=${state.DATABASE}`) {
    throw new Error('Disposable runtime marker does not match the recorded database.');
  }

  return {
    ...state,
    phpPid,
    port,
    createdAt,
    stateSha256: sha256(stateBuffer),
  };
}

function validateAndNormalizeBaseUrl(rawBaseUrl, state) {
  let parsed;
  try {
    parsed = new URL(rawBaseUrl);
  } catch {
    throw new Error('--base-url is not a valid URL.');
  }
  if (parsed.protocol !== 'http:'
    || parsed.hostname !== '127.0.0.1'
    || parsed.username !== ''
    || parsed.password !== ''
    || parsed.pathname !== '/'
    || parsed.search !== ''
    || parsed.hash !== '') {
    throw new Error('--base-url must be the exact recorded HTTP loopback origin with a root path.');
  }
  if (parsed.port !== String(state.port) || state.port === 8055) {
    throw new Error('--base-url port does not match the recorded disposable non-8055 port.');
  }
  return parsed.href;
}

function expectedDocumentTitle(route, websiteTitle) {
  const legacyArticleTitle = route.title.replace(/-/gu, ' ');
  if (route.path === '/') return route.title;
  if (route.kind === 'section') return `${websiteTitle} | ${route.sectionTitle}`;
  return `${websiteTitle} | ${legacyArticleTitle}`;
}

function validateManifest(manifest) {
  if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) {
    throw new Error('The route manifest must be a JSON object.');
  }
  if (manifest.migrationId !== 'adriana-granobles-v4') {
    throw new Error('The route manifest has an unexpected migrationId.');
  }
  if (!Array.isArray(manifest.routes) || manifest.routes.length !== 28) {
    throw new Error('The route manifest must contain exactly 28 routes.');
  }
  if (manifest.counts?.routes !== 28 || manifest.counts?.nonHomeAliases !== 27) {
    throw new Error('The route manifest count sentinels must remain 28 routes and 27 non-home aliases.');
  }
  if (!manifest.shell || typeof manifest.shell !== 'object' || Array.isArray(manifest.shell)
    || typeof manifest.shell.websiteTitle !== 'string'
    || normalizeText(manifest.shell.websiteTitle) === '') {
    throw new Error('The route manifest must contain a non-empty shell Website_Title.');
  }

  const paths = new Set();
  const aliases = new Set();
  let homeCount = 0;
  for (const [index, route] of manifest.routes.entries()) {
    for (const field of ['source', 'path', 'alias', 'layout', 'title', 'h1', 'sourceMarker']) {
      if (typeof route[field] !== 'string') {
        throw new Error(`Route ${index + 1} has an invalid ${field}.`);
      }
    }
    if (paths.has(route.path)) {
      throw new Error(`Duplicate route path: ${route.path}`);
    }
    paths.add(route.path);
    if (route.path === '/') {
      homeCount += 1;
      if (route.alias !== '') {
        throw new Error('The home route must use an empty alias.');
      }
    } else {
      if (!/^\/[a-z0-9-]+\.html$/u.test(route.path)) {
        throw new Error(`Non-home route does not preserve the exact root .html contract: ${route.path}`);
      }
      if (route.alias === '' || aliases.has(route.alias)) {
        throw new Error(`Invalid or duplicate non-home alias: ${route.alias}`);
      }
      aliases.add(route.alias);
    }
  }
  if (homeCount !== 1 || aliases.size !== 27) {
    throw new Error('The route manifest must contain one home route and 27 unique non-home aliases.');
  }
}

function validatePagewiseMapping(mapping, manifest, manifestSha256) {
  if (!mapping || typeof mapping !== 'object' || Array.isArray(mapping)) {
    throw new Error('The pagewise mapping must be a JSON object.');
  }
  if (mapping.schemaVersion !== 2
    || mapping.migrationId !== 'adriana-granobles-v4-pagewise-site'
    || mapping.manifestSha256 !== manifestSha256) {
    throw new Error('The pagewise mapping identity or manifest checksum is invalid.');
  }
  if (!Array.isArray(mapping.routes) || mapping.routes.length !== 28) {
    throw new Error('The pagewise mapping must contain exactly 28 routes.');
  }

  const canonicalPaths = new Set();
  let mappedSectionCount = 0;
  for (const [index, mappedRoute] of mapping.routes.entries()) {
    const sourceRoute = manifest.routes[index];
    for (const field of ['sourcePath', 'canonicalPath', 'kind', 'section', 'alias', 'layout']) {
      if (typeof mappedRoute[field] !== 'string') {
        throw new Error(`Mapped route ${index + 1} has an invalid ${field}.`);
      }
    }
    if (mappedRoute.sourcePath !== sourceRoute.path || mappedRoute.layout !== sourceRoute.layout) {
      throw new Error(`Mapped route ${index + 1} does not match its source manifest route.`);
    }
    if (!['home', 'section', 'article'].includes(mappedRoute.kind)) {
      throw new Error(`Mapped route ${index + 1} has an unsupported kind.`);
    }
    if (canonicalPaths.has(mappedRoute.canonicalPath)) {
      throw new Error(`Duplicate canonical route path: ${mappedRoute.canonicalPath}`);
    }
    canonicalPaths.add(mappedRoute.canonicalPath);
    if (!Array.isArray(mappedRoute.positions) || mappedRoute.positions.length < 1
      || mappedRoute.positions.some((position) => !Number.isInteger(position) || position < 1 || position > 5)) {
      throw new Error(`Mapped route ${index + 1} has invalid layout positions.`);
    }
    if (mappedRoute.kind === 'section'
      && (typeof mappedRoute.sectionTitle !== 'string' || normalizeText(mappedRoute.sectionTitle) === '')) {
      throw new Error(`Mapped section route ${index + 1} has no section title.`);
    }
    mappedSectionCount += mappedRoute.positions.length;
  }
  if (canonicalPaths.size !== 28 || mappedSectionCount !== 153) {
    throw new Error('The pagewise mapping must preserve 28 canonical routes and 153 source sections.');
  }

  if (!Array.isArray(mapping.navigation) || mapping.navigation.length !== 9) {
    throw new Error('The pagewise navigation must contain exactly nine top-level links.');
  }
  const navigationRows = mapping.navigation.reduce(
    (total, item) => total + 1 + (Array.isArray(item.children) ? item.children.length : 0),
    0
  );
  if (navigationRows !== 28) {
    throw new Error('The pagewise navigation must contain exactly 28 total links.');
  }
}

function buildCanonicalRoutes(manifest, mapping) {
  return manifest.routes.map((sourceRoute, index) => {
    const mappedRoute = mapping.routes[index];
    return {
      ...sourceRoute,
      sourcePath: sourceRoute.path,
      path: mappedRoute.canonicalPath,
      kind: mappedRoute.kind,
      section: mappedRoute.section,
      sectionTitle: mappedRoute.sectionTitle ?? '',
      canonicalAlias: mappedRoute.alias,
      positions: mappedRoute.positions,
    };
  });
}

async function auditLegacyRedirects(requestContext, routes, baseUrl) {
  const redirects = [
    { sourcePath: '/index.html', canonicalPath: '/' },
    ...routes
      .filter((route) => route.sourcePath !== '/')
      .map((route) => ({ sourcePath: route.sourcePath, canonicalPath: route.path })),
  ];

  const results = [];
  for (const redirect of redirects) {
    const requestedUrl = new URL(redirect.sourcePath, baseUrl);
    let status = null;
    let location = '';
    let error = '';
    try {
      const response = await requestContext.get(requestedUrl.href, { maxRedirects: 0 });
      status = response.status();
      location = response.headers().location ?? '';
      await response.dispose();
    } catch (caught) {
      error = safeText(caught.stack || caught.message);
    }
    let resolvedPath = '';
    try {
      const resolved = new URL(location, requestedUrl);
      resolvedPath = `${resolved.pathname}${resolved.search}`;
    } catch {
      resolvedPath = '';
    }
    results.push({
      sourcePath: redirect.sourcePath,
      canonicalPath: redirect.canonicalPath,
      status,
      location: safeText(location),
      resolvedPath,
      error,
      passed: status === 308 && resolvedPath === redirect.canonicalPath && error === '',
    });
  }
  return results;
}

function loadPlaywright(moduleOverride) {
  const require = createRequire(import.meta.url);
  const candidates = [
    moduleOverride,
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
    'playwright-core',
  ].filter(Boolean);
  const errors = [];

  for (const candidate of candidates) {
    try {
      const loaded = require(candidate);
      if (loaded?.chromium && loaded?.request) {
        return { chromium: loaded.chromium, request: loaded.request, module: candidate };
      }
      errors.push(`${candidate}: chromium or request export not found`);
    } catch (error) {
      errors.push(`${candidate}: ${safeText(error.message)}`);
    }
  }

  throw new Error(
    `Playwright could not be loaded. Use the bundled Codex Node runtime or pass --playwright-module. ${errors.join(' | ')}`
  );
}

async function executableFile(candidate) {
  if (!candidate) return false;
  try {
    await access(candidate, fsConstants.X_OK);
    return true;
  } catch {
    return false;
  }
}

async function findChromeExecutable(override) {
  const candidates = [
    override,
    process.env.RED_CHROME_EXECUTABLE,
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Chromium.app/Contents/MacOS/Chromium',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
  ].filter(Boolean);

  for (const candidate of candidates) {
    if (await executableFile(candidate)) {
      return candidate;
    }
  }
  if (override) {
    throw new Error(`Chrome/Chromium executable is unavailable: ${override}`);
  }
  return '';
}

async function warmDocument(page) {
  await page.evaluate(async () => {
    if (document.fonts?.ready) {
      await document.fonts.ready.catch(() => {});
    }

    const viewportStep = Math.max(Math.floor(window.innerHeight * 0.82), 360);
    const maximumY = Math.max(document.documentElement.scrollHeight, document.body?.scrollHeight ?? 0);
    const positions = [];
    for (let y = 0; y < maximumY && positions.length < 120; y += viewportStep) {
      positions.push(y);
    }
    positions.push(maximumY);
    for (const y of positions) {
      window.scrollTo(0, y);
      await new Promise((resolve) => window.setTimeout(resolve, 25));
    }
    window.scrollTo(0, 0);

    const imageSettles = Array.from(document.images).map((image) => {
      if (image.complete) return Promise.resolve();
      return new Promise((resolve) => {
        const settle = () => resolve();
        image.addEventListener('load', settle, { once: true });
        image.addEventListener('error', settle, { once: true });
        window.setTimeout(settle, 2500);
      });
    });
    await Promise.all(imageSettles);
  });
  await page.waitForTimeout(150);
}

async function inspectDocument(page, route, viewport) {
  const expectedMarker = route.alias === '' ? 'home' : route.alias;
  return page.evaluate(
    ({ expectedMarkerValue, expectedLayout }) => {
      const normalize = (value) => String(value ?? '').replace(/\s+/gu, ' ').trim();
      const isVisible = (element) => {
        if (!(element instanceof Element)) return false;
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
      };
      const root = document.documentElement;
      const body = document.body;
      const h1Elements = Array.from(document.querySelectorAll('h1'));
      const markers = Array.from(document.querySelectorAll('[data-redcms-source-page]'));
      const brokenImages = Array.from(document.images)
        .filter((image) => !image.complete || image.naturalWidth === 0 || image.naturalHeight === 0)
        .map((image) => ({
          src: image.currentSrc || image.src,
          alt: image.alt,
          complete: image.complete,
          naturalWidth: image.naturalWidth,
          naturalHeight: image.naturalHeight,
        }));
      const text = normalize(root.innerText || root.textContent || '');
      const phpPatterns = [
        /(?:PHP\s+)?(?:Fatal error|Parse error|Warning|Notice|Deprecated|Recoverable fatal error)\s*:/giu,
        /Uncaught\s+(?:mysqli_sql_exception|Error|Exception)/giu,
      ];
      const phpErrors = [];
      for (const pattern of phpPatterns) {
        for (const match of text.matchAll(pattern)) {
          phpErrors.push(match[0]);
        }
      }
      if (document.querySelector('.xdebug-error, .php-error, table.xdebug-error')) {
        phpErrors.push('PHP/Xdebug error element');
      }
      const viewportWidth = root.clientWidth;
      const scrollWidth = Math.max(root.scrollWidth, body?.scrollWidth ?? 0);
      const marker = markers[0] ?? null;

      return {
        title: document.title,
        h1Count: h1Elements.length,
        h1: h1Elements.length > 0 ? normalize(h1Elements[0].textContent) : '',
        themeClassPresent: body?.classList.contains('red-standard-theme--adriana-granobles') ?? false,
        layoutClassPresent: document.querySelector(`main.adriana-layout--${expectedLayout}`) !== null,
        mainCount: document.querySelectorAll('main#main-content').length,
        sourceMarkerCount: markers.length,
        sourceMarkerValue: marker?.getAttribute('data-redcms-source-page') ?? '',
        sourceMarkerText: marker ? normalize(marker.textContent) : '',
        sourceMarkerMatchesExpectedValue:
          markers.length === 1 && marker?.getAttribute('data-redcms-source-page') === expectedMarkerValue,
        phpErrors: Array.from(new Set(phpErrors)),
        brokenImages,
        horizontalOverflowPixels: Math.max(0, scrollWidth - viewportWidth),
        documentWidth: scrollWidth,
        viewportWidth,
        visibleMain: isVisible(document.querySelector('main#main-content')),
        formCountInMain: document.querySelectorAll('main#main-content form').length,
        submissionObserved: window.__redcmsQaFormSubmitObserved === true,
      };
    },
    { expectedMarkerValue: expectedMarker, expectedLayout: route.layout, viewport }
  );
}

async function inspectContactForm(page) {
  return page.evaluate(() => {
    const component = document.querySelector(
      '.redcms-component--form[data-red-component="Form"]'
    );
    const forms = component ? Array.from(component.querySelectorAll('form')) : [];
    const form = forms[0] ?? null;
    const visible = (element) => {
      if (!(element instanceof Element)) return false;
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    };
    const fields = form
      ? Array.from(form.querySelectorAll('input:not([type="hidden"]), textarea:not(#MySpamTrap), select'))
      : [];
    const submitControls = form
      ? Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'))
      : [];

    return {
      componentCount: document.querySelectorAll(
        '.redcms-component--form[data-red-component="Form"]'
      ).length,
      formCount: forms.length,
      formVisible: visible(form),
      method: form?.method?.toUpperCase() ?? '',
      fieldCount: fields.length,
      visibleFieldCount: fields.filter(visible).length,
      submitControlCount: submitControls.length,
      visibleSubmitControlCount: submitControls.filter(visible).length,
      nativeAnchorRemainderCount: document.querySelectorAll('[data-redcms-native-form-anchor]').length,
      submissionObserved: window.__redcmsQaFormSubmitObserved === true,
      inspectedWithoutSubmission: true,
    };
  });
}

async function exerciseMenu(page, viewportName) {
  if (viewportName === 'mobile') {
    const toggle = page.locator('[data-menu-toggle]');
    if ((await toggle.count()) !== 1 || !(await toggle.isVisible())) {
      return { passed: false, mode: 'mobile-toggle', reason: 'Visible mobile menu toggle not found.' };
    }
    await toggle.click();
    const opened = await page.evaluate(() => ({
      expanded: document.querySelector('[data-menu-toggle]')?.getAttribute('aria-expanded') === 'true',
      navOpen: document.querySelector('[data-site-nav]')?.classList.contains('is-open') === true,
      bodyOpen: document.body.classList.contains('is-menu-open'),
    }));
    await toggle.click();
    const closed = await page.evaluate(() => ({
      expanded: document.querySelector('[data-menu-toggle]')?.getAttribute('aria-expanded') === 'false',
      navClosed: document.querySelector('[data-site-nav]')?.classList.contains('is-open') === false,
      bodyClosed: !document.body.classList.contains('is-menu-open'),
    }));
    return {
      passed: Object.values(opened).every(Boolean) && Object.values(closed).every(Boolean),
      mode: 'mobile-toggle',
      opened,
      closed,
    };
  }

  const dropdownToggle = page.locator('[data-dropdown-toggle]:visible').first();
  if ((await dropdownToggle.count()) !== 1) {
    return { passed: false, mode: 'desktop-dropdown', reason: 'Visible desktop dropdown toggle not found.' };
  }
  await dropdownToggle.click();
  await page.waitForTimeout(250);
  const opened = await dropdownToggle.evaluate((button) => ({
    expanded: button.getAttribute('aria-expanded') === 'true',
    groupOpen: button.closest('[data-nav-group]')?.classList.contains('is-open') === true,
    controlledMenuVisible: (() => {
      const controlledId = button.getAttribute('aria-controls');
      const controlled = controlledId ? document.getElementById(controlledId) : null;
      if (!controlled) return false;
      const style = window.getComputedStyle(controlled);
      return style.display !== 'none' && style.visibility !== 'hidden';
    })(),
  }));
  await page.keyboard.press('Escape');
  await page.waitForTimeout(50);
  const closed = await dropdownToggle.evaluate((button) => ({
    expanded: button.getAttribute('aria-expanded') === 'false',
    groupClosed: button.closest('[data-nav-group]')?.classList.contains('is-open') === false,
  }));
  return {
    passed: Object.values(opened).every(Boolean) && Object.values(closed).every(Boolean),
    mode: 'desktop-dropdown',
    opened,
    closed,
  };
}

function buildFailures(result) {
  const failures = [];
  if (result.navigation.status !== 200) {
    failures.push(failure('http-status', 'Expected HTTP 200.', result.navigation.status));
  }
  if (result.navigation.redirected) {
    failures.push(failure('redirect', 'The exact route must not redirect.', result.navigation.redirectChain));
  }
  if (!result.navigation.finalUrlMatches) {
    failures.push(
      failure('final-url', 'The final URL does not match the requested exact route.', {
        expected: result.navigation.expectedUrl,
        actual: result.navigation.finalUrl,
      })
    );
  }
  if (result.navigation.error !== '') {
    failures.push(failure('navigation-error', 'Navigation raised an error.', result.navigation.error));
  }
  if (!result.document.themeClassPresent) {
    failures.push(failure('theme-class', `Body is missing ${EXPECTED_THEME_CLASS}.`));
  }
  if (!result.document.layoutClassPresent) {
    failures.push(failure('layout-class', `Expected Adriana layout class for ${result.expected.layout}.`));
  }
  if (result.document.mainCount !== 1 || !result.document.visibleMain) {
    failures.push(failure('main-content', 'Expected one visible main#main-content.', {
      count: result.document.mainCount,
      visible: result.document.visibleMain,
    }));
  }
  if (!result.title.matches) {
    failures.push(failure('title', 'Rendered title does not match the staged title contract.', result.title));
  }
  if (result.document.h1Count !== 1 || normalizeComparison(result.document.h1) !== normalizeComparison(result.expected.h1)) {
    failures.push(failure('h1', 'Expected exactly one matching H1.', {
      expected: result.expected.h1,
      actual: result.document.h1,
      count: result.document.h1Count,
    }));
  }
  if (!result.document.sourceMarkerMatchesExpectedValue) {
    failures.push(failure('source-marker-value', 'Source-page marker is missing, duplicated, or has the wrong value.', {
      expected: result.expected.sourceMarkerValue,
      actual: result.document.sourceMarkerValue,
      count: result.document.sourceMarkerCount,
    }));
  }
  if (!normalizeComparison(result.document.sourceMarkerText).includes(normalizeComparison(result.expected.sourceMarker))) {
    failures.push(failure('source-marker-content', 'Source-page marker does not contain the expected source marker text.'));
  }
  if (result.document.phpErrors.length > 0) {
    failures.push(failure('php-error', 'Rendered PHP error output was detected.', result.document.phpErrors));
  }
  if (result.consoleErrors.length > 0) {
    failures.push(failure('console-error', 'Browser console errors were detected.', result.consoleErrors));
  }
  if (result.pageErrors.length > 0) {
    failures.push(failure('page-error', 'Uncaught page errors were detected.', result.pageErrors));
  }
  if (result.sameOriginFailures.length > 0) {
    failures.push(failure('same-origin-request', 'Failed same-origin requests were detected.', result.sameOriginFailures));
  }
  if (result.document.brokenImages.length > 0) {
    failures.push(failure('broken-image', 'Broken or incomplete images were detected.', result.document.brokenImages));
  }
  if (result.document.horizontalOverflowPixels > 1) {
    failures.push(failure('horizontal-overflow', 'Document width exceeds the viewport.', {
      pixels: result.document.horizontalOverflowPixels,
      documentWidth: result.document.documentWidth,
      viewportWidth: result.document.viewportWidth,
    }));
  }
  if (result.mutatingRequests.length > 0) {
    failures.push(failure('mutating-request', 'A mutating HTTP request was attempted and blocked.', result.mutatingRequests));
  }
  if (result.document.submissionObserved) {
    failures.push(failure('form-submission', 'A form submit event was observed and prevented.'));
  }
  if (result.expected.path === '/contacto') {
    const form = result.contactForm;
    if (!form
      || form.componentCount !== 1
      || form.formCount !== 1
      || !form.formVisible
      || form.method !== 'POST'
      || form.fieldCount < 1
      || form.visibleFieldCount < 1
      || form.submitControlCount < 1
      || form.visibleSubmitControlCount < 1
      || form.nativeAnchorRemainderCount !== 0
      || form.submissionObserved
    ) {
      failures.push(failure('native-contact-form', 'The native RED-CMS Contact form boundary is incomplete.', form));
    }
  } else if (result.document.formCountInMain !== 0) {
    failures.push(failure('unexpected-form', 'A non-contact migrated route contains a form.', result.document.formCountInMain));
  }
  if (result.menuInteraction && !result.menuInteraction.passed) {
    failures.push(failure('menu-interaction', 'Representative menu interaction failed.', result.menuInteraction));
  }
  if (result.screenshot.error !== '') {
    failures.push(failure('screenshot', 'Route screenshot capture failed.', result.screenshot.error));
  }
  return failures;
}

async function auditRoute({
  page,
  route,
  routeIndex,
  viewport,
  baseUrl,
  websiteTitle,
  outputDirectory,
  setActiveCapture,
}) {
  await page.goto('about:blank', { waitUntil: 'load' });
  const capture = {
    consoleErrors: [],
    pageErrors: [],
    sameOriginFailures: [],
    externalFailures: [],
    mutatingRequests: [],
  };
  setActiveCapture(capture);

  const target = new URL(route.path, baseUrl);
  let response = null;
  let navigationError = '';
  const startedAt = Date.now();
  try {
    response = await page.goto(target.href, { waitUntil: 'domcontentloaded', timeout: 45_000 });
  } catch (error) {
    navigationError = safeText(error.stack || error.message);
  }

  let loadReached = false;
  let networkIdleReached = false;
  try {
    await page.waitForLoadState('load', { timeout: 10_000 });
    loadReached = true;
  } catch {
    loadReached = false;
  }
  try {
    await page.waitForLoadState('networkidle', { timeout: 3_000 });
    networkIdleReached = true;
  } catch {
    networkIdleReached = false;
  }
  try {
    await warmDocument(page);
  } catch (error) {
    if (navigationError === '') {
      navigationError = `Document warm-up failed: ${safeText(error.message)}`;
    }
  }

  const redirectChain = [];
  if (response) {
    let redirect = response.request().redirectedFrom();
    while (redirect) {
      redirectChain.unshift(safeUrl(redirect.url()));
      redirect = redirect.redirectedFrom();
    }
  }
  const finalUrl = page.url();
  let finalUrlMatches = false;
  try {
    finalUrlMatches = urlWithoutHash(finalUrl) === urlWithoutHash(target.href);
  } catch {
    finalUrlMatches = false;
  }

  let documentInspection;
  try {
    documentInspection = await inspectDocument(page, route, viewport);
  } catch (error) {
    documentInspection = {
      title: '',
      h1Count: 0,
      h1: '',
      themeClassPresent: false,
      layoutClassPresent: false,
      mainCount: 0,
      sourceMarkerCount: 0,
      sourceMarkerValue: '',
      sourceMarkerText: '',
      sourceMarkerMatchesExpectedValue: false,
      phpErrors: [`Inspection failed: ${safeText(error.message)}`],
      brokenImages: [],
      horizontalOverflowPixels: 0,
      documentWidth: 0,
      viewportWidth: viewport.width,
      visibleMain: false,
      formCountInMain: 0,
      submissionObserved: false,
    };
  }

  let contactForm = null;
  if (route.path === '/contacto') {
    try {
      contactForm = await inspectContactForm(page);
    } catch (error) {
      contactForm = { inspectionError: safeText(error.message), inspectedWithoutSubmission: true };
    }
  }

  let menuInteraction = null;
  if (routeIndex === 0) {
    try {
      menuInteraction = await exerciseMenu(page, viewport.name);
    } catch (error) {
      menuInteraction = { passed: false, mode: viewport.name, reason: safeText(error.message) };
    }
  }

  const screenshotRelative = path.join(
    'screenshots',
    viewport.name,
    `${String(routeIndex).padStart(2, '0')}-${routeSlug(route)}.png`
  );
  const screenshotAbsolute = path.join(outputDirectory, screenshotRelative);
  let screenshotError = '';
  try {
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(100);
    await page.screenshot({ path: screenshotAbsolute, fullPage: true, animations: 'disabled' });
  } catch (error) {
    screenshotError = safeText(error.message);
  }
  await page.waitForTimeout(100);

  const expectedTitle = expectedDocumentTitle(route, websiteTitle);
  const titleContract = titleMatches(documentInspection.title, expectedTitle);
  const result = {
    viewport: viewport.name,
    expected: {
      path: route.path,
      sourcePath: route.sourcePath,
      alias: route.alias,
      canonicalAlias: route.canonicalAlias,
      kind: route.kind,
      section: route.section,
      positions: route.positions,
      layout: route.layout,
      title: expectedTitle,
      stagedRouteTitle: route.title,
      h1: route.h1,
      sourceMarker: route.sourceMarker,
      sourceMarkerValue: route.alias === '' ? 'home' : route.alias,
    },
    navigation: {
      expectedUrl: target.href,
      finalUrl: safeUrl(finalUrl),
      finalUrlMatches,
      status: response?.status() ?? null,
      redirected: redirectChain.length > 0,
      redirectChain,
      error: navigationError,
      loadReached,
      networkIdleReached,
      durationMs: Date.now() - startedAt,
    },
    title: {
      expected: expectedTitle,
      actual: documentInspection.title,
      matches: titleContract.matches,
      matchMode: titleContract.mode,
    },
    document: {
      ...documentInspection,
      brokenImages: documentInspection.brokenImages.map((image) => ({
        ...image,
        src: safeUrl(image.src),
      })),
    },
    contactForm,
    menuInteraction,
    consoleErrors: capture.consoleErrors,
    pageErrors: capture.pageErrors,
    sameOriginFailures: capture.sameOriginFailures,
    externalFailures: capture.externalFailures,
    mutatingRequests: capture.mutatingRequests,
    screenshot: {
      path: screenshotRelative.split(path.sep).join('/'),
      error: screenshotError,
    },
    failures: [],
    passed: false,
  };
  result.failures = buildFailures(result);
  result.passed = result.failures.length === 0;
  setActiveCapture(null);
  return result;
}

async function main() {
  const options = parseArguments(process.argv.slice(2));
  if (options.help) {
    process.stdout.write(usage());
    return;
  }

  const state = await loadAndValidateState(options.stateFile);
  options.baseUrl = validateAndNormalizeBaseUrl(options.baseUrl, state);
  if (options.manifest === '') {
    options.manifest = path.join(state.WEBROOT, MANIFEST_RELATIVE_PATH);
  }
  if (options.mapping === '') {
    options.mapping = path.join(state.WEBROOT, MAPPING_RELATIVE_PATH);
  }
  await requireRegularFile(options.manifest, null, 'Browser QA route manifest');
  await requireRegularFile(options.mapping, null, 'Browser QA pagewise mapping');
  const manifestBuffer = await readFile(options.manifest);
  const manifestSha256 = sha256(manifestBuffer);
  if (manifestSha256 !== state.MANIFEST_SHA256) {
    throw new Error('Browser QA route manifest does not match the recorded disposable manifest SHA-256.');
  }
  const manifest = JSON.parse(manifestBuffer.toString('utf8'));
  validateManifest(manifest);
  const mappingBuffer = await readFile(options.mapping);
  const mappingSha256 = sha256(mappingBuffer);
  const mapping = JSON.parse(mappingBuffer.toString('utf8'));
  validatePagewiseMapping(mapping, manifest, manifestSha256);
  const canonicalRoutes = buildCanonicalRoutes(manifest, mapping);
  await mkdir(options.outputDir, { recursive: true });
  for (const viewport of VIEWPORTS) {
    await mkdir(path.join(options.outputDir, 'screenshots', viewport.name), { recursive: true });
  }

  const playwright = loadPlaywright(options.playwrightModule);
  const chromeExecutable = await findChromeExecutable(options.chromeExecutable);
  const launchOptions = { headless: options.headless };
  if (chromeExecutable !== '') {
    launchOptions.executablePath = chromeExecutable;
  }

  const browser = await playwright.chromium.launch(launchOptions);
  const baseOrigin = new URL(options.baseUrl).origin;
  const report = {
    schemaVersion: 2,
    audit: 'adriana-granobles-v4-route-browser-qa',
    generatedAt: new Date().toISOString(),
    baseUrl: options.baseUrl,
    state: {
      path: options.stateFile,
      sha256: state.stateSha256,
      version: state.STATE_VERSION,
      marker: state.STATE_MARKER,
      database: state.DATABASE,
      primaryDatabase: state.PRIMARY_DATABASE,
      runRoot: state.RUN_ROOT,
      webroot: state.WEBROOT,
      phpPid: state.phpPid,
      port: state.port,
      primarySnapshot: state.PRIMARY_SNAPSHOT,
      manifestSha256: state.MANIFEST_SHA256,
      createdAt: state.createdAt,
    },
    manifest: {
      path: options.manifest,
      sha256: manifestSha256,
      migrationId: manifest.migrationId,
      expectedCounts: manifest.counts,
    },
    mapping: {
      path: options.mapping,
      sha256: mappingSha256,
      migrationId: mapping.migrationId,
      canonicalRouteCount: canonicalRoutes.length,
      sourceSectionCount: canonicalRoutes.reduce((total, route) => total + route.positions.length, 0),
      navigationRootCount: mapping.navigation.length,
      navigationRowCount: mapping.navigation.reduce(
        (total, item) => total + 1 + (Array.isArray(item.children) ? item.children.length : 0),
        0
      ),
    },
    runtime: {
      node: process.version,
      platform: process.platform,
      playwrightModule: playwright.module,
      chromeExecutable: chromeExecutable || 'playwright-default',
      headless: options.headless,
      reducedMotion: 'reduce',
    },
    policy: {
      routeOrder: 'pagewise-canonical-mapping-order-sequential',
      exactRouteCount: 28,
      legacyRedirectCount: 28,
      viewports: VIEWPORTS,
      expectedThemeClass: EXPECTED_THEME_CLASS,
      titleMatch: 'exact home title or exact non-home Website_Title prefix after legacy article hyphen and case normalization',
      formSubmissions: 'prevented and not initiated',
      mutatingMethods: Array.from(MUTATING_METHODS),
      externalFailures: 'reported separately; not silently discarded',
    },
    viewports: [],
    legacyRedirects: [],
    externalFailures: [],
    mutatingRequests: [],
    summary: null,
  };

  try {
    const redirectContext = await playwright.request.newContext({
      baseURL: options.baseUrl,
      ignoreHTTPSErrors: false,
    });
    try {
      report.legacyRedirects = await auditLegacyRedirects(
        redirectContext,
        canonicalRoutes,
        options.baseUrl
      );
    } finally {
      await redirectContext.dispose();
    }

    for (const viewport of VIEWPORTS) {
      const context = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        reducedMotion: 'reduce',
        serviceWorkers: 'block',
        ignoreHTTPSErrors: false,
      });
      let activeCapture = null;
      const setActiveCapture = (capture) => {
        activeCapture = capture;
      };

      await context.addInitScript(() => {
        window.__redcmsQaFormSubmitObserved = false;
        document.addEventListener(
          'submit',
          (event) => {
            window.__redcmsQaFormSubmitObserved = true;
            event.preventDefault();
          },
          true
        );
      });
      await context.route('**/*', async (routeHandler) => {
        const method = routeHandler.request().method().toUpperCase();
        if (MUTATING_METHODS.has(method)) {
          if (activeCapture) {
            activeCapture.mutatingRequests.push(
              requestEvidence(routeHandler.request(), { action: 'blocked-by-qa' })
            );
          }
          await routeHandler.abort('blockedbyclient');
          return;
        }
        await routeHandler.continue();
      });

      const page = await context.newPage();
      page.on('console', (message) => {
        if (!activeCapture || !['error', 'assert'].includes(message.type())) return;
        activeCapture.consoleErrors.push({
          type: message.type(),
          text: safeText(message.text()),
          location: {
            url: safeUrl(message.location().url || ''),
            lineNumber: message.location().lineNumber,
            columnNumber: message.location().columnNumber,
          },
        });
      });
      page.on('pageerror', (error) => {
        if (!activeCapture) return;
        activeCapture.pageErrors.push(safeText(error.stack || error.message));
      });
      page.on('requestfailed', (request) => {
        if (!activeCapture) return;
        const evidence = requestEvidence(request, {
          failure: safeText(request.failure()?.errorText || 'request failed'),
        });
        if (isSameOrigin(request.url(), baseOrigin)) {
          activeCapture.sameOriginFailures.push(evidence);
        } else {
          activeCapture.externalFailures.push({ ...evidence, kind: 'requestfailed' });
        }
      });
      page.on('response', (response) => {
        if (!activeCapture || response.status() < 400) return;
        const evidence = requestEvidence(response.request(), {
          status: response.status(),
          statusText: response.statusText(),
        });
        if (isSameOrigin(response.url(), baseOrigin)) {
          activeCapture.sameOriginFailures.push(evidence);
        } else {
          activeCapture.externalFailures.push({ ...evidence, kind: 'http-status' });
        }
      });

      const viewportReport = {
        name: viewport.name,
        width: viewport.width,
        height: viewport.height,
        routes: [],
      };
      for (const [routeIndex, route] of canonicalRoutes.entries()) {
        const result = await auditRoute({
          page,
          route,
          routeIndex,
          viewport,
          baseUrl: options.baseUrl,
          websiteTitle: manifest.shell.websiteTitle,
          outputDirectory: options.outputDir,
          setActiveCapture,
        });
        viewportReport.routes.push(result);
        process.stderr.write(
          `[adriana-route-qa] ${viewport.name} ${routeIndex + 1}/28 ${route.path}: ${result.passed ? 'PASS' : 'FAIL'}\n`
        );
        for (const externalFailure of result.externalFailures) {
          report.externalFailures.push({
            viewport: viewport.name,
            path: route.path,
            ...externalFailure,
          });
        }
        for (const mutatingRequest of result.mutatingRequests) {
          report.mutatingRequests.push({
            viewport: viewport.name,
            path: route.path,
            ...mutatingRequest,
          });
        }
      }
      report.viewports.push(viewportReport);
      await context.close();
    }
  } finally {
    await browser.close();
  }

  const allResults = report.viewports.flatMap((viewport) => viewport.routes);
  const failedResults = allResults.filter((result) => !result.passed);
  const failedLegacyRedirects = report.legacyRedirects.filter((result) => !result.passed);
  report.summary = {
    expectedRouteChecks: 56,
    completedRouteChecks: allResults.length,
    passedRouteChecks: allResults.length - failedResults.length,
    failedRouteChecks: failedResults.length,
    failureCount: failedResults.reduce((total, result) => total + result.failures.length, 0),
    externalFailureCount: report.externalFailures.length,
    mutatingRequestCount: report.mutatingRequests.length,
    screenshotCount: allResults.filter((result) => result.screenshot.error === '').length,
    expectedLegacyRedirectChecks: 28,
    passedLegacyRedirectChecks: report.legacyRedirects.length - failedLegacyRedirects.length,
    failedLegacyRedirectChecks: failedLegacyRedirects.length,
    desktopMenuInteractionPassed:
      report.viewports.find((viewport) => viewport.name === 'desktop')?.routes[0]?.menuInteraction?.passed === true,
    mobileMenuInteractionPassed:
      report.viewports.find((viewport) => viewport.name === 'mobile')?.routes[0]?.menuInteraction?.passed === true,
    contactFormChecksPassed: report.viewports
      .map((viewport) => viewport.routes.find((result) => result.expected.path === '/contacto'))
      .every((result) => result?.failures.some((item) => item.code === 'native-contact-form') === false),
    passed:
      allResults.length === 56
      && failedResults.length === 0
      && report.legacyRedirects.length === 28
      && failedLegacyRedirects.length === 0
      && report.mutatingRequests.length === 0,
  };

  await writeFile(
    path.join(options.outputDir, 'external-failures.json'),
    `${JSON.stringify(report.externalFailures, null, 2)}\n`,
    'utf8'
  );
  await writeFile(
    path.join(options.outputDir, 'report.json'),
    `${JSON.stringify(report, null, 2)}\n`,
    'utf8'
  );

  process.stdout.write(`${JSON.stringify(report.summary, null, 2)}\n`);
  if (!report.summary.passed) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  process.stderr.write(`Adriana route browser QA failed: ${safeText(error.stack || error.message)}\n`);
  process.exitCode = 1;
});
