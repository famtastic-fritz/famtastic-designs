const {chromium}=require('../frontend/node_modules/@playwright/test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{const b=await chromium.launch();const results=[];try{
 for(const blocked of [false,true])for(const width of [320,390,768,1440]){
  const p=await b.newPage({viewport:{width,height:1000},reducedMotion:'reduce'});
  if(blocked)await p.route('**/brand/fonts/*.woff2',r=>r.abort());
  const errors=[];p.on('pageerror',e=>errors.push(e.message));
  await p.goto('http://127.0.0.1:4187/');await p.waitForLoadState('networkidle');await p.evaluate(()=>document.fonts.ready);
  assert.equal(await p.locator('h1').count(),1);
  const accents=await p.locator('.fam-heading-script').all();assert.equal(accents.length,3);
  for(const accent of accents){assert.ok((await accent.textContent()).split(/\s+/).length<=6);assert.ok(await accent.evaluate(e=>['H1','H2'].includes(e.parentElement.tagName)));}
  assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width);
  assert.equal(await p.evaluate(()=>document.fonts.check('24px "FAMtastic Heading Script"')), !blocked);
  assert.equal(await p.locator('button .fam-heading-script,nav .fam-heading-script').count(),0);
  assert.deepEqual(errors,[]);
  if(width===390||width===1440)await p.screenshot({path:`.local-email-preview/brand-dna/cursive-${width}${blocked?'-fallback':''}.png`});
  results.push({width,fontBlocked:blocked,accents:3,overflow:false,errors});await p.close();
 }
 fs.writeFileSync('.local-email-preview/brand-dna/cursive-results.json',JSON.stringify(results,null,2));
 console.log('PASS: cursive H1/H2 opt-in, 3 phrases <=6 words, 320/390/768/1440, loaded and blocked fonts, no overflow/page errors, no navigation/button script.');
 }finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1});
