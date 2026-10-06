// Static layout proof, not authenticated Drupal workflow proof.
const {readFileSync, mkdirSync, writeFileSync} = require('node:fs');
const {resolve} = require('node:path');
const {chromium} = require(process.argv[2] || '@playwright/test');
const root = resolve(__dirname, '..');
const theme = readFileSync(resolve(root, 'backend/web/themes/custom/famtastic_admin/css/famtastic-admin.css'), 'utf8');
const operations = readFileSync(resolve(root, 'backend/web/modules/custom/famtastic_pipeline/css/operations.css'), 'utf8');
const out = resolve(root, '.artifacts/owner-mobile-ui');
mkdirSync(out, {recursive: true});
const link = label => `<a class="famtastic-admin-nav__link" href="#work">${label}</a>`;
const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${theme}\n${operations}</style></head><body class="path-admin famtastic-admin-shell"><div class="famtastic-admin-shell__frame"><header class="famtastic-admin-shell__header"><div class="famtastic-admin-shell__brand">FAMtastic Designs</div><nav class="famtastic-admin-nav" aria-label="Staff navigation">${link('Home')}${link('Messages (14)')}${link('Campaigns')}<details class="famtastic-admin-nav__more"><summary>More</summary><div class="details-wrapper"><div class="famtastic-admin-nav__secondary">${link('Attention')}${link('Marketing')}${link('Analytics')}${link('Settings')}</div></div></details></nav></header><div class="famtastic-admin-shell__body"><main class="famtastic-admin-shell__main"><section class="famtastic-ops"><section class="famtastic-owner__heading"><h2>What needs me?</h2><p>Start with a reply, review a draft, or plan your next campaign.</p></section><div class="famtastic-owner__queues">${['Needs reply','Needs review','Failed delivery','Due next'].map(x=>`<a class="famtastic-owner__queue" href="#work"><strong>3</strong><span>${x}</span><small>Fixture record reason for this queue.</small></a>`).join('')}</div><h2>Oldest replies waiting</h2><p>Customer is waiting · 2 days ago</p><div class="famtastic-ops__actions"><a href="#reply" class="button button--primary">Prepare reply</a><a href="#campaign" class="button">Plan a campaign</a></div><div style="min-height:700px"></div><button id="last-action">Save draft</button></section></main></div></div></body></html>`;
(async()=>{
 const browser=await chromium.launch({headless:true}); const results=[];
 try {
 for(const width of [320,390,1280]) {
  const page=await browser.newPage({viewport:{width,height:844}}); await page.setContent(html);
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw Error(`Overflow at ${width}`);
  for(const target of await page.locator('.famtastic-admin-nav > a, .famtastic-admin-nav summary, .famtastic-owner__queue').all()){
   const box=await target.boundingBox(); if(box.width<44||box.height<44) throw Error(`Target below 44px at ${width}: ${JSON.stringify(box)}`);
  }
  const more=page.locator('summary'); await more.focus(); await page.keyboard.press('Enter');
  if(!await page.locator('details').evaluate(x=>x.open)) throw Error('Keyboard cannot open More');
  const panel=await page.locator('.details-wrapper').boundingBox(); if(panel.x<0||panel.x+panel.width>width+1||panel.y<0) throw Error('More menu outside viewport');
  await page.keyboard.press('Tab'); if(await page.locator(':focus').textContent()!=='Attention') throw Error('More keyboard order lost');
  await more.focus(); await page.keyboard.press('Enter');
  await page.locator('#last-action').scrollIntoViewIfNeeded();
  const last=await page.locator('#last-action').boundingBox(); const nav=await page.locator('.famtastic-admin-nav').boundingBox();
  if(width<783&&last.y+last.height>nav.y) throw Error('Bottom navigation obscures final action');
  await page.screenshot({path:resolve(out,`owner-${width}.png`),fullPage:true}); results.push({width,overflow:false,touch_targets:true,keyboard_more:true,final_action_clear:true}); await page.close();
 }
 } finally { await browser.close(); }
 writeFileSync(resolve(out,'result.json'),JSON.stringify({kind:'static-layout-fixture',results},null,2)); console.log('PASS: owner mobile UI fixture at 320/390/1280; touch targets, keyboard More and final action clearance.');
})().catch(e=>{console.error(e);process.exit(1)});
