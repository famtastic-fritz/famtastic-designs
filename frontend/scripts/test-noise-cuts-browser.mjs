import assert from "node:assert/strict";
import {chromium} from "playwright";

const base = process.env.NOISE_CUTS_BASE || "http://127.0.0.1:4173/showcase/booked-and-branded-pilot/noise-cuts-proof/";
const browser = await chromium.launch({headless:true});
const page = await browser.newPage({viewport:{width:390,height:844},deviceScaleFactor:1});
await page.route("**/web/api/booking-request/noise-cuts", async route => route.fulfill({status:201,contentType:"application/json",body:JSON.stringify({ok:true,status:"received",reference:"proof-test",next_step:"owner_review_required"})}));
await page.goto(base,{waitUntil:"networkidle"});
assert.equal(await page.title(),"Noise Cuts — Concepto 01");
assert.equal(await page.locator("h1").innerText(),"TU CORTE\nHACE RUIDO.");
assert.ok(await page.locator("body").evaluate(node=>node.scrollWidth<=node.clientWidth),"public page must not overflow horizontally");
await page.locator("[data-open-booking]").first().click();
await page.locator('[name="name"]').fill("Prueba FAMtastic");
await page.locator('[name="email"]').fill("proof@example.com");
await page.locator('[name="day"]').fill("2026-09-19");
await page.locator('[name="consent"]').check();
await page.locator('#booking-form button[type="submit"]').click();
await page.getByText("Solicitud recibida").waitFor();
await page.goto(new URL("owner/",base).href,{waitUntil:"networkidle"});
await page.getByText("Vista de demostración").waitFor();
assert.ok(await page.locator("body").evaluate(node=>node.scrollWidth<=node.clientWidth),"owner page must not overflow horizontally");
const navTargets=await page.locator(".mobile-nav a").evaluateAll(nodes=>nodes.map(node=>node.getAttribute("href")));
assert.deepEqual(navTargets,["#requests","#hours","#settings","../"]);
for(const [route,title] of [["concept-02/","Noise Cuts — Concepto 02"],["concept-03/","Noise Cuts — Concepto 03"]]){
  await page.goto(new URL(route,base).href,{waitUntil:"networkidle"});
  assert.equal(await page.title(),title);
  assert.ok(await page.locator("body").evaluate(node=>node.scrollWidth<=node.clientWidth),`${title} must not overflow horizontally`);
  await page.locator("[data-open-booking]").first().click();
  await page.locator("#booking-dialog").waitFor({state:"visible"});
  await page.locator("[data-close-booking]").click();
  await page.locator('a[href="../owner/"]').first().click();
  await page.getByText("Vista de demostración").waitFor();
}
await browser.close();
console.log("PASS Noise Cuts mobile browser journey: three public proofs, persisted-request success, shared owner portal, no horizontal overflow, no dead navigation.");
