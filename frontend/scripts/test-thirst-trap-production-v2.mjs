import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, '../public/showcase/thirst-trap-772/v2');
const read = (relative) => readFile(path.join(root, relative), 'utf8');

const [html, css, js, ownerHtml, ownerCss, ownerJs] = await Promise.all([
  read('index.html'),
  read('thirst-trap-v2.css'),
  read('thirst-trap-v2.js'),
  read('owner/index.html'),
  read('owner/owner-demo.css'),
  read('owner/owner.js'),
]);

const sectionIds = [...html.matchAll(/data-component-instance-id="([^"]+)"/g)].map((match) => match[1]);
assert.equal(sectionIds.length, 8, 'the production page keeps eight explicit component instances');
assert.equal(new Set(sectionIds).size, sectionIds.length, 'component instance IDs are unique');
assert.match(html, /id="contact-form"/);
assert.match(html, /id="subscribe-form"/);
assert.match(html, /id="preorder-form"/);
assert.match(html, /id="cash-app-qr"/);
assert.match(html, /qrcode-generator-1\.4\.4\.js/);
assert.match(html, /data-social="instagram"/);
assert.match(html, /data-social="facebook"/);
assert.match(html, /<svg[^>]+viewBox="0 0 24 24"/);
assert.match(html, /A BUSINESS EXPERIENCE BY/);
assert.match(html, /Admin demo/);
assert.match(html, /Public preview · no sign-in/);
assert.match(html, /href="\/portal"/);
assert.match(html, /A BUSINESS EXPERIENCE BY/);
assert.match(html, /FAM<span>tastic<\/span>/);
assert.doesNotMatch(html, /\b(?:Fritz|Shay|gift|concept|mockup|I built|we built)\b/i, 'public copy is the business voice, not a pitch to the owner');

assert.match(css, /@media \(max-width: 680px\)/);
assert.match(css, /@media \(prefers-reduced-motion: reduce\)/);
assert.match(css, /thirst-trap-v2-hero\.webp/);
assert.match(css, /thirst-trap-v2-ice\.webp/);
assert.match(css, /thirst-trap-v2-menu\.webp/);
assert.match(css, /thirst-trap-v2-pop-up\.webp/);
assert.match(css, /\.button \{[^}]*font-family: var\(--utility\)[^}]*font-size: \.84rem[^}]*letter-spacing: \.01em/s);
assert.match(css, /\.kicker \{[^}]*font-size: \.75rem[^}]*letter-spacing: \.09em/s);
assert.match(css, /\.system-links/);
assert.match(css, /\.famtastic-credit/);

assert.match(js, /\/web\/api\/microsite\/\$\{SITE_KEY\}/);
assert.match(js, /submitCapture\(contact, 'contact'/);
assert.match(js, /submitCapture\(subscribe, 'subscriber'/);
assert.match(js, /submitPreorder/);
assert.match(js, /\/preorder/);
assert.match(js, /payment\.available/);
assert.match(js, /credentials: 'omit'/);

assert.match(ownerHtml, /noindex,nofollow,noarchive/);
assert.match(ownerHtml, /id="product-editor"/);
assert.match(ownerHtml, /id="event-editor"/);
assert.match(ownerHtml, /id="message-list"/);
assert.match(ownerHtml, /id="order-list"/);
assert.match(ownerHtml, /id="preorders-enabled"/);
assert.match(ownerHtml, /id="cash-app-url"/);
assert.match(ownerHtml, /id="social-instagram"/);
assert.match(ownerJs, /\/web\/session\/token/);
assert.match(ownerJs, /method: 'PUT'/);
assert.match(ownerJs, /method:'PATCH'/);
assert.match(ownerJs, /updateOrder/);
assert.match(ownerJs, /price_cents/);
assert.match(ownerJs, /IS_DEMO/);
assert.match(ownerJs, /demoState/);
assert.match(ownerJs, /No account, customer, or live website was changed/);
assert.match(ownerHtml, /PUBLIC ADMIN DEMO/);
assert.match(ownerCss, /\.demo-notice/);

for (const asset of [
  'assets/thirst-trap-v2-hero.webp',
  'assets/thirst-trap-v2-ice.webp',
  'assets/thirst-trap-v2-menu.webp',
  'assets/thirst-trap-v2-pop-up.webp',
]) {
  const info = await stat(path.join(root, asset));
  assert.ok(info.size > 100_000, `${asset} is a substantive visual asset`);
  assert.ok(info.size < 900_000, `${asset} remains web-delivery sized`);
}

console.log('PASS: Thirst Trap v2 has eight reusable components, four optimized art assets, durable preorder/direct-pay UI, production forms, themed social links, owner controls, and mobile/reduced-motion contracts.');
