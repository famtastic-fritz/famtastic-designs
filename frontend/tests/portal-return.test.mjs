import test from 'node:test';
import assert from 'node:assert/strict';
import { portalReturn } from '../src/pages/portalReturn.js';

test('owner desk destination survives login with query parameters', () => {
  assert.equal(portalReturn('/portal?section=booking'), '/portal?section=booking');
  assert.equal(portalReturn('/portal/?section=booking&site=tighten-up-your-locs'), '/portal/?section=booking&site=tighten-up-your-locs');
  assert.equal(portalReturn('/portal?section=projects#discard'), '/portal?section=projects');
  assert.equal(portalReturn('/buy?sku=example'), '/buy?sku=example');
  assert.equal(portalReturn('/admin'), '/admin');
});

test('login return cannot become an external destination or loop', () => {
  for (const input of [null, '', 'https://evil.test', '//evil.test', '/\\evil.test', '/login', '/portal/../../login', '/portalx', 'javascript:alert(1)', '/portal\n']) {
    assert.equal(portalReturn(input), '/portal', String(input));
  }
});
