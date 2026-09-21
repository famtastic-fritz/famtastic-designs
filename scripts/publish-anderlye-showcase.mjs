import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { approvedLogo, creatorCreditHtml } from './creator-credit.mjs';

// Task-authorized exception: one initially absent static directory, preserving
// every existing frontend byte. This never replaces the canonical broad deployer.
const REPO=path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const ROOT=path.join(REPO,'frontend/public/work/anderlye');
const SSH='xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net';
const PUBLIC='/home/xrdj7j99xhzt/public_html/work/anderlye';
const BASE='/home/xrdj7j99xhzt/deploy/anderlye-showcase';
const FILES=['assets/hero-steel-mobile.webp','assets/hero-steel.webp','index.html','style.css'];
const args=process.argv.slice(2);
assert.ok(args.length===0||(args.length===1&&['--dry-run','--apply'].includes(args[0])),'Use --dry-run or --apply');
const apply=args[0]==='--apply';
const hash=value=>createHash('sha256').update(value).digest('hex');
const git=(...arguments_)=>execFileSync('git',arguments_,{cwd:REPO,encoding:'utf8'}).trim();
const quote=value=>`'${value.replaceAll("'","'\\''")}'`;
assert.equal(fs.realpathSync(git('rev-parse','--show-toplevel')),fs.realpathSync(REPO));
assert.match(git('remote','get-url','origin'),/^(?:https:\/\/github\.com\/|git@github\.com:)famtastic-fritz\/famtastic-designs(?:\.git)?$/);
const sha=git('rev-parse','HEAD');const clean=git('status','--porcelain')==='';
const branch=git('branch','--show-current');assert.equal(branch,'codex/anderlye-showcase','Scoped release requires its dedicated source branch');
const remoteMain=git('ls-remote','origin','refs/heads/main').split(/\s/)[0];
const pushedSha=git('ls-remote','origin',`refs/heads/${branch}`).split(/\s/)[0];
const containsCurrentMain=git('merge-base',remoteMain,sha)===remoteMain;
const files=Object.fromEntries(FILES.map(relative=>{const file=path.join(ROOT,relative);assert.ok(fs.lstatSync(file).isFile()&&!fs.lstatSync(file).isSymbolicLink());return[relative,hash(fs.readFileSync(file))];}));
const html=fs.readFileSync(path.join(ROOT,'index.html'),'utf8');
assert.ok(html.includes(creatorCreditHtml()),'Exact approved creator credit required');
assert.ok(!/<(?:script|iframe|object|embed)\b|\son[a-z]+\s*=|javascript:/i.test(html),'Static showcase cannot contain active content');
assert.ok(FILES.reduce((n,p)=>n+fs.statSync(path.join(ROOT,p)).size,0)<500000,'Unexpected showcase payload growth');
const manifest={schema:'famtastic.anderlye-showcase.v1',source_sha:sha,host:'famtasticdesigns.com',route:'/work/anderlye/',files};
const encoded=Buffer.from(JSON.stringify(manifest)).toString('base64');
function remote(mode){const command=['/usr/local/bin/php','--',mode,sha,encoded].map(quote).join(' ');const output=execFileSync('ssh',['-T','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes',SSH,command],{input:fs.readFileSync(path.join(REPO,'scripts/anderlye-showcase-remote.php')),encoding:'utf8',timeout:40000});return JSON.parse(output);}
async function get(url){const response=await fetch(url,{redirect:'manual',signal:AbortSignal.timeout(20000)});return{status:response.status,type:response.headers.get('content-type'),bytes:Buffer.from(await response.arrayBuffer())};}
const preflight=remote('preflight');
const plan={mode:apply?'apply':'dry-run',source_sha:sha,source_branch:branch,source_clean:clean,source_matches_pushed_branch:sha===pushedSha,contains_current_main:containsCurrentMain,target:PUBLIC,files,preflight,overwrite_existing:false,whole_frontend_build:false};
console.log(JSON.stringify(plan,null,2));
if(!apply)process.exit(0);
assert.ok(clean&&sha===pushedSha&&containsCurrentMain,'Apply requires clean exact pushed dedicated-branch SHA containing current main');
assert.deepEqual(git('ls-files','frontend/public/work/anderlye').split('\n').map(p=>p.replace('frontend/public/work/anderlye/','')).sort(),FILES,'Only the four reviewed tracked files may publish');
assert.ok(preflight.target_absent&&preflight.stage_absent&&preflight.public_parent_writable,'Expected-absent target/stage preflight failed');
for(const url of ['https://anderlyepl.famtasticinc.com/','https://anderlyepl.famtasticinc.com/connect','https://famtasticdesigns.com/connect/'])assert.equal((await get(url)).status,200,`Delivery destination unavailable: ${url}`);
const liveLogo=await get('https://famtasticdesigns.com/brand/famtastic-designs-logo-v1.png');assert.equal(liveLogo.status,200);assert.equal(hash(liveLogo.bytes),hash(approvedLogo()));
const font='brand/fonts/kaushan-script-latin-v19.woff2';const liveFont=await get(`https://famtasticdesigns.com/${font}`);assert.equal(liveFont.status,200);assert.equal(hash(liveFont.bytes),hash(fs.readFileSync(path.join(REPO,'frontend/public',font))));
const before={};for(const origin of ['https://famtasticdesigns.com','https://www.famtasticdesigns.com']){const home=await get(origin+'/');assert.equal(home.status,200);before[origin]=hash(home.bytes);}
remote('prepare');
for(const file of FILES)execFileSync('scp',['-q','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes',path.join(ROOT,file),`${SSH}:${BASE}/${sha}/payload/${file}`],{stdio:'inherit',timeout:40000});
let promoted=false;
try{
  remote('activate');promoted=true;
  for(const origin of ['https://famtasticdesigns.com','https://www.famtasticdesigns.com']){
    for(const file of FILES){const response=await get(`${origin}/work/anderlye/${file==='index.html'?'':file}`);assert.equal(response.status,200);assert.equal(hash(response.bytes),files[file],`Live bytes differ: ${origin}/${file}`);assert.ok(response.type?.includes(file.endsWith('.webp')?'image/webp':file.endsWith('.css')?'text/css':'text/html'));}
    assert.equal(hash((await get(origin+'/')).bytes),before[origin],'Existing homepage changed; inspect unrelated work');
  }
  const unchanged=remote('preflight');
  assert.equal(unchanged.frontend_release_sha256,preflight.frontend_release_sha256,'Existing agency release pointer changed');
  assert.equal(unchanged.existing_assets_sha256,preflight.existing_assets_sha256,'Existing agency assets changed');
  const receipt=remote('finalize');
  promoted=false; // A finalized remote release is not undone by a local receipt error.
  const destination=path.join(os.homedir(),'.local/state/famtastic/anderlye-showcase');fs.mkdirSync(destination,{recursive:true,mode:0o700});
  const record={...receipt,verified_at:new Date().toISOString(),existing_homepage_sha256:before,existing_frontend_release_sha256:unchanged.frontend_release_sha256,existing_assets_sha256:unchanged.existing_assets_sha256,browser_acceptance:'pending',cms_entry_created:false};
  fs.writeFileSync(path.join(destination,`${sha}.json`),JSON.stringify(record,null,2)+'\n',{mode:0o600,flag:'wx'});console.log(JSON.stringify(record,null,2));
}catch(error){if(promoted){try{console.error(JSON.stringify(remote('rollback')));}catch{console.error('Rollback refused or uncertain; inspect the private receipt before any retry.');}}throw error;}
