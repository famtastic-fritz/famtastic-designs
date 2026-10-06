// Local installed-Drupal snapshots, served through a synthetic canonical origin.
// No production request, actual recipient, cookie-bearing login or send.
import fs from 'node:fs/promises';
import path from 'node:path';
import {createRequire} from 'node:module';
import assert from 'node:assert/strict';
const require = createRequire(path.resolve('frontend/package.json'));
const {chromium,expect} = require('@playwright/test');
const sandbox = await fs.realpath(process.argv[2]);
assert.match(sandbox, /^\/private\/tmp\/famtastic-acquisition-drupal\.[a-zA-Z0-9]{6}$/);
const local = new URL(process.argv[3]);
assert.equal(local.hostname, '127.0.0.1');
const fixtures = JSON.parse(await fs.readFile(path.join(sandbox, 'industry-fixture.json'), 'utf8'));
assert.equal(fixtures.length, 9);
const controls = {
  'home-services': ['toggle-detail','job-details','reply','save-reply','reply-note','job-status','status-note','instructions','save-instructions','instructions-note','reset'],
  'baking-catering': ['detailsToggle','moreDetails','replyDraft','saveReply','replyState','inquiryStatus','statusBadge','ownerInstructions','saveContent','contentState','resetLab'],
  'events-rentals': ['detailsToggle','moreDetails','replyDraft','saveReply','replyState','requestStatus','statusBadge','ownerInstructions','saveContent','contentState','resetLab'],
  'handcrafted-boutiques': ['detailsToggle','moreDetails','replyDraft','saveReply','replyState','requestStatus','statusBadge','ownerInstructions','saveContent','contentState','resetLab'],
};
const fallback = ['open-inquiry','inquiry-detail','reply','save-reply','reply-notice','request-status','status-notice','instructions','save-instructions','instructions-notice','reset-practice'];
controls['consulting-tutoring'] = [...fallback];
controls['consulting-tutoring'][7]='customer-guide';controls['consulting-tutoring'][8]='save-guidance';controls['consulting-tutoring'][9]='guidance-notice';
const browser = await chromium.launch({headless:true});
const results=[];
try {
  const context=await browser.newContext();
  await context.route('**/*', async route => {
    const url=new URL(route.request().url());
    if(url.protocol==='data:')return route.continue();
    if(url.origin!=='https://famtasticdesigns.com')return route.abort();
    if(url.pathname.includes('famtastic-designs-logo'))return route.fulfill({contentType:'image/png',body:await fs.readFile('marketing/campaigns/acquisition-199/assets/famtastic-designs-logo-v1.png')});
    if(url.pathname==='/fixture-shell') {
      const fixture=fixtures.find(f=>f.slug===url.searchParams.get('slug'));
      assert.ok(fixture);
      return route.fulfill({contentType:'text/html',body:`<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}iframe{border:0;width:100%;height:100vh}</style><iframe title="Industry Lab" sandbox="allow-scripts" src="${fixture.preview_path}"></iframe>`});
    }
    const fixture=fixtures.find(f=>f.preview_path===url.pathname);
    if(!fixture)return route.abort();
    const response=await context.request.get(new URL(url.pathname.replace(/^\/web\//, '/'),local).href,{maxRedirects:0});
    assert.equal(response.status(),200);
    return route.fulfill({response});
  });
  const page=await context.newPage();
  for(const fixture of fixtures) {
    for(const width of [1280,390]) {
      const errors=[];page.on('pageerror', error=>errors.push(error.message));
      await page.setViewportSize({width,height:900});
      await page.goto(`https://famtasticdesigns.com/fixture-shell?slug=${fixture.slug}`);
      const frame=page.frameLocator('iframe');
      await frame.locator(`[data-famtastic-industry-lab="${fixture.slug}"]`).waitFor();
      const [open,detail,reply,saveReply,replyNotice,status,statusNotice,instructions,saveInstructions,instructionsNotice,reset]=controls[fixture.slug]??fallback;
      await frame.locator('#'+open).click();assert.ok(await frame.locator('#'+detail).isVisible());
      await frame.locator('#'+reply).fill('Synthetic fixture reply');await frame.locator('#'+saveReply).click();assert.ok((await frame.locator('#'+replyNotice).textContent()).trim());
      await frame.locator('#'+status).selectOption({index:2});assert.ok((await frame.locator('#'+statusNotice).textContent()).trim());
      await frame.locator('#'+instructions).fill('Synthetic fixture instructions');await frame.locator('#'+saveInstructions).click();assert.ok((await frame.locator('#'+instructionsNotice).textContent()).trim());
      const metrics=await frame.locator('body').evaluate(()=>({viewport:innerWidth,scroll:document.documentElement.scrollWidth}));
      assert.ok(metrics.scroll<=metrics.viewport+1);
      await frame.locator('#'+reset).click();await expect(frame.locator('#'+reply)).not.toHaveValue('Synthetic fixture reply');
      assert.equal(errors.length,0);page.removeAllListeners('pageerror');
      results.push({slug:fixture.slug,width,scrollWidth:metrics.scroll,opaque_origin_sandbox:true,interaction:true,reset:true});
    }
  }
  await fs.writeFile(path.join(sandbox,'industry-browser-proof.json'),JSON.stringify({classification:'local_synthetic_installed_drupal',production:false,results},null,2));
  process.stdout.write(JSON.stringify({status:'passed',viewport_interaction_checks:results.length,customer_sends:false})+'\n');
} catch(error) {process.stderr.write('Browser check failed: '+error.message+'\n');throw error;}
finally {await browser.close();}
