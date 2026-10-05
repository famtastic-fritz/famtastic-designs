import {createRequire} from 'node:module';
import {readFile,mkdir,writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const require=createRequire(new URL('../frontend/package.json',import.meta.url));const {chromium,expect}=require('@playwright/test');
const fixturePath=process.env.ACQUISITION_GENERIC_FIXTURE;if(!fixturePath)throw Error('Set ACQUISITION_GENERIC_FIXTURE to the local fictional Drupal fixture; never a customer file.');
const fixture=JSON.parse(await readFile(fixturePath,'utf8'));
const native=new URL('../.artifacts/acquisition-generic-native/',import.meta.url);
const sample=JSON.parse(await readFile(new URL('sample.json',native),'utf8'));
const continuation=JSON.parse(await readFile(new URL('continuation-before-request.json',native),'utf8'));
const requestRaw=JSON.parse(await readFile(new URL('request.json',native),'utf8'));const savedRequest=requestRaw.website_request||requestRaw;
const preview=await readFile(new URL('preview.html',native),'utf8');
const review=new URL('../marketing/campaigns/acquisition-199/generic-review/',import.meta.url);const out=new URL('evidence/',review);await mkdir(out,{recursive:true});
const target=process.env.ACQUISITION_FRONTEND_URL||'http://127.0.0.1:5199';
// A routed, synthetic public origin avoids Chrome's opaque-frame loopback
// policy without changing the product's strict iframe sandbox. No DNS request
// or external host is used; static app bytes are fetched from local Vite.
const origin='https://acquisition-preview.example.test';const token=fixture.token;
let email=await readFile(new URL('beauty-email.html',review),'utf8');email=email.replaceAll('href="beauty-lab.html"',`href="${origin}/samples/${token}"`).replaceAll('src="assets/','src="/review-assets/').replace('no invitation or unsubscribe binding is issued.','a fictional stored native invitation is used here; no real mail is sent.');
const browser=await chromium.launch({headless:true});const results=[];
try{
for(const viewport of [{width:1440,height:1000},{width:390,height:844}]){
 const context=await browser.newContext({viewport,reducedMotion:'reduce'}),page=await context.newPage();const errors=[];let registered,requested,verifyPosts=0,saved=false;const mutations=[];
 page.on('pageerror',e=>errors.push(e.message));
 if(process.env.ACQ_DEBUG) {page.on('requestfailed',r=>console.log('FAILED',new URL(r.url()).pathname,r.failure()));page.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text());});}
 const session={customer:{display_name:'Review owner',email:fixture.email},organizations:[{public_id:'review-org',name:'Review business'}],continuation};
 const workspace=()=>({organization:{public_id:'review-org',name:'Review business'},website_requests:saved?[savedRequest]:[],projects:[],orders:[],invoices:[],faqs:[],referrals:[],preferences:{}});
 await context.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());if(url.origin!==origin)return route.abort();
  if(url.pathname==='/review-email')return route.fulfill({contentType:'text/html',body:email});
  if(url.pathname.startsWith('/review-assets/')){const name=url.pathname.slice('/review-assets/'.length);if(!/^[a-z0-9.-]+$/.test(name))return route.abort();const body=await readFile(new URL('assets/'+name,review));return route.fulfill({body,contentType:name.endsWith('.png')?'image/png':'image/jpeg'});}
  if(url.pathname.startsWith('/api/acquisition/samples/')){
   if(req.method()==='POST')mutations.push(url.pathname.split('/').at(-1));
   if(url.pathname.includes('/preview/'))return route.fulfill({contentType:'text/html',body:preview});
   if(url.pathname.endsWith('/preference'))return route.fulfill({json:{ok:true,preference:{recipe_id:fixture.recipe_id}}});
   if(url.pathname.endsWith('/claim'))return route.fulfill({json:{ok:true,continuation}});
   return route.fulfill({json:sample});
  }
  if(url.pathname==='/session/token')return route.fulfill({body:'local-review-csrf'});
  if(url.pathname==='/api/customer/register'){registered=req.postDataJSON();return route.fulfill({json:{ok:true}});}
  if(url.pathname==='/api/customer/verify'){verifyPosts++;return route.fulfill({json:{ok:true,continuation}});}
  if(url.pathname==='/api/customer/login'||url.pathname==='/api/customer/session')return route.fulfill({json:session});
  if(url.pathname==='/api/customer/workspace')return route.fulfill({json:workspace()});
  if(url.pathname==='/api/customer/catalog')return route.fulfill({json:{products:[]}});
  if(url.pathname==='/api/customer/website-requests'&&req.method()==='POST'){requested=req.postDataJSON();saved=true;return route.fulfill({json:{website_request:savedRequest}});}
  if(url.pathname.startsWith('/api/')||url.pathname.startsWith('/jsonapi/'))return route.fulfill({status:401,json:{message:'Isolated browser fixture'}});
  const response=await route.fetch({url:target+url.pathname+url.search});return route.fulfill({response});
 });
 await page.goto(origin+'/review-email');await page.getByRole('link',{name:'See what your website could look like',exact:true}).click();
 await page.getByRole('heading',{name:new RegExp(sample.sample.business_name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'))}).first().waitFor();
 assert.equal(await page.locator('iframe').count(),1);assert.equal(await page.locator('iframe').getAttribute('sandbox'),'');assert.deepEqual(mutations,[]);
 assert.ok((await page.locator('.acquisition-note').first().innerText()).includes('supplied'));
 assert.ok(!page.url().includes('@'));assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 await page.frameLocator('iframe').locator('.hero-image').waitFor();await page.frameLocator('iframe').locator('.hero-image').evaluate(i=>i.decode());assert.equal(await page.frameLocator('iframe').locator('.hero-image').evaluate(i=>i.complete&&i.naturalWidth>0),true);
 await page.screenshot({path:new URL(`native-lab-${viewport.width}.png`,out).pathname,fullPage:true});
 await page.getByRole('button',{name:'Open sample inquiry',exact:true}).click();await page.getByLabel('Request status',{exact:true}).selectOption('Reviewing');await page.getByLabel('Your sample reply',{exact:true}).fill('Please share your preferred service.');await page.getByRole('button',{name:'Save sample reply',exact:true}).click();await page.getByRole('status').filter({hasText:'Nothing was sent'}).waitFor();assert.deepEqual(mutations,[]);
 await page.getByRole('link',{name:'Start my website interview',exact:true}).click();
 await expect(page.locator('#portal-business')).toHaveValue(sample.sample.business_name);
 await page.getByLabel('Your name',{exact:true}).fill('Review owner');await page.getByLabel('Email',{exact:true}).fill(fixture.email);await page.getByLabel('Password',{exact:true}).fill('local-review-password-only');await page.getByRole('button',{name:'Create my account',exact:true}).click();await page.getByRole('status').filter({hasText:'Check your email'}).waitFor();assert.equal(registered.sample_continuation,token);assert.equal(registered.business_name,sample.sample.business_name);
 const verify=await context.newPage();await verify.goto(origin+'/verify-email?token=synthetic-review');await verify.getByRole('heading',{name:'Email verified',exact:true}).waitFor();assert.equal(verifyPosts,1);await verify.getByRole('link',{name:'Sign in to my portal'}).click();await verify.getByLabel('Email',{exact:true}).fill(fixture.email);await verify.getByLabel('Password',{exact:true}).fill('local-review-password-only');await verify.getByRole('button',{name:'Open my portal',exact:true}).click();
 await verify.getByLabel('Request name',{exact:true}).waitFor();await expect(verify.getByLabel('Request name',{exact:true})).toHaveValue(sample.sample.business_name+' website');assert.ok((await verify.locator('.portal-form-stepnote').first().innerText()).includes(continuation.known_information.industry));
 await verify.getByRole('button',{name:'Save draft & open full brief',exact:true}).click();await verify.locator('[name=business_name]').waitFor();assert.equal(requested.acquisition_context_id,continuation.context_id);await expect(verify.locator('[name=business_name]')).toHaveValue(sample.sample.business_name);await expect(verify.locator('[name=industry]')).toHaveValue(continuation.known_information.industry);
 await verify.screenshot({path:new URL(`native-interview-${viewport.width}.png`,out).pathname,fullPage:true});assert.deepEqual(errors,[]);
 results.push({viewport,one_polished_preview:true,stored_business_and_industry_supplied:true,owner_unknown_not_invented:true,scanner_get_mutations:0,phone_practice_mutations:0,registration_business_prefilled:true,opaque_continuation_payload:true,cross_device_verification_posts:verifyPosts,interview_business_industry_prefilled:true,environment:'Browser on routed synthetic public origin; app bytes from local Vite, HTTP fixtures from actual controlled Drupal output; strict sandbox retained; hosted CORS and deployment unverified',owner_accepted:false});await context.close();
}
await writeFile(new URL('native-browser-receipt.json',out),JSON.stringify({date:new Date().toISOString(),results},null,2)+'\n');console.log(JSON.stringify(results,null,2));
}finally{await browser.close();}
