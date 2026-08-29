import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../public/showcase/omar-top-deals');
const [publicHtml, ownerHtml, css, js, v2Html, v2Css, v2Js] = await Promise.all([
  readFile(path.join(root, 'index.html'), 'utf8'),
  readFile(path.join(root, 'owner/index.html'), 'utf8'),
  readFile(path.join(root, 'top-deals.css'), 'utf8'),
  readFile(path.join(root, 'top-deals.js'), 'utf8'),
  readFile(path.join(root, 'v2/index.html'), 'utf8'),
  readFile(path.join(root, 'v2/v2.css'), 'utf8'),
  readFile(path.join(root, 'v2/v2.js'), 'utf8')
]);

assert.match(publicHtml, /GOOD FINDS\.<br><em>RIGHT ON TIME\.<\/em>/);
assert.match(publicHtml, /data-component-system="omar-top-deals-components-v1"/);
assert.match(publicHtml, /Shay — FAMtastic AI Business Concierge/);
assert.match(publicHtml, /Nothing here charges, emails, reserves inventory, or changes a real account/);
assert.match(publicHtml, /id="event-map-link"/);
assert.match(publicHtml, /id="contact-form"/);
assert.match(publicHtml, /id="hold-form"/);
assert.match(publicHtml, /id="flywheel"/);
assert.match(publicHtml, /ONE HANDOUT\.<br><em>A WHOLE DIGITAL RUNWAY\.<\/em>/);
assert.match(publicHtml, /THE UPGRADE IS NOT “STOP PRINTING\.”/);
assert.match(publicHtml, /marketing\.flyer-to-follow\.v1/);
assert.match(ownerHtml, /Top Deals Control/);
assert.match(ownerHtml, /data-owner-panel="table"/);
assert.match(ownerHtml, /demo value/i);
assert.match(ownerHtml, /data-owner-panel="holds"/);
assert.match(ownerHtml, /data-owner-panel="events"/);
assert.match(ownerHtml, /data-owner-panel="front-door"/);
assert.match(ownerHtml, /data-owner-panel="social"/);
assert.match(ownerHtml, /data-owner-panel="launch"/);
assert.match(ownerHtml, /Recommended handle · availability not checked/);
assert.match(ownerHtml, /Copy draft caption/);
assert.match(ownerHtml, /availability not checked/i);
assert.match(ownerHtml, /not activated/i);
assert.match(ownerHtml, /FAMtastic does not become the payment processor/i);
assert.match(js, /famtastic\.omar-top-deals\.v1/);
assert.doesNotMatch(js, /\bfetch\s*\(/, 'prototype must not call an application API');
assert.doesNotMatch(js, /stripe|smtp|twilio/i, 'prototype must not imply connected payment or messaging');
assert.match(css, /prefers-reduced-motion/);
assert.match(css, /@media \(max-width: 390px\)/);
assert.match(css, /--orange: #f05a28/);
assert.match(publicHtml, /See flyer-inspired V2/);
assert.match(ownerHtml, /See flyer Version 2/);
assert.match(v2Html, /OMAR<\/span><strong>TOP DEALS/);
assert.match(v2Html, /THE TABLE,<br><em>WITH THE VALUE UP FRONT\.<\/em>/);
assert.match(v2Html, /DEMO VALUES, NOT LIVE PRICES/);
assert.match(v2Html, /data-page-template-id="flyer-storefront-v2"/);
assert.match(v2Html, /catalog\.value-wall\.v2/);
assert.equal((v2Html.match(/class="product-card/g) || []).length, 4);
assert.equal((v2Html.match(/DEMO VALUE/g) || []).length, 5);
assert.match(v2Html, /\$25/);
assert.match(v2Html, /\$30/);
assert.match(v2Html, /\$20\+/);
assert.match(v2Html, /Shay — FAMtastic AI Business Concierge/);
assert.match(v2Html, /Nothing is emailed, texted, reserved, or charged/);
assert.match(v2Css, /@media \(max-width: 390px\)/);
assert.match(v2Css, /prefers-reduced-motion/);
assert.match(v2Css, /--ember: #e53f1b/);
assert.match(v2Js, /famtastic\.omar-top-deals\.v1/);
assert.match(v2Js, /data-demo-value/);
assert.match(js, /valueNote/);
assert.doesNotMatch(v2Js, /\bfetch\s*\(/, 'v2 must not call an application API');
assert.doesNotMatch(v2Js, /stripe|smtp|twilio/i, 'v2 must not imply connected payment or messaging');

for (const relative of [
  'assets/hero-selected.webp',
  'assets/hero-evening.webp',
  'assets/hero-marketday.webp',
  'assets/omar-and-fritz.webp',
  'assets/field-market.webp',
  'assets/current-flyer-safe.webp',
  'assets/social-feed-finds.webp',
  'assets/social-story-pop-up.webp',
  'assets/social-follow-up.webp',
  'assets/omar-top-deals-qr.png',
  'assets/v2-market-table.jpg',
  'assets/v2-mobile-market.jpg',
  'assets/v2-social-card.webp'
]) {
  const file = await stat(path.join(root, relative));
  assert.ok(file.size > 3_000, `${relative} should be a substantive local asset`);
}

console.log('PASS Omar Top Deals: V1 plus distinct flyer-inspired V2, four demo-value product families, 7 owner panels, shared local state, 13 local assets, zero application API effects.');
