const { chromium } = require('../frontend/node_modules/@playwright/test');
const fs = require('node:fs');
const phase = process.argv[2] || 'after';
if (!['before', 'after'].includes(phase)) throw new Error('Use before or after');
const dir = '.local-email-preview/content-rollout';
const routes = [
  ['packages', '/packages'], ['package', '/packages/499-site-upgrade'],
  ['services', '/services'], ['solution', '/services/custom-website-development'],
  ['about', '/about'], ['contact', '/contact'],
];
(async () => {
  fs.mkdirSync(dir, { recursive: true }); const browser = await chromium.launch(); const results = [];
  try {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    await context.route('**/*', r => ['GET', 'HEAD'].includes(r.request().method()) ? r.continue() : r.abort());
    for (const [name, route] of routes) for (const width of [390, 1440]) {
      const page = await context.newPage(); await page.setViewportSize({ width, height: 1000 });
      const errors = []; page.on('pageerror', e => errors.push(e.message));
      await page.goto('http://127.0.0.1:4187' + route); await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready);
      await page.screenshot({ path: `${dir}/${phase}-${name}-${width}.png`, fullPage: true });
      await page.screenshot({ path: `${dir}/${phase}-${name}-${width}-hero.png` });
      const detailSelector = { packages: '#comparison', package: '#features', services: '#capabilities', solution: '#system', about: '#fam-definition', contact: '#contact-form' }[name];
      if (phase === 'after') await page.locator(detailSelector).screenshot({ path: `${dir}/${phase}-${name}-${width}-detail.png` });
      results.push({ route, width, heading: await page.locator('main h1').allTextContents(), overflow: await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), errors });
      if (width === 1440) fs.writeFileSync(`${dir}/${phase}-${name}-text.json`, JSON.stringify(await page.locator('main').innerText(), null, 2));
      await page.close();
    }
    fs.writeFileSync(`${dir}/${phase}.json`, JSON.stringify(results, null, 2)); console.log(JSON.stringify(results));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
