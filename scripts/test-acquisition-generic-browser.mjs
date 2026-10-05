import {createRequire} from 'node:module';
import {mkdir,writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const require=createRequire(new URL('../frontend/package.json',import.meta.url));
const {chromium}=require('@playwright/test');
const origin='http://127.0.0.1:5201';
const out=new URL('../marketing/campaigns/acquisition-199/generic-review/evidence/',import.meta.url);await mkdir(out,{recursive:true});
const browser=await chromium.launch({headless:true});const results=[];
try{
 for(const viewport of [{width:1440,height:1000},{width:390,height:844}]){
  const context=await browser.newContext({viewport,reducedMotion:'reduce'});const page=await context.newPage();const errors=[];let writes=0;
  page.on('pageerror',e=>errors.push(e.message));
  await context.route('**/*',route=>{const r=route.request();if(r.method()!=='GET')writes++;return new URL(r.url()).origin===origin?route.continue():route.abort();});
  await page.goto(origin+'/beauty-email.html');await page.locator('img').first().waitFor();await page.evaluate(()=>document.fonts.ready);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Email overflow');
  assert.equal(await page.locator('img').evaluateAll(images=>images.filter(i=>!i.complete||i.naturalWidth===0).length),0);
  await page.screenshot({path:new URL(`email-${viewport.width}.png`,out).pathname,fullPage:true});
  await page.getByRole('link',{name:'See what your website could look like',exact:true}).click();
  await page.getByRole('heading',{name:/Good hair/}).waitFor();await page.evaluate(()=>document.fonts.ready);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Lab overflow');
  assert.equal(await page.locator('a[href=""]').count(),0);
  assert.equal(await page.locator('img').evaluateAll(images=>images.filter(i=>!i.complete||i.naturalWidth===0).length),0);
  const body=await page.locator('body').innerText();assert.ok(!/replace this illustrative|type director|shape director/i.test(body));
  await page.screenshot({path:new URL(`lab-${viewport.width}.png`,out).pathname,fullPage:true});
  await page.locator('#open-inquiry').click();assert.equal(await page.locator('#inquiry-detail').isVisible(),true);
  await page.locator('#reply').fill('Please share your routine and preferred service.');await page.locator('#save-reply').click();await page.locator('#reply-notice').filter({hasText:/Nothing sent/}).waitFor();
  await page.locator('#request-status').selectOption('Reviewing details');assert.equal(await page.locator('#status-label').innerText(),'Reviewing details');
  await page.locator('#instructions').fill('Tell us your service and preparation questions.');await page.locator('#save-instructions').click();await page.locator('#instructions-notice').filter({hasText:/Nothing published/}).waitFor();
  await page.locator('.phone').screenshot({path:new URL(`phone-practice-${viewport.width}.png`,out).pathname});
  await page.locator('#business').fill('Fictional Studio Review');await page.locator('#workday').fill('Review inquiries between appointments.');await page.locator('#interview-form button').click();await page.locator('#interview-notice').filter({hasText:/No account|Nothing|practice|locally/i}).waitFor();
  await page.reload();assert.equal(await page.locator('#status-label').innerText(),'Needs reply');assert.equal(await page.locator('#business').inputValue(),'');
  assert.equal(writes,0);assert.deepEqual(errors,[]);
  results.push({viewport,email_to_lab:true,images_loaded:true,no_overflow:true,phone_actions:['open inquiry','save unsent reply','change status','save unpublished instructions'],practice_interview:true,practice_reload_resets:true,backend_writes:0,environment:'local static review; native context/registration verified separately'});await context.close();
 }
 await writeFile(new URL('browser-receipt.json',out),JSON.stringify({date:new Date().toISOString(),results,owner_accepted:false},null,2)+'\n');console.log(JSON.stringify(results,null,2));
}finally{await browser.close();}
