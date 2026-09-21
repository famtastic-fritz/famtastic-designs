import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
const temp=fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(),'anderlye-showcase-test-')));
const base=path.join(temp,'private');const live=path.join(temp,'public/work/anderlye');
const gnuMv=process.platform==='darwin'?'/opt/homebrew/bin/gmv':'/bin/mv';
assert.ok(fs.existsSync(gnuMv),'GNU mv required for the exact no-clobber primitive');
const source=fs.readFileSync(new URL('./anderlye-showcase-remote.php',import.meta.url),'utf8').replaceAll('/home/xrdj7j99xhzt/public_html/work/anderlye',live).replaceAll('/home/xrdj7j99xhzt/public_html',path.join(temp,'public')).replaceAll('/home/xrdj7j99xhzt/deploy/anderlye-showcase',base).replaceAll('/bin/mv',gnuMv);
const sha='a'.repeat(40);
const content={'assets/hero-steel-mobile.webp':'mobile fixture','assets/hero-steel.webp':'desktop fixture','index.html':'<h1>Fixture</h1>','style.css':'body{margin:0}'};
const files=Object.fromEntries(Object.entries(content).map(([name,value])=>[name,createHash('sha256').update(value).digest('hex')]));
const manifest={source_sha:sha,host:'famtasticdesigns.com',route:'/work/anderlye/',files};
const encoded=Buffer.from(JSON.stringify(manifest)).toString('base64');
const run=mode=>spawnSync('php',['--',mode,sha,encoded],{input:source,encoding:'utf8'});
function succeeds(mode){const result=run(mode);assert.equal(result.status,0,result.stderr);return JSON.parse(result.stdout);}
function refuses(mode){assert.notEqual(run(mode).status,0,`${mode} should refuse`);}
fs.mkdirSync(path.dirname(live),{recursive:true});fs.writeFileSync(path.join(temp,'public/unrelated.txt'),'preserve');
fs.mkdirSync(path.join(temp,'public/assets'));fs.writeFileSync(path.join(temp,'public/assets/main.js'),'unchanged');fs.writeFileSync(path.join(temp,'public/.frontend-release'),'existing');
try{
  assert.equal(succeeds('preflight').target_absent,true);assert.equal(fs.existsSync(base),false);console.log('PASS read-only preflight creates nothing');
  succeeds('prepare');assert.equal(fs.existsSync(live),false);console.log('PASS private preparation leaves public route absent');
  refuses('activate');console.log('PASS missing payload cannot publish');
  for(const [name,value] of Object.entries(content))fs.writeFileSync(path.join(base,sha,'payload',name),value);
  fs.mkdirSync(live);fs.writeFileSync(path.join(live,'foreign.txt'),'do not overwrite');refuses('activate');assert.equal(fs.readFileSync(path.join(live,'foreign.txt'),'utf8'),'do not overwrite');fs.rmSync(live,{recursive:true});console.log('PASS existing public target is preserved');
  assert.equal(succeeds('activate').status,'promoted');assert.equal(fs.readFileSync(path.join(live,'index.html'),'utf8'),content['index.html']);console.log('PASS exact files promote through GNU no-clobber move');
  fs.writeFileSync(path.join(live,'foreign.txt'),'later work');refuses('rollback');assert.equal(fs.readFileSync(path.join(live,'foreign.txt'),'utf8'),'later work');fs.unlinkSync(path.join(live,'foreign.txt'));console.log('PASS rollback refuses changed public inventory');
  assert.equal(succeeds('rollback').status,'rolled_back');assert.equal(fs.existsSync(live),false);assert.equal(fs.readFileSync(path.join(temp,'public/unrelated.txt'),'utf8'),'preserve');assert.equal(fs.readFileSync(path.join(base,sha,'failed-public/index.html'),'utf8'),content['index.html']);console.log('PASS scoped rollback retains exact candidate privately and preserves unrelated file');
  refuses('prepare');console.log('PASS attempted source SHA cannot be replayed over its private stage');
}finally{fs.rmSync(temp,{recursive:true});}
console.log('8 isolated local filesystem checks passed; no provider calls or live writes.');
