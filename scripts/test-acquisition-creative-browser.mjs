import {createRequire} from 'node:module';
import {readFile,writeFile,mkdir} from 'node:fs/promises';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
const require=createRequire(new URL('../frontend/package.json',import.meta.url));
const {chromium}=require('@playwright/test');
const base=new URL('../marketing/campaigns/acquisition-199/',import.meta.url),origin='http://127.0.0.1:5200';
const recipes=JSON.parse(await readFile(new URL('recipes.json',base))),messages=JSON.parse(await readFile(new URL('messages.json',base)));
const artifacts=new URL('../.artifacts/acquisition-creative-current/',import.meta.url);await mkdir(artifacts,{recursive:true});
const pages=[...recipes.niches.flatMap(n=>n.directions.map(d=>({path:d.review_path,kind:'sample',id:d.id}))),...messages.messages.map(m=>({path:`emails/${m.id}.html`,kind:'email',id:m.id})),...messages.messages.map(m=>({path:`emails/${m.id}-images-blocked.html`,kind:'images_blocked',id:m.id}))];
const browser=await chromium.launch({headless:true}),rows=[];
try{
 for(const viewport of [{width:1280,height:900},{width:390,height:844}]){
  const context=await browser.newContext({viewport,reducedMotion:'reduce'});await context.route('**/*',route=>new URL(route.request().url()).origin===origin?route.continue():route.abort());
  const page=await context.newPage();
  for(const item of pages){
   const errors=[],failures=[];const err=e=>errors.push(e.message),resp=r=>{if(r.status()>=400)failures.push(r.status());};page.on('pageerror',err);page.on('response',resp);
   await page.goto(`${origin}/${item.path}`);await page.locator('h1').first().waitFor();
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`${item.path} overflow`);
   const loaded=await page.locator('img').evaluateAll(imgs=>imgs.every(i=>i.complete&&i.naturalWidth>0));assert.equal(loaded,true,`${item.path} image loads`);
   assert.equal(errors.length,0,`${item.path} page errors`);assert.equal(failures.length,0,`${item.path} asset HTTP errors`);
   if(item.kind==='images_blocked')assert.equal(await page.locator('img').count(),0);
   if(item.kind==='sample'){
    const anchor=page.locator('a[href="#request"]');if(await anchor.count()){await anchor.first().click();assert.equal(new URL(page.url()).hash,'#request');}
    assert.equal(await page.locator('script,form').count(),0);
   }
   for(const a of await page.locator('a').all())assert.ok((await a.textContent()).trim()||await a.getAttribute('aria-label')||await a.locator('img').first().getAttribute('alt').catch(()=>null),`${item.path} named link`);
   if(item.kind==='email'||item.kind==='images_blocked'){const cta=page.locator('a.cta');assert.equal(await cta.count(),1);assert.ok((await cta.boundingBox()).height>=44);}
   const bytes=await readFile(new URL(item.path,base));
   await page.screenshot({path:new URL(`${item.id}-${item.kind}-${viewport.width}.jpg`,artifacts).pathname,type:'jpeg',quality:65,fullPage:true});
   rows.push({...item,viewport,sha256:createHash('sha256').update(bytes).digest('hex'),loaded_images:true,overflow:false,page_errors:0,local_http_errors:0,sample_anchor_interaction:item.kind==='sample',environment:'Independent Playwright local static browser; no real inbox or provider'});
   page.off('pageerror',err);page.off('response',resp);
  }
  await context.close();
 }
 const receipt={schema:'famtastic.acquisition-current-browser-qa.v1',observed_at:new Date().toISOString(),rows,limits:['Browser local HTML, not Gmail/Outlook/Apple Mail','Post-freeze CUA unavailable; prior CUA receipt retained separately','No physical owner acceptance, hosted release or real dispatch']};
 await writeFile(new URL('evidence/current-browser-qa.json',base),JSON.stringify(receipt,null,2)+'\n');console.log(`PASS: ${rows.length} current hash-bound local browser rows; no real sends.`);
}finally{await browser.close();}
