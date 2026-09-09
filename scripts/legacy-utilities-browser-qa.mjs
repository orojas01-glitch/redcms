#!/usr/bin/env node
import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
import {createServer} from 'node:http';
import {readFile, mkdir, writeFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
import os from 'node:os';
const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const require = createRequire(import.meta.url);
let pw;
for (const candidate of [process.env.RED_PLAYWRIGHT_MODULE,'playwright',path.join(os.homedir(),'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')].filter(Boolean)) { try { pw=require(candidate); break; } catch {} }
if (!pw) throw new Error('Playwright unavailable');
const requests=[];
const assets = new Set(['js/jquery-3.7.1.min.js','js/jquery.mobilemenu.js','js/superfish.js','js/tm-scripts.js','js/jquery.easing.1.3.js','admin/assets/js/jquery.filedrop.js','admin/assets/js/jquery.filedrop2.js','admin/assets/js/jquery.filedrop3.js']);
const server=createServer(async(req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(req.method==='POST' && url.pathname.startsWith('/upload/')){
        const parts=[]; for await(const part of req) parts.push(part);
        requests.push({path:url.pathname,csrf:req.headers['x-csrf-token'],body:Buffer.concat(parts).toString('utf8'),type:req.headers['content-type']});
        setTimeout(()=>{res.writeHead(url.pathname==='/upload/failure'?403:200,{'Content-Type':'application/json'});res.end(url.pathname==='/upload/invalid'?'not json':JSON.stringify({status:'ok'}));},30);return;
    }
    if(url.pathname==='/child') { res.end('Synthetic navigation target'); return; }
    if(url.pathname==='/') {res.setHeader('Content-Type','text/html');res.end('<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1"><title>Legacy utility fixture</title><body><h1>RED-CMS legacy utility checks</h1><div id="copyright-year"></div></body>');return;}
    const file=url.pathname.slice(1);
    if(!assets.has(file)){res.writeHead(404);res.end();return;}
    res.setHeader('Content-Type','text/javascript');res.end(process.env.RED_LEGACY_BASELINE_REF ? execFileSync('git',['show',process.env.RED_LEGACY_BASELINE_REF+':'+file],{cwd:root}) : await readFile(path.join(root,file)));
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const origin='http://127.0.0.1:'+server.address().port;
const browser=await pw.chromium.launch({headless:true,executablePath:process.env.RED_CHROME_EXECUTABLE || (process.platform==='darwin'?'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome':undefined)});
const results=[];
function check(name,pass){results.push({name,pass:Boolean(pass)});}
try {
    for(const [device,ua] of [['desktop',undefined],['iphone','Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'],['android','Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/128.0 Mobile Safari/537.36']]){
        const ctx=await browser.newContext({viewport:device==='desktop'?{width:1280,height:900}:{width:390,height:844},...(ua?{userAgent:ua}: {})});
        const page=await ctx.newPage(),errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        await page.goto(origin);
        await page.addScriptTag({url:origin+'/js/jquery-3.7.1.min.js'});
        await page.evaluate(()=>{
            const nav=document.createElement('ul');nav.className='sf-menu';nav.setAttribute('aria-label','Site navigation');
            nav.innerHTML='<li><a href="/about">About</a><ul><li><a href="/child">Child</a></li></ul></li><li><a href="javascript:window.marker=1">Unsafe URL</a></li>';
            const a=document.createElement('a');a.href='/literal';a.textContent='<script>window.labelExecuted=1</script>';
            const li=document.createElement('li');li.append(a);nav.append(li);document.body.append(nav);
            if(/Android/.test(navigator.userAgent)) window.orientation=0;
        });
        await page.addScriptTag({url:origin+'/js/superfish.js'});
        await page.addScriptTag({url:origin+'/js/jquery.mobilemenu.js'});
        await page.addScriptTag({url:origin+'/js/tm-scripts.js'});
        await page.waitForTimeout(150);
        const state=await page.evaluate(()=>({
            menu:document.querySelectorAll('select.select-menu').length,
            unsafe:[...document.querySelectorAll('select option')].some(o=>/^javascript:/i.test(o.value)),
            literal:[...document.querySelectorAll('select option')].some(o=>o.textContent.includes('<script>window.labelExecuted=1</script>')) && !window.labelExecuted,
            viewport:[...document.querySelectorAll('meta[name="viewport"]')].map(m=>m.content),
            year:document.querySelector('#copyright-year').textContent,
            yearGlobal:window.currentYear,
            superfish:document.querySelector('.sf-menu').classList.contains('sf-js-enabled')
        }));
        check(device+' literal labels',state.literal);check(device+' safe navigation URLs',!state.unsafe);
        check(device+' single zoomable viewport',state.viewport.length===1&&!/user-scalable\s*=\s*(0|no)|maximum-scale=1(?:[,.\s]|$)/.test(state.viewport[0]));
        check(device+' year/menu initialization',state.year===String(new Date().getFullYear())&&state.yearGlobal===new Date().getFullYear()&&state.menu===1&&state.superfish);
        check(device+' jQuery 3 compatibility',errors.length===0);
        await page.locator('.sf-menu > li > a').first().focus();
        check(device+' keyboard opens submenu', await page.locator('.sf-menu > li').first().evaluate(e=>e.classList.contains('sfHover')));
        await page.evaluate(()=>jQuery('.sf-menu').mobileMenu());
        check(device+' repeat initialization',await page.locator('select.select-menu').count()===1);
        if (await page.locator('select.select-menu').count() === 1) {
            await page.selectOption('select.select-menu', origin+'/child');
            await page.waitForURL(origin+'/child');
            check(device+' selection preserves URL',page.url()===origin+'/child');
        } else check(device+' selection preserves URL',false);
        await ctx.close();
    }
    const ctx=await browser.newContext(),page=await ctx.newPage();const errors=[],outbound=[];
    page.on('pageerror',e=>errors.push(e.message));
    await page.route('**/*',route=>{if(route.request().url().startsWith(origin+'/'))route.continue();else{outbound.push(route.request().url());route.abort();}});
    await page.goto(origin);
    for(const asset of ['js/jquery-3.7.1.min.js','admin/assets/js/jquery.filedrop.js','admin/assets/js/jquery.filedrop2.js','admin/assets/js/jquery.filedrop3.js']) await page.addScriptTag({url:origin+'/'+asset});
    await page.evaluate(()=>{
        window.RED_CSRF_TOKEN='synthetic-csrf-fixture';window.finished=[];window.failed=[];window.completed=[];window.rejected=[];
        window.mount=(id,plugin,url,extra={})=>{
            let box=document.getElementById(id);if(!box){box=document.createElement('div');box.id=id;document.body.append(box);}
            jQuery(box)[plugin]({url,paramname:'pic',data:{label:id},...extra,uploadFinished:(i,file)=>finished.push([id,file.name]),uploadFailed:()=>failed.push(id),afterAll:()=>completed.push(id),error:e=>rejected.push([id,e])});
        };
        window.drop=(id,name,content)=>{const dt=new DataTransfer();dt.items.add(new File([content],name,{type:'text/plain'}));document.getElementById(id).dispatchEvent(new DragEvent('drop',{dataTransfer:dt,bubbles:true,cancelable:true}));};
        mount('a','filedrop','/upload/a');mount('b','filedrop','/upload/b');
        drop('a','résumé.txt','AAAA');drop('b','other.txt','BBBB');
    });
    await page.waitForTimeout(500);
    check('independent upload targets and bytes',requests.some(r=>r.path==='/upload/a'&&r.body.includes('AAAA'))&&requests.some(r=>r.path==='/upload/b'&&r.body.includes('BBBB')));
    check('UTF-8 filename and CSRF',requests.some(r=>r.body.includes('résumé.txt'))&&requests.every(r=>r.csrf==='synthetic-csrf-fixture'));
    check('instance callbacks',await page.evaluate(()=>finished.some(x=>x[0]==='a'&&x[1]==='résumé.txt')&&finished.some(x=>x[0]==='b'&&x[1]==='other.txt')));
    await page.evaluate(()=>{mount('c','filedrop2','/upload/c');mount('d','filedrop3','/upload/d');drop('c','c.txt','CCCC');drop('d','d.txt','DDDD');mount('failure','filedrop','/upload/failure');drop('failure','f.txt','FAIL');});
    await page.waitForTimeout(300);
    check('legacy numbered APIs retained',requests.some(r=>r.path==='/upload/c')&&requests.some(r=>r.path==='/upload/d'));
    check('HTTP failure callback',await page.evaluate(()=>failed.includes('failure')&&!finished.some(x=>x[0]==='failure')));
    await page.evaluate(()=>{mount('invalid','filedrop','/upload/invalid');drop('invalid','i.txt','BADJSON');});
    await page.waitForTimeout(200);
    check('invalid JSON is failure',await page.evaluate(()=>failed.includes('invalid')&&!finished.some(x=>x[0]==='invalid')));
    await page.evaluate(()=>{mount('repeat','filedrop','/upload/repeat');mount('repeat','filedrop','/upload/repeat');drop('repeat','r.txt','ONCE');});
    await page.waitForTimeout(200);
    check('repeat bind sends once',requests.filter(r=>r.path==='/upload/repeat').length===1);
    await page.evaluate(()=>{mount('foreign','filedrop','https://example.invalid/upload');drop('foreign','private.txt','synthetic-only');});
    await page.waitForTimeout(150);
    check('foreign upload refused before network',outbound.length===0);
    await page.evaluate(()=>{
        mount('busy','filedrop','/upload/busy');drop('busy','first.txt','FIRST');drop('busy','second.txt','SECOND');
        mount('limit','filedrop','/upload/limit',{maxfilesize:0});drop('limit','large.txt','LARGE');
        mount('rename','filedrop','/upload/rename',{rename:()=> 'renamed.txt',data:{dynamic:()=> 'dynamic-value'}});drop('rename','original.txt','RENAME');
    });
    await page.waitForTimeout(250);
    check('overlapping drop refuses second batch',requests.filter(r=>r.path==='/upload/busy').length===1&&await page.evaluate(()=>rejected.some(r=>r[0]==='busy'&&r[1]==='UploadInProgress')));
    check('oversized file refused before transport',!requests.some(r=>r.path==='/upload/limit'));
    check('rename and dynamic form fields',requests.some(r=>r.path==='/upload/rename'&&r.body.includes('renamed.txt')&&r.body.includes('dynamic-value')));
    check('afterAll once per batch',await page.evaluate(()=>completed.filter(x=>x==='busy').length===1&&completed.filter(x=>x==='rename').length===1));
    await page.addScriptTag({url:origin+'/js/jquery.easing.1.3.js'});
    check('retained easing works with jQuery 3 animation',await page.evaluate(()=>new Promise(resolve=>{
        const box=document.createElement('div');box.style.width='0px';document.body.append(box);
        jQuery(box).animate({width:'100px'},{duration:60,easing:'easeInOutQuad',complete:()=>resolve(Math.abs(parseFloat(box.style.width)-100)<.01)});
    })));
    check('upload runtime clean',errors.length===0);
    await ctx.close();
} finally {await browser.close();await new Promise(r=>server.close(r));}
const output=process.env.RED_LEGACY_QA_OUTPUT || '/private/tmp/redcms-legacy-provenance';await mkdir(output,{recursive:true});
await writeFile(path.join(output,'browser-results.json'),JSON.stringify(results,null,2));
for(const r of results) console.log((r.pass?'PASS ':'FAIL ')+r.name);
console.log(`${results.filter(r=>r.pass).length}/${results.length} legacy utility browser checks passed.`);
if(results.some(r=>!r.pass))process.exitCode=1;
