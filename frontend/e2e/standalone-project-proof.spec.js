import { expect, test } from '@playwright/test';

const proof = { uuid: 'standalone-proof', label: 'Selected shipping proof', proof_url: 'https://preview.example.test/jjba/', live_url: '', delivery_status: 'proof_delivered', approval_status: 'pending', revision_count: 0, revision_limit: 0, proofs: null };
async function openProjects(page, project) {
  await page.route('**/api/customer/session', route => route.fulfill({ json: { customer: { display_name: 'Proof reviewer', email: 'reviewer@example.test' } } }));
  await page.route('**/api/customer/catalog', route => route.fulfill({ json: { products: [] } }));
  await page.route('**/api/customer/workspace*', route => route.fulfill({ json: {
    organization: { public_id: 'proof-org', name: 'Shipping review', role: 'owner' }, organizations: [],
    projects: [project], orders: [], entitlements: [], website_requests: [], threads: [], activity: [], members: [], referrals: [], articles: [], faqs: [], offers: [],
    analytics: { entitled: false }, preferences: { topics: [] }, topics: {},
  } }));
  await page.goto('/portal/?section=projects');
  await expect(page.getByRole('heading', { name: project.label })).toBeVisible();
}

test('standalone unpaid proof is accessible without invented purchase or concepts', async ({ page }) => {
  await openProjects(page, proof);
  const link = page.getByRole('link', { name: 'Open proof', exact: false });
  await expect(link).toHaveAttribute('href', proof.proof_url);
  await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  await expect(page.getByText('Proof available', { exact: true })).toBeVisible();
  await expect(page.getByText('Paid', { exact: true })).toHaveCount(0);
  await expect(page.getByText('3 concepts', { exact: true })).toHaveCount(0);
  await expect(page.getByRole('heading', { name: 'No website requests yet' })).toHaveCount(0);
  await expect(page.getByText('0 of 0', { exact: true })).toBeVisible();
  const box = await link.boundingBox();
  expect(box.height).toBeGreaterThanOrEqual(44);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBeTruthy();
});

test('unsafe proof URLs do not create an executable link or fake ready state', async ({ page }) => {
  await openProjects(page, { ...proof, proof_url: 'javascript:alert(1)' });
  await expect(page.getByRole('link', { name: 'Open proof', exact: false })).toHaveCount(0);
  await expect(page.getByText('Proof pending', { exact: true })).toBeVisible();
});

test('existing concept counts and live-site link remain grounded in project data', async ({ page }) => {
  await openProjects(page, { ...proof, label: 'Existing website project', live_url: 'https://business.example.test/', proofs: { variants: [{direction_id:'a'}, {direction_id:'b'}, {direction_id:'c'}] } });
  await expect(page.getByText('3 concepts', { exact: true })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Visit live site', exact: false })).toHaveAttribute('href', 'https://business.example.test/');
  await expect(page.getByRole('link', { name: 'Open proof', exact: false })).toHaveCount(0);
});
