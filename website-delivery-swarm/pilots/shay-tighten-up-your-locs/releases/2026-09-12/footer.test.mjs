import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const html = readFileSync(new URL('./index.html', import.meta.url), 'utf8');
test('public footer includes customer copyright and linked agency credit', () => {
  const footer = html.match(/<footer[\s\S]*?<\/footer>/)[0];
  assert.match(footer, /© 2026 Tighten Up Your Locs\. All rights reserved\./);
  assert.match(footer, /href="https:\/\/famtasticdesigns\.com\/">Website by FAMtastic Designs<\/a>/);
  assert.match(footer, /href="#privacy">Privacy<\/a>/);
});
test('compact privacy disclosure replaces the optional analytics panel', () => {
  assert.doesNotMatch(html, /off until you choose Allow/);
  assert.doesNotMatch(html, /id="analytics-(?:status|allow|decline)"/);
  assert.match(html, /cookie storage disabled/);
  assert.match(html, /href="#booking">Booking/);
  assert.match(html, /id="booking"/);
  assert.match(html, /id="request"/);
  assert.match(html, /Request your appointment/);
});
