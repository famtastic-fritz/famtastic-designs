import { createRequire } from 'node:module';
import {mkdir,writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const require=createRequire(new URL('../frontend/package.json',import.meta.url));
const {chromium}=require('@playwright/test');
const origin=process.env.ACQUISITION_FRONTEND_URL||'http://127.0.0.1:5199';
const token='ab'.repeat(32),returnPath='/portal?start=website&section=projects';
const evidence=new URL('../.artifacts/acquisition-browser/',import.meta.url);await mkdir(evidence,{recursive:true});
const browser=await chromium.launch({headless:true});
const results=[];
try{
 for(const viewport of [{width:1440,height:1000},{width:390,height:844}]){
  const context=await browser.newContext({viewport,reducedMotion:'reduce'}),page=await context.newPage();
  const mutations=[];let registerPayload;let failNextSample=false;let verificationPosts=0;
  await context.route('**/*',async route=>{
   const req=route.request(),url=new URL(req.url());
   if(url.origin!==new URL(origin).origin)return route.abort();
   if(url.pathname.startsWith('/api/acquisition/samples/')){
    if(req.method()==='POST')mutations.push(url.pathname);
    if(url.pathname.endsWith('/preference'))return route.fulfill({json:{ok:true,preference:{recipe_id:'beauty_editorial'}}});
    if(url.pathname.endsWith('/claim'))return route.fulfill({json:{ok:true,continuation:{kind:'acquisition_sample',return_path:returnPath}}});
    if(url.pathname.includes('/preview/'))return route.fulfill({contentType:'text/html',body:'<!doctype html><html><head><meta name="viewport" content="width=device-width"></head><body style="background:#f7e8de;color:#32201c;font:24px Georgia;padding:24px"><p>Local browser fixture</p><h1>Studio Example</h1><p>Illustrative appointment direction</p></body></html>'});
    if(failNextSample){return route.fulfill({status:503,json:{message:'Synthetic temporary failure'}});}
    return route.fulfill({json:{ok:true,sample:{illustrative:true,business_name:'Studio Example · local test',niche:'beauty_hair',preference:null,recipes:[{id:'beauty_editorial',title:'The Signature Edit',summary:'An expressive service introduction.',preview_path:`/web/api/acquisition/samples/${token}/preview/beauty_editorial`},{id:'beauty_service_first',title:'The Service Studio',summary:'A menu and booking handoff.',preview_path:`/web/api/acquisition/samples/${token}/preview/beauty_service_first`}]}}});
   }
   if(url.pathname==='/session/token')return route.fulfill({body:'local-csrf-only'});
   if(url.pathname==='/api/customer/register'){registerPayload=req.postDataJSON();return route.fulfill({json:{ok:true}});}
   if(url.pathname==='/api/customer/verify'){verificationPosts++;return route.fulfill({json:{ok:true,continuation:{kind:'acquisition_sample',return_path:returnPath}}});}
   if(url.pathname==='/api/customer/login')return route.fulfill({json:{customer:{email:'owner@example.test'},continuation:{kind:'acquisition_sample',return_path:returnPath}}});
   if(url.pathname.startsWith('/api/')||url.pathname.startsWith('/jsonapi/'))return route.fulfill({status:401,json:{message:'Fixture boundary'}});
   return route.continue();
  });
  await page.goto(`${origin}/samples/${token}`);await page.getByRole('heading',{name:/Studio Example/}).waitFor();
  assert.equal(mutations.length,0,'view/scanner GET had no mutations');
  assert.equal(await page.locator('iframe').count(),2);assert.equal(await page.locator('iframe').first().getAttribute('sandbox'),'');
  assert.equal(await page.locator('meta[name=robots]').getAttribute('content'),'noindex, nofollow, noarchive');
  assert.equal(await page.locator('meta[name=referrer]').getAttribute('content'),'no-referrer');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  for(const button of await page.locator('button,a.acquisition-button').all())assert.ok((await button.boundingBox()).height>=44);
  await page.getByRole('button',{name:'Open sample inquiry',exact:true}).click();
  await page.getByLabel('Request status',{exact:true}).selectOption('Reviewing');
  await page.getByLabel('Your sample reply',{exact:true}).fill('Please send your preferred service and date.');
  await page.getByRole('button',{name:'Save sample reply',exact:true}).click();
  await page.getByRole('status').filter({hasText:'Sample reply saved locally'}).waitFor();
  await page.getByRole('button',{name:'Website details',exact:true}).click();
  await page.getByLabel('Inquiry instructions',{exact:true}).fill('Include your service and location.');
  await page.getByRole('button',{name:'Save sample website detail',exact:true}).click();
  await page.getByRole('status').filter({hasText:'Nothing was published'}).waitFor();
  assert.equal(mutations.length,0,'Owner walkthrough creates no backend mutations');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await page.locator('.acquisition-owner').screenshot({path:new URL(`phone-owner-workflow-${viewport.width}.png`,evidence).pathname});
  await page.getByRole('button',{name:'I like this direction'}).first().click();await page.getByRole('button',{name:'Preference saved'}).waitFor();
  assert.equal(mutations.length,1);
  await page.screenshot({path:new URL(`sample-lab-${viewport.width}.png`,evidence).pathname,fullPage:true});
  await page.getByRole('link',{name:'Create my workspace'}).click();await page.getByLabel('Your name',{exact:true}).fill('Test owner');await page.getByLabel('Email',{exact:true}).fill('owner@example.test');await page.getByLabel('Password',{exact:true}).fill('disposable-password-only');await page.getByRole('button',{name:'Create my account',exact:true}).click();await page.getByRole('status').filter({hasText:'Check your email'}).waitFor();
  assert.equal(registerPayload.sample_continuation,token);assert.equal(registerPayload.source,'acquisition_sample');
  // Different tab/device has only verification token, no invitation URL storage.
  const verify=await context.newPage();await verify.goto(`${origin}/verify-email?token=synthetic-only`);await verify.getByRole('heading',{name:'Email verified',exact:true}).waitFor();const href=await verify.getByRole('link',{name:'Sign in to my portal'}).getAttribute('href');assert.equal(new URL(href,origin).searchParams.get('redirect'),returnPath);assert.ok(!href.includes(token));assert.equal(verificationPosts,1,'One-time verification is requested once under StrictMode');
  await page.goto(`${origin}/samples/invalid`);await page.getByRole('heading',{name:'This invitation is unavailable.'}).waitFor();
  failNextSample=true;await page.goto(`${origin}/samples/${token}`);
  await page.getByRole('heading',{name:'This invitation is unavailable.'}).waitFor();
  failNextSample=false;await page.getByRole('button',{name:'Try again',exact:true}).click();
  await page.getByRole('heading',{name:/Studio Example/}).waitFor();
  assert.equal(mutations.length,1,'Retry only performs read-only fetch');
  await page.getByRole('button',{name:'Open sample inquiry',exact:true}).click();
  assert.equal(await page.getByLabel('Request status',{exact:true}).inputValue(),'New','Reload resets owner practice state');
  assert.equal(await page.getByLabel('Your sample reply',{exact:true}).inputValue(),'');
  results.push({viewport,environment:'browser with explicit synthetic HTTP fixtures',scanner_get_mutations:0,preference_posts:1,registration_payload_verified:true,cross_device_verification_return:true,owner_workflow_interactions:['open inquiry','change status','save local reply','save local website detail'],owner_demo_backend_mutations:0,read_retry_recovered:true,owner_practice_reset:true,overflow:false,reduced_motion:true});await context.close();
 }
 await writeFile(new URL('receipt.json',evidence),JSON.stringify({date:new Date().toISOString(),results,limitation:'Synthetic HTTP fixture frontend proof; Drupal persistence, real mail clients and hosted state are separate gates.'},null,2));console.log(JSON.stringify(results,null,2));
}finally{await browser.close();}
