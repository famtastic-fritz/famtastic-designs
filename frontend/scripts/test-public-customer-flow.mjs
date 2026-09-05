import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { linkHref } from '../src/utils/content.js';

assert.equal(linkHref('internal:/web/start?option=web-basics'), '/start?option=web-basics');
assert.equal(linkHref({ uri: 'internal:/web/website-options' }), '/website-options');
assert.equal(linkHref('internal:/web'), '/');
assert.equal(linkHref('internal:/web/api/customer/session'), '/web/api/customer/session');
assert.equal(linkHref('https://example.com/next'), 'https://example.com/next');

const [home, finder, purchase] = await Promise.all([
  readFile(new URL('../src/pages/HomePage.jsx', import.meta.url), 'utf8'),
  readFile(new URL('../src/components/SolutionFinder.jsx', import.meta.url), 'utf8'),
  readFile(new URL('../src/pages/PurchasePage.jsx', import.meta.url), 'utf8'),
]);

assert.ok(!home.includes('<StatsBar'), 'public home must not render unverified metrics');
assert.ok(!home.includes("href: '/buy'"), 'public home must not promote direct website checkout');
assert.ok(finder.includes('res?.ok !== true || !res?.request_id'), 'Solution Finder must require a server-confirmed request');
assert.ok(finder.includes('Try saving again'), 'Solution Finder must offer recovery after a failed submission');
assert.ok(finder.includes('registration_url'), 'Solution Finder must continue from the server-provided registration URL');
assert.ok(finder.includes('onClick={() => openChat()}'), 'the Finder launcher must not pass a click event as a service branch');
assert.ok(purchase.includes('const checkoutEligible'), 'website payment UI must respect server-provided eligibility');
assert.ok(!purchase.includes('checked={renewal} onChange={(e) => setRenewal(e.target.checked)} required'), 'recurring authorization must remain optional');

console.log('Public customer-flow contract checks passed.');
