import test from 'node:test';
import assert from 'node:assert/strict';
import { ownerSitePresentation } from '../src/api/ownerSitePresentation.js';

test('verified production Locs site receives Ruby Signal without changing identity', () => {
  const site = { site_key: 'site-dffd4cb9c3aa47fd', business_name: 'Tighten Up Your Locs' };
  const result = ownerSitePresentation(site);
  assert.equal(result.site_key, site.site_key);
  assert.equal(result.business_name, site.business_name);
  assert.equal(result.brand_id, 'ruby-signal');
  assert.equal(result.theme['--od-accent'], '#8f1831');
  assert.equal(site.theme, undefined);
});
test('fixture alias retains styling and unrelated businesses remain neutral', () => {
  assert.equal(ownerSitePresentation({ site_key: 'tighten-up-your-locs' }).brand_id, 'ruby-signal');
  const other = { site_key: 'site-other', business_name: 'Tighten Up Your Locs' };
  assert.equal(ownerSitePresentation(other), other);
  assert.equal(ownerSitePresentation(other).brand_id, undefined);
});
