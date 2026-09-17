// Pass an installed @playwright/test path; no package download is performed.
const { chromium } = require(process.argv[2] || '@playwright/test');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const output = path.resolve(__dirname, '../../.local-email-preview');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const results = [];
  try {
    for (const width of [320, 390, 660, 768]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      await page.goto('http://127.0.0.1:8765/email.html');
      await page.evaluate(() => Promise.all([...document.images].map(i => i.decode())));
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width);
      const link = page.getByRole('link', { name: /REVIEW YOUR STAGING SITE/ });
      assert.equal(await link.getAttribute('href'), 'https://prosintraining.famtasticinc.com/');
      const box = await link.boundingBox();
      assert.ok(box.height >= 44 && box.width <= width);
      assert.ok(await page.getByText('Hi Valerie,', { exact: true }).isVisible());
      const cells = await page.locator('.stack').first().evaluate(e => getComputedStyle(e).display);
      assert.equal(cells, width <= 640 ? 'block' : 'table-cell');
      if (width === 660 || width === 390) await page.screenshot({ path: path.join(output, width === 660 ? 'email-desktop.png' : 'email-mobile.png'), fullPage: true });
      results.push({width, overflow: false, minTouchTarget: true, stacking: 'passed'});
      await page.close();
    }
    const noImages = await browser.newPage({ viewport: { width: 390, height: 1000 } });
    await noImages.route('**/*', route => route.request().resourceType() === 'image' ? route.abort() : route.continue());
    await noImages.goto('http://127.0.0.1:8765/email.html');
    assert.ok(await noImages.getByText('Hi Valerie,', { exact: true }).isVisible());
    assert.ok(await noImages.getByText(/There’s no payment due/).isVisible());
    assert.ok(await noImages.getByRole('link', { name: /REVIEW YOUR STAGING SITE/ }).isVisible());
    assert.equal(await noImages.evaluate(() => document.documentElement.scrollWidth), 390);
    await noImages.screenshot({path: path.join(output, 'email-images-disabled.png'), fullPage: true});
    await noImages.close();
    for (const width of [390, 768, 1280]) {
      const page = await browser.newPage({viewport: {width, height: 900}});
      await page.goto('http://127.0.0.1:8766/');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width);
      assert.equal(await page.locator('meta[name="robots"]').getAttribute('content'), 'noindex, nofollow, noarchive');
      assert.ok(await page.getByText('What is a staging site?', {exact:true}).count() === 1);
      if (width !== 768) await page.locator('footer').screenshot({path:path.join(output, width === 390 ? 'footnote-mobile.png' : 'footnote-desktop.png')});
      await page.close();
    }
    const gallery = await browser.newPage({viewport:{width:1560,height:2200}});
    await gallery.goto('http://127.0.0.1:8765/');
    await gallery.screenshot({path:path.join(output, 'comparison.png'), fullPage:true});
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({email:results, imagesDisabled:'passed', footnoteWidths:[390,768,1280], actualEmailClients:'not tested', sent:false, deployed:false},null,2));
    console.log('PASS: 4 email widths, images disabled, links, stacking, touch targets, 3 footnote widths. No external navigation or sending.');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1});
