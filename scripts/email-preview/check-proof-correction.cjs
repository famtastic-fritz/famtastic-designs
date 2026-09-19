const {chromium}=require(process.argv[2]||'playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
(async()=>{
 const id=process.argv[3]||'17';
 const requests={'17':'4940a4fd-91af-40c4-b8a5-2b4dad1a3b95','16':'8000fc68-aae3-4f40-a3de-2251bd09076a'};
 assert.ok(requests[id]);
 const dir=path.resolve(__dirname,'../../.artifacts/proof-navigation',id==='17'?'':'request16');
 const expected='https://famtasticdesigns.com/portal/?section=projects&request='+requests[id];
 const html=fs.readFileSync(path.join(dir,'email.html'),'utf8');
 const plain=fs.readFileSync(path.join(dir,'email.txt'),'utf8');
 assert.equal((plain.match(/https?:\/\/\S+/g)||[]).length,1);
 assert.ok(plain.includes(expected)&&plain.includes('Always FAMtastic,\nShay'));
 assert.ok(!plain.includes('/web/'));
 const browser=await chromium.launch({headless:true});const cases=[];
 try{
  for(const width of [320,390,900])for(const images of [true,false]){
   const page=await browser.newPage({viewport:{width,height:1000}});
   await page.route('**/*',r=>images&&r.request().url().endsWith('/brand/famtastic-designs-logo-v1.png')?r.fulfill({path:path.resolve(__dirname,'../../docs/design/assets/famtastic-designs-logo-v1.png'),contentType:'image/png'}):r.abort());
   await page.setContent(html);
   const text=await page.locator('body').innerText();
   assert.ok(!/https?:\/\/|\/web\/|\{\{|\}\}/.test(text));
   assert.ok(text.includes('AI project guide')&&text.includes('Shay'));
   assert.equal(await page.locator('a').count(),1);
   assert.equal(await page.locator('a.cta').getAttribute('href'),expected);
   assert.ok((await page.locator('a.cta').boundingBox()).height>=44);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth),width);
   await page.screenshot({path:path.join(dir,`email-${width}-${images?'images':'no-images'}.png`),fullPage:true});
   cases.push({width,images,passed:true});await page.close();
  }
 }finally{await browser.close();}
 fs.writeFileSync(path.join(dir,'email-browser-qa.json'),JSON.stringify({passed:true,cases,plain_text_valid:true,one_portal_cta:true,visible_raw_urls:0,actual_email_client_rendering:'not tested',sent:false},null,2));
 console.log('PASS: six corrected email browser cases, images off, one portal button, plain text');
})().catch(e=>{console.error(e);process.exitCode=1;});
