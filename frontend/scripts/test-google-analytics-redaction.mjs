import assert from 'node:assert/strict';
import test from 'node:test';
import { safeEventParams, safeLocation } from '../src/lib/googleAnalytics.js';

const continuation = '123e4567-e89b-12d3-a456-426614174000.0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

globalThis.window = {
  location: { href: `https://famtasticdesigns.com/login?mode=register&continuation=${continuation}&redirect=%2Fportal%3Fstart%3Dwebsite` },
};

test('removes a signed continuation from router page path and page location', () => {
  const safe = safeLocation(`/login?mode=register&continuation=${continuation}&redirect=%2Fportal%3Fstart%3Dwebsite`);

  assert.equal(safe.path, '/login?mode=register&redirect=%2Fportal%3Fstart%3Dwebsite');
  assert.equal(new URL(safe.location).searchParams.get('continuation'), null);
  assert.ok(!JSON.stringify(safe).includes(continuation));
});

test('removes signed continuations from custom event parameters while retaining ordinary navigation data', () => {
  const params = safeEventParams({
    page_location: `/login?mode=register&continuation=${continuation}`,
    page_path: `/login?mode=register&continuation=${continuation}`,
    link_url: `https://famtasticdesigns.com/login?continuation=${continuation}&utm_source=email`,
    preview_continuation: continuation,
    direct_value: continuation,
    item_category: 'package',
  });

  assert.equal(params.page_path, '/login?mode=register');
  assert.equal(new URL(params.page_location).searchParams.get('continuation'), null);
  assert.equal(new URL(params.link_url).searchParams.get('continuation'), null);
  assert.equal(params.link_url.includes('utm_source=email'), true);
  assert.equal('preview_continuation' in params, false);
  assert.equal('direct_value' in params, false);
  assert.equal(params.item_category, 'package');
  assert.ok(!JSON.stringify(params).includes(continuation));
});
