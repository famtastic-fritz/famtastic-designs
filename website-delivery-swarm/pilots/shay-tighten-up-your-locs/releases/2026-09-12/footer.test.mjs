import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const html = readFileSync(new URL('./index.html', import.meta.url), 'utf8');
test('public footer includes customer copyright and linked agency credit', () => {
  const footer = html.match(/<footer[\s\S]*?<\/footer>/)[0];
  assert.match(footer, /© 2026 Tighten Up Your Locs\. All rights reserved\./);
  assert.match(footer, /href="https:\/\/famtasticdesigns\.com\/">Website by FAMtastic Designs<\/a>/);
  assert.match(footer, /href="#privacy">Privacy settings<\/a>/);
});
test('privacy copy does not contradict an allowed state or remove visitor controls', () => {
  assert.doesNotMatch(html, /off until you choose Allow/);
  assert.match(html, /id="analytics-status" role="status"/);
  assert.match(html, /id="analytics-allow"/);
  assert.match(html, /id="analytics-decline"/);
});
