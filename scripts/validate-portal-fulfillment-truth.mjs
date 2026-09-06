import assert from 'node:assert/strict';
import { derivePortalFulfillmentState } from '../frontend/src/lib/portalFulfillment.js';

const brandOnly = derivePortalFulfillmentState({
  orders: [{ package: 'Logo and Brand Starter', payment_status: 'paid' }],
  entitlements: [{ entitlement_type: 'brand_starter', status: 'active' }],
  website_requests: [],
  projects: [],
});
assert.equal(brandOnly.show, false);
assert.equal(brandOnly.hasHosting, false);
assert.equal(brandOnly.hasDomain, false);
assert.equal(brandOnly.hasLiveSite, false);

const webBasics = derivePortalFulfillmentState({
  orders: [{ package: 'Starter Mobile Business Foundation', payment_status: 'paid' }],
  entitlements: [
    { entitlement_type: 'website_service', status: 'active' },
    { entitlement_type: 'hosting', status: 'active' },
  ],
  website_requests: [{ public_id: 'request-1' }],
  projects: [{ live_url: '' }],
});
assert.equal(webBasics.show, true);
assert.equal(webBasics.paymentConfirmed, true);
assert.equal(webBasics.hasWebsiteService, true);
assert.equal(webBasics.hasHosting, true);
assert.equal(webBasics.hasDomain, false);
assert.match(webBasics.domainLabel, /still required/);
assert.equal(webBasics.hasLiveSite, false);

const prePurchaseRequest = derivePortalFulfillmentState({
  orders: [],
  entitlements: [],
  website_requests: [{ public_id: 'draft-request-1', status: 'draft' }],
  projects: [],
});
assert.equal(prePurchaseRequest.show, false);
assert.equal(prePurchaseRequest.hasProofWork, true);

console.log('PASS: portal fulfillment claims are entitlement- and project-scoped.');
