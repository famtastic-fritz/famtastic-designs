import test from 'node:test';
import assert from 'node:assert/strict';
import { seoForPath } from '../src/seo.js';
import { safeLocation } from '../src/lib/googleAnalytics.js';

test('proposal metadata excludes tokens and forbids indexing/referrers', () => {
  const seo = seoForPath('/appointment/00000000-0000-4000-8000-000000000001');
  assert.equal(seo.robots, 'noindex, nofollow, noarchive');
  assert.equal(seo.referrer, 'no-referrer');
  assert.ok(!seo.canonical.includes('00000000'));
});

test('even explicit analytics locations redact appointment identity and bearer credentials', () => {
  globalThis.window = { location: { href: 'https://famtasticdesigns.com/appointment/id?token=secret#token=other' } };
  try {
    assert.deepEqual(safeLocation('/appointment/id?token=secret#token=other'), { path: '/appointment/private', location: 'https://famtasticdesigns.com/appointment/private' });
  } finally { delete globalThis.window; }
});
