import test from 'node:test';
import assert from 'node:assert/strict';
import { acceptWebsiteStagingReview } from '../frontend/src/api/customer.js';
import { acceptDisplayedStagingReview } from '../frontend/src/api/stagingReview.js';

test('displayed receipt traverses actual API with CSRF; stale response refreshes without accepting replacement', async () => {
  const original = globalThis.fetch;
  const calls = [];
  let refreshed = 0;
  let current = 'a'.repeat(64);
  globalThis.fetch = async (url, options) => {
    calls.push({ url, options });
    if (url.endsWith('/session/token')) return { text: async () => 'synthetic-csrf' };
    return { ok: false, status: 422, json: async () => ({ error: 'staging_review_not_ready', message: 'Refresh and review the current artifact.' }) };
  };
  try {
    await assert.rejects(acceptDisplayedStagingReview('synthetic-request', current, {
      accept: acceptWebsiteStagingReview,
      refresh: async () => { refreshed++; current = 'b'.repeat(64); },
    }), /Refresh and review/);
    assert.equal(calls.length, 2);
    assert.deepEqual(JSON.parse(calls[1].options.body), { receipt_hash: 'a'.repeat(64) });
    assert.equal(calls[1].options.headers['X-CSRF-Token'], 'synthetic-csrf');
    assert.match(calls[1].url, /staging-review\/accept$/);
    assert.equal(refreshed, 1);
    assert.equal(current, 'b'.repeat(64));
  } finally { globalThis.fetch = original; }
});

test('successful exact revision acceptance refreshes once and does not invoke checkout', async () => {
  let accepted = 0, refreshed = 0;
  const result = await acceptDisplayedStagingReview('synthetic-request', 'c'.repeat(64), {
    accept: async (id, hash) => { accepted++; assert.equal(hash, 'c'.repeat(64)); return { website_request: { staging_review_status: 'accepted' } }; },
    refresh: async () => { refreshed++; },
  });
  assert.equal(accepted, 1); assert.equal(refreshed, 1);
  assert.equal(result.website_request.staging_review_status, 'accepted');
});

test('missing displayed receipt cannot send acceptance', async () => {
  await assert.rejects(acceptDisplayedStagingReview('synthetic-request', '', { accept: () => assert.fail('must not send'), refresh: () => assert.fail('must not refresh') }), /Refresh and review/);
});
