const {chromium}=require(process.argv[2]||'@playwright/test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
(async()=>{
 const output=path.resolve(__dirname,'../../.local-email-preview');
 const browser=await chromium.launch({headless:true});
 const results=[];
 try {
 for(const template of ['standard','customer_intake_submitted','customer_proof_ready','customer_revision_received','customer_message_reply','customer_staging_review_ready']){
  for(const width of [320,390,660,768]) for(const images of [true,false]){
   const page=await browser.newPage({viewport:{width,height:1000}});
   await page.route('https://**/*',r=>images&&r.request().url().endsWith('/brand/famtastic-designs-logo-v1.png')?r.fulfill({path:path.resolve(__dirname,'../../docs/design/assets/famtastic-designs-logo-v1.png'),contentType:'image/png'}):r.abort());
   await page.goto('file://'+path.join(output,template+'.html'));
   if(images) await page.evaluate(()=>Promise.all([...document.images].map(i=>i.decode())));
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth),width,`${template} ${width} images=${images}`);
   assert.equal(await page.locator('body[data-famtastic-email-brand="v1"]').count(),1);
   assert.ok(await page.locator('h1').isVisible());
   const cta=page.locator('a.cta');
   if(await cta.count())assert.ok((await cta.boundingBox()).height>=44);
   if([390,660].includes(width)&&images)await page.screenshot({path:path.join(output,`${template}-${width}.png`),fullPage:true});
   results.push({template,width,images,passed:true});await page.close();
  }
 }
 fs.writeFileSync(path.join(output,'all-template-results.json'),JSON.stringify({cases:results,sent:false,emailClients:'not tested'},null,2));
 console.log(`PASS ${results.length} responsive and images-disabled cases`);
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
