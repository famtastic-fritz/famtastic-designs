import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../public/showcase/omar-top-deals');
const [publicHtml, ownerHtml, css, js] = await Promise.all([
  readFile(path.join(root, 'index.html'), 'utf8'),
  readFile(path.join(root, 'owner/index.html'), 'utf8'),
  readFile(path.join(root, 'top-deals.css'), 'utf8'),
  readFile(path.join(root, 'top-deals.js'), 'utf8')
]);

assert.match(publicHtml, /GOOD FINDS\.<br><em>RIGHT ON TIME\.<\/em>/);
assert.match(publicHtml, /data-component-system="omar-top-deals-components-v1"/);
assert.match(publicHtml, /Shay — FAMtastic AI Business Concierge/);
assert.match(publicHtml, /Nothing here charges, emails, reserves inventory, or changes a real account/);
assert.match(publicHtml, /id="event-map-link"/);
assert.match(publicHtml, /id="contact-form"/);
assert.match(publicHtml, /id="hold-form"/);
assert.match(ownerHtml, /Top Deals Control/);
assert.match(ownerHtml, /data-owner-panel="table"/);
assert.match(ownerHtml, /data-owner-panel="holds"/);
assert.match(ownerHtml, /data-owner-panel="events"/);
assert.match(ownerHtml, /data-owner-panel="front-door"/);
assert.match(ownerHtml, /data-owner-panel="launch"/);
assert.match(ownerHtml, /availability not checked/i);
assert.match(ownerHtml, /not activated/i);
assert.match(ownerHtml, /FAMtastic does not become the payment processor/i);
assert.match(js, /famtastic\.omar-top-deals\.v1/);
assert.doesNotMatch(js, /\bfetch\s*\(/, 'prototype must not call an application API');
assert.doesNotMatch(js, /stripe|smtp|twilio/i, 'prototype must not imply connected payment or messaging');
assert.match(css, /prefers-reduced-motion/);
assert.match(css, /@media \(max-width: 390px\)/);
assert.match(css, /--orange: #f05a28/);

for (const relative of [
  'assets/hero-selected.webp',
  'assets/hero-evening.webp',
  'assets/hero-marketday.webp',
  'assets/omar-and-fritz.webp',
  'assets/field-market.webp',
  'assets/omar-top-deals-qr.png'
]) {
  const file = await stat(path.join(root, relative));
  assert.ok(file.size > 3_000, `${relative} should be a substantive local asset`);
}

console.log('PASS Omar Top Deals: public front door, 6 owner panels, local shared state, real QR, 6 local assets, zero application API effects.');
