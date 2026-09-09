#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createHash} from 'node:crypto';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
import vm from 'node:vm';
const root=path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const jquery={easing:{swing:x=>.5-Math.cos(x*Math.PI)/2},extend:(target,source)=>Object.assign(target,source)};
vm.runInNewContext(await readFile(path.join(root,'js/jquery.easing.1.3.js'),'utf8'),{jQuery:jquery});
let checks=0;
for(const [name,fn] of Object.entries(jquery.easing).filter(([n,f])=>/^ease|^swing$/.test(n)&&typeof f==='function')){
    for(const x of [0,.25,.5,.75,1]){
        const result=fn(x);assert(Number.isFinite(result),name+' supports jQuery 3 progress-only calls');
        assert(Math.abs(fn(x,x*100,10,20,100)-(10+20*result))<1e-9,name+' preserves legacy time/base/change/duration calls');checks++;
    }
}
let shared;
for(const name of ['filedrop','filedrop2','filedrop3']){
    const source=await readFile(path.join(root,'admin/assets/js/jquery.'+name+'.js'),'utf8');
    const normalized=source.replace("var pluginName = '"+name+"';","var pluginName = 'filedrop';");
    if(shared)assert.equal(normalized,shared,'Numbered compatibility helpers must not drift');shared=normalized;
    assert(!/sendAsBinary|readAsBinaryString|eval\(/.test(source));checks++;
}
const glue=await readFile(path.join(root,'js/tm-scripts.js'),'utf8');
assert(!/document\.write|user-scalable|maximum-scale|\.toggle\(/.test(glue),'Theme initialization preserves viewport and avoids removed event API');checks++;
{
    const manifest=JSON.parse(await readFile(path.join(root,'release/legacy-utilities-manifest.json'),'utf8'));
    for(const entry of manifest.files){
        const bytes=await readFile(path.join(root,entry.path));
        assert.equal(createHash('sha256').update(bytes).digest('hex'),entry.sha256,'Provenance file integrity: '+entry.path);checks++;
        if(entry.upstreamPrefixBytes){assert.equal(createHash('sha256').update(bytes.subarray(0,entry.upstreamPrefixBytes)).digest('hex'),entry.upstreamSha256,'Original upstream bytes: '+entry.path);checks++;}
    }
}
console.log(`Legacy utility self-test passed: ${checks} math, API, drift and provenance checks.`);
