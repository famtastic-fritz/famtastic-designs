import { createServer } from "node:http";
import { readFile, mkdtemp } from "node:fs/promises";
import { tmpdir } from "node:os";
import { resolve, extname } from "node:path";
import { createRequire } from "node:module";
import assert from "node:assert/strict";
const root=process.argv[2]; if(!root) throw Error("Pass repository root");
const require=createRequire(resolve(root,"frontend/package.json"));
const {chromium}=require("playwright");
const dir=import.meta.dirname;
const screenshotDir=await mkdtemp(resolve(tmpdir(),"locs-booking-qa-"));
const server=createServer(async(req,res)=>{try{const url=new URL(req.url,"http://localhost");const path=resolve(dir,"."+url.pathname+(url.pathname.endsWith("/")?"index.html":""));if(!path.startsWith(dir+"/"))throw Error();res.setHeader("Content-Type",({".html":"text/html",".js":"text/javascript",".css":"text/css",".png":"image/png"})[extname(path)]||"application/octet-stream");res.end(await readFile(path));}catch{res.statusCode=404;res.end();}});
await new Promise(r=>server.listen(0,"127.0.0.1",r));
const base="http://127.0.0.1:"+server.address().port;
const browser=await chromium.launch({headless:true});
const results=[];
try{
 for(const width of [390,768,1280]){
  const context=await browser.newContext({viewport:{width,height:900}});
  const page=await context.newPage(); const external=[];
  await page.route("https://**",route=>{external.push(route.request().url());return route.abort();});
  await page.goto(base+"/"); await page.waitForTimeout(100);
  assert.equal(await page.locator("#send-request").isDisabled(),false);
  assert.equal(external.some(url=>url.includes("googletagmanager")),false);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),true);
  await page.screenshot({path:resolve(screenshotDir,"qa-"+width+".png"),fullPage:true});
  await page.locator("#analytics-allow").click();
  await page.waitForTimeout(100);
  assert.equal(await page.locator("#locs-analytics").count(),1, await page.locator("#analytics-status").textContent());
  const queue=await page.evaluate(()=>window.dataLayer.map(args=>Array.from(args)));
  const config=queue.find(args=>args[0]==="config");
  assert.equal(config[2].page_location,base+"/");assert.equal(config[2].page_referrer,"");
  assert.equal(config[2].send_page_view,false);
  await page.locator("#analytics-decline").click();
  assert.equal(await page.evaluate(()=>window["ga-disable-G-V8M437DWV0"]),true);
  results.push("production endpoint configured, consent and no-overflow "+width);await context.close();
 }
 const context=await browser.newContext({viewport:{width:390,height:844}});
 const page=await context.newPage(); let submissions=0, responseMode="saved";
 await page.route("https://**",r=>r.abort());
 await page.route("**/config.js",r=>r.fulfill({contentType:"text/javascript",body:'window.LOCS_CONFIG={requestsEnabled:true,requestEndpoint:"/api/booking-request/site-dffd4cb9c3aa47fd",gaMeasurementId:""};'}));
 await page.route("**/api/booking-request/**",r=>{
  submissions++;
  if(responseMode==="uncertain") return r.abort();
  if(responseMode==="rejected") return r.fulfill({status:422,contentType:"application/json",body:JSON.stringify({ok:false,error:"booking_consent_required"})});
  return r.fulfill({contentType:"application/json",body:JSON.stringify({ok:true,status:"received",reference:"123e4567-e89b-12d3-a456-426614174000"})});
 });
 async function fill(){await page.locator('[name="name"]').fill("Local QA Fixture");await page.locator('[name="email"]').fill("qa@example.test");await page.locator('[name="requested_window"]').fill("Friday");await page.locator('[name="consent"]').check();}
 await page.goto(base+"/");await fill();await page.locator("#send-request").click();
 await page.waitForFunction(()=>document.getElementById("status").textContent.includes("request was saved"));
 assert.equal(submissions,1);assert.equal(await page.locator("#send-request").isDisabled(),true);
 results.push("mock durable-save receipt and duplicate-click prevention");
 responseMode="uncertain";await page.reload();await fill();await page.locator("#send-request").click();
 await page.waitForFunction(()=>document.getElementById("status").textContent.includes("could not confirm"));
 assert.equal(submissions,2);assert.equal(await page.locator("#send-request").isDisabled(),true);
 assert.equal(await page.locator('[name="name"]').inputValue(),"Local QA Fixture");
 results.push("mock ambiguous network outcome blocks retry and preserves form");
 responseMode="rejected";await page.reload();await fill();await page.locator("#send-request").click();
 await page.waitForFunction(()=>document.getElementById("status").textContent.includes("Please check"));
 assert.equal(await page.locator("#send-request").isDisabled(),false);
 assert.equal(await page.locator('[name="name"]').inputValue(),"Local QA Fixture");
 assert.equal(await page.evaluate(()=>JSON.stringify(window.dataLayer||[]).includes("Local QA Fixture")),false);
 results.push("mock rejected input permits correction without false receipt");
 await context.close();console.log(JSON.stringify({classification:"LOCAL ONLY; network mocks, no provider writes",screenshotDir,passed:results},null,2));
}finally{await browser.close();await new Promise(r=>server.close(r));}
