#!/usr/bin/env node
// Synthetic two-origin browser enforcement; no repository webroot or external traffic.
import {createServer} from 'node:http';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
import path from 'node:path';
import os from 'node:os';
import {fileURLToPath} from 'node:url';
const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const require = createRequire(import.meta.url);
let playwright;
for (const candidate of [process.env.RED_PLAYWRIGHT_MODULE, 'playwright', path.join(os.homedir(), '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')].filter(Boolean)) {
    try { playwright = require(candidate); break; } catch {}
}
if (!playwright) throw new Error('Playwright unavailable; no automatic installation.');
const php = process.env.RED_PHP_BIN || 'php';
const policy = execFileSync(php, ['-r', 'require $argv[1]; echo red_csp_policy();', path.join(root, 'includes/csp_helpers.php')], {encoding:'utf8'});
const strict = execFileSync(php, ['-r', 'require $argv[1]; echo red_csp_policy([], true);', path.join(root, 'includes/csp_helpers.php')], {encoding:'utf8'});
let foreignRequests = 0;
const foreign = createServer((req,res) => {
    foreignRequests++;
    if (req.url === '/embed') {
        res.setHeader('Content-Type', 'text/html');
        res.end('<!doctype html><title>Foreign parent</title><iframe src="'+origin+'/framed"></iframe>');
        return;
    }
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Content-Type', req.url === '/foreign.js' ? 'text/javascript' : 'text/plain');
    res.end(req.url === '/foreign.js' ? 'window.foreignExecuted=true;' : 'synthetic');
});
await new Promise(resolve => foreign.listen(0,'127.0.0.1',resolve));
const foreignOrigin = 'http://127.0.0.1:' + foreign.address().port;
const app = createServer((req,res) => {
    if (req.url === '/app.js') {
        res.setHeader('Content-Type','text/javascript');
        res.end(`window.violations=[]; document.addEventListener('securitypolicyviolation',e=>violations.push({directive:e.effectiveDirective, disposition:e.disposition,inline:e.blockedURI==='inline'}));
const inline=document.createElement('script'); inline.textContent='window.inlineLate=true'; document.head.append(inline);
window.localExecuted=true;
try { window.eval('window.evalExecuted=true'); } catch { window.evalBlocked=true; }
fetch('/ping').then(r=>r.text()).then(t=>window.localFetch=t==='ok');
fetch('${foreignOrigin}/connect').then(()=>window.foreignFetch=true).catch(()=>window.foreignFetch=false);
const script=document.createElement('script'); script.src='${foreignOrigin}/foreign.js'; document.head.append(script);
const base=document.createElement('base'); base.href='${foreignOrigin}/'; document.head.append(base);
const object=document.createElement('object'); object.data='${foreignOrigin}/object'; document.body.append(object);
document.getElementById('submit').onclick=()=>document.getElementById('form').submit();
window.started=true;`);
        return;
    }
    if (req.url === '/ping') { res.end('ok'); return; }
    const mode = req.url === '/report' ? 'report' : 'enforce';
    res.setHeader('Content-Type','text/html');
    res.setHeader(mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', mode === 'enforce' ? policy : strict);
    res.end(`<!doctype html><title>CSP fixture</title><script src="/app.js" defer></script><script>window.inlineExecuted=true;</script><style>body{color:rgb(1,2,3)}</style><body><form id="form" action="${foreignOrigin}/form" method="post" target="result"><input name="synthetic" value="test"></form><iframe name="result"></iframe><button id="submit">Test form</button></body>`);
});
await new Promise(resolve => app.listen(0,'127.0.0.1',resolve));
const origin = 'http://127.0.0.1:' + app.address().port;
let browser, checks=0;
function check(value,label) { if (!value) throw new Error(label); checks++; }
try {
    browser = await playwright.chromium.launch({headless:true, executablePath:process.env.RED_CHROME_EXECUTABLE || (process.platform === 'darwin' ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : undefined)});
    for (const mode of ['enforce','report']) {
        const context=await browser.newContext(); const page=await context.newPage();
        await page.route('**/*',route=> {
            const url=route.request().url();
            if (url.startsWith(origin+'/') || url.startsWith(foreignOrigin+'/')) return route.continue();
            return route.abort();
        });
        foreignRequests=0;
        await page.goto(origin+'/'+mode);
        await page.waitForFunction(()=>window.started && window.localFetch && typeof window.foreignFetch==='boolean');
        if (mode==='report') await page.waitForFunction(()=>window.foreignExecuted);
        const result=await page.evaluate(()=>({local:window.localExecuted,inline:window.inlineExecuted && window.inlineLate,evalBlocked:window.evalBlocked,foreign:window.foreignExecuted,fetch:window.foreignFetch,base:document.baseURI,style:getComputedStyle(document.body).color,violations:window.violations}));
        check(result.local && result.inline && result.style==='rgb(1, 2, 3)','Existing local and inline behavior');
        if (mode==='enforce') {
            check(result.evalBlocked && !result.foreign && !result.fetch,'Execution and connection blocking');
            check(result.base.startsWith(origin+'/'),'Foreign base blocked');
            await page.click('#submit');
            await page.waitForFunction(()=>violations.some(v=>v.directive==='form-action'));
            check(foreignRequests===0,'No blocked request reaches second origin');
            const directives=await page.evaluate(()=>violations.map(v=>v.directive));
            for (const directive of ['script-src-elem','script-src','connect-src','base-uri','object-src','form-action']) check(directives.includes(directive),directive+' enforced');
        } else {
            check(result.foreign && result.fetch && !result.evalBlocked,'Report mode does not block');
            check(result.violations.some(v=>v.disposition==='report'),'Reports observable without collector');
            check(result.violations.some(v=>v.disposition==='report' && v.inline),'Strict report detects remaining inline scripts');
        }
        await context.close();
    }
    const page=await browser.newPage();
    const framed=page.waitForEvent('console',{predicate:message=>message.text().includes('frame-ancestors'),timeout:10000});
    await page.goto(foreignOrigin+'/embed');
    check(Boolean(await framed),'Cross-origin framing blocked');
    await page.close();
    console.log(`CSP browser QA passed: ${checks} assertions; enforcement and report-only verified on loopback.`);
} finally {
    if(browser) await browser.close();
    await Promise.all([new Promise(resolve=>app.close(resolve)),new Promise(resolve=>foreign.close(resolve))]);
}
