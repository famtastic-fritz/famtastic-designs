import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import test from 'node:test';
import { SEO_PAGES, seoForPath } from '../src/seo.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const read = (path) => readFile(resolve(root, path), 'utf8');
const [app, seoSource, page, campaign, script, dealConfig, productConfig] = await Promise.all([
  read('frontend/src/App.jsx'),
  read('frontend/src/seo.js'),
  read('frontend/src/pages/WhyFamtasticPage.jsx'),
  read('frontend/src/pages/FiftyFiveCentWebsitePage.jsx'),
  read('marketing/brands/famtastic/video-studio/whats-the-catch/user-script.txt'),
  read('backend/config/famtastic-deal-terms.json').then(JSON.parse),
  read('backend/config/famtastic-products.json').then(JSON.parse),
]);

test('the public route has a canonical indexable page and uses the film poster for sharing', () => {
  assert.match(app, /path="\/why-famtastic" element=\{<WhyFamtasticPage \/>\}/);
  assert.match(app, /import WhyFamtasticPage from '\.\/pages\/WhyFamtasticPage\.jsx'/);
  assert.equal(SEO_PAGES['/why-famtastic'].image, '/media/films/whats-the-catch-20260919.jpg');
  assert.equal(seoForPath('/why-famtastic/').canonical, 'https://famtasticdesigns.com/why-famtastic/');
  assert.equal(seoForPath('/why-famtastic').image, 'https://famtasticdesigns.com/media/films/whats-the-catch-20260919.jpg');
  assert.match(seoSource, /title: 'You Grow\. We Grow\. \| What’s the Catch\? \| FAMtastic Designs'/);
  assert.match(seoSource, /image: page\.image \? `\$\{SITE_URL\}\$\{page\.image\}` : DEFAULT_IMAGE/);
});

test('the film has native controls, no autoplay, a poster, captions, and a complete source-script transcript', () => {
  assert.match(page, /controls[\s\S]*?playsInline[\s\S]*?preload="metadata"/);
  assert.doesNotMatch(page, /\bautoPlay\b|\bautoplay\b/);
  assert.match(page, /poster=\{FILM\.poster\}/);
  assert.match(page, /<track kind="captions" src=\{FILM\.captions\} srcLang="en-US" label="English captions" \/>/);
  assert.doesNotMatch(page.match(/<track\b[^>]*>/)?.[0] || '', /\bdefault\b/, 'text captions remain optional because the film contains open captions');
  assert.match(page, /import narration from '\.\.\/\.\.\/\.\.\/marketing\/brands\/famtastic\/video-studio\/whats-the-catch\/user-script\.txt\?raw'/);
  assert.match(page, /narration\.trim\(\)\.split\(\/\\r\?\\n\/\)\.filter\(Boolean\)/);
  assert.match(page, /transcriptLines\.map\(\(line, index\) => <p key=\{`\$\{index\}-\$\{line\}`\}>\{line\}<\/p>\)/);
  assert.ok(script.includes('You grow. We grow.'));
  assert.ok(script.includes('FAMtasticDesigns.com'));
  assert.match(page, /title="You grow\. We grow\."/);
  assert.match(page, /video: '\/media\/films\/whats-the-catch-20260919\.mp4'/);
  assert.match(page, /captions: '\/media\/films\/whats-the-catch-20260919\.vtt'/);
});

test('the $199 copy matches the canonical SKU, scope, exclusions, and renewal terms', () => {
  const product = productConfig.products.find((item) => item.sku === 'FAM-FOOT-199');
  const terms = dealConfig.deals['FAM-FOOT-199'];
  assert.ok(product, 'FAM-FOOT-199 remains the canonical offer');
  assert.equal(product.price, '199.00');
  assert.equal(product.billing.kind, 'one_time');
  assert.match(page, /WEB_BASICS\.priceLabel/);
  assert.match(page, /WEB_BASICS\.title/);
  for (const [phrase, label] of [
    ['One owner-approved research snapshot', 'research snapshot'],
    ['Exactly three research-backed proof directions before checkout', 'three proofs before checkout'],
    ['One included design reset', 'one reset'],
    ['up to three selected-direction edit rounds', 'three edit rounds'],
    ['Twelve months of basic managed hosting', 'hosting year'],
    ['Mailboxes are separate subscriptions', 'mailbox exclusion'],
    ['FAMtastic does not process those customer payments', 'payment-processing boundary'],
  ]) {
    assert.ok(terms.deliverables.some((item) => item.includes(phrase)) || terms.included.some((item) => item.includes(phrase)) || page.includes(phrase), `${label} is represented`);
  }
  assert.ok(terms.renewal.hosting.includes('$9.99 per month'));
  assert.match(page, /\$9\.99 per month only if you give separate recurring-payment authorization/);
  assert.match(page, /annual renewal is a separate prepaid registrar charge/);
  assert.match(page, /The actual price is disclosed before payment/);
  assert.match(page, /Connecting a domain you already own does not create a FAMtastic domain-renewal charge/);
  assert.match(page, /do not guarantee traffic, rankings, leads, bookings, sales, or business results/);
  assert.match(page, /to="\/start\?option=web-basics"/);
  assert.doesNotMatch(page, /to="\/buy(?:\?|\")/);
});

test('the existing $199 landing page points visitors to the new film page', () => {
  assert.match(campaign, /to="\/why-famtastic"/);
  assert.match(campaign, /Hear the thinking behind the offer/);
});
