const { chromium } = require('../frontend/node_modules/@playwright/test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {createHash} = require('node:crypto');
const output = '.local-email-preview';
const target = process.env.LOGO_TEST_URL || 'http://127.0.0.1:4187/';
const evidence = process.env.LOGO_TEST_LABEL || 'website';
(async () => {
  const hash=createHash('sha256').update(fs.readFileSync('frontend/public/brand/famtastic-designs-logo-v1.png')).digest('hex');
  assert.equal(hash,'ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950');
  const browser=await chromium.launch();const results=[];
  try {
    for(const width of [320,390,768,960,1100,1440]) {
      const page=await browser.newPage({viewport:{width,height:1000},reducedMotion:'reduce'});
      const errors=[];page.on('pageerror',e=>errors.push(e.message));
      await page.goto(target);
      await page.waitForFunction(()=>document.querySelectorAll('.v1-nav a').length>=5);
      await page.evaluate(()=>document.fonts.ready);
      await page.locator('.fam-brand-logo--header').evaluate(i=>i.decode());
      // Await actual animation completion, not an arbitrary screenshot delay.
      await page.waitForFunction(()=>[...document.querySelectorAll('h1')].every(e=>Number(getComputedStyle(e).opacity)===1));
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth),width,`overflow at ${width}`);
      const logo=await page.locator('.fam-brand-logo--header').boundingBox();
      assert.ok(Math.abs(logo.width/logo.height-3)<.02,'Logo must retain 3:1 ratio');
      assert.ok(logo.height>=44,'Home logo touch target');
      const home=page.locator('.v1-brand');assert.equal(await home.getAttribute('href'),'/');
      const nav=page.locator('.v1-nav');assert.equal(await nav.isVisible(),width>=1100);
      if(width<1100){
        const toggle=page.locator('.v1-header__burger');await toggle.click();
        assert.ok(await page.locator('.v1-nav-mobile').isVisible());
        const header=await page.locator('.v1-header__inner').boundingBox();
        const menu=await page.locator('.v1-nav-mobile').boundingBox();
        assert.ok(Math.abs(menu.y-(header.y+header.height))<2,'Menu begins below resized header');
        await toggle.click();assert.equal(await page.locator('.v1-nav-mobile').count(),0);
      }
      if(width===1440||width===390)await page.screenshot({path:`${output}/${evidence}-${width===1440?'desktop':'mobile'}.png`});
      await page.locator('.v1-footer__logo').scrollIntoViewIfNeeded();
      await page.locator('.fam-brand-logo--footer').evaluate(i=>i.decode());
      const footerLogo=await page.locator('.fam-brand-logo--footer').boundingBox();assert.ok(Math.abs(footerLogo.width/footerLogo.height-3)<.02);
      if(width===1440||width===390)await page.locator('.v1-footer').screenshot({path:`${output}/${evidence}-footer-${width}.png`});
      assert.deepEqual(errors,[]);results.push({width,overflow:false,logoRatio:'3:1',navigation:'passed',consoleErrors:0});
      await page.close();
    }
    fs.writeFileSync(`${output}/${evidence}-logo-results.json`,JSON.stringify({scope:'public website presentation only',target,results},null,2));
    console.log('PASS: original asset hash, 6 viewport widths, logo ratios, home links, mobile menus/offset, footer, no console errors.');
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
