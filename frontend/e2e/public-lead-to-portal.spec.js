import { expect, test } from '@playwright/test';

const registrationUrl = '/login?mode=register&email=lead%40example.test&business=Proof%20Shop&source=public_quote&request=501&redirect=%2Fportal%3Fstart%3Dwebsite';

async function mockPortal(page) {
  await page.route('**/api/customer/session', (route) => route.fulfill({ json: { customer: { display_name: 'Lead Owner', email: 'lead@example.test' } } }));
  await page.route('**/api/customer/workspace*', (route) => route.fulfill({ json: {
    organization: { public_id: 'org-lead', name: 'Proof Shop', role: 'owner' }, organizations: [],
    projects: [], orders: [], entitlements: [], website_requests: [], threads: [], activity: [], members: [], referrals: [], articles: [], faqs: [], offers: [],
    analytics: { entitled: false }, preferences: { project_email: true, support_email: true, billing_email: true, product_education: true, deals_promotions: true, analytics_digest: 'monthly', topics: [] }, topics: {},
  } }));
}

test('anonymous Solution Finder becomes a saved lead and continues into the detailed portal intake', async ({ page }, testInfo) => {
  let captured;
  await page.route('**/api/public/quote', async (route) => {
    captured = route.request().postDataJSON();
    await route.fulfill({ json: {
      ok: true, status: 'received', request_id: 501, prospect_id: 77,
      preview_level: 'basic', next_step: 'register_for_detailed_demo', registration_url: registrationUrl,
      message: 'Your starter recommendation is ready and your request is saved. Create a free account for a more detailed brief and working design demos.',
    } });
  });

  await page.goto('/start');
  await page.getByRole('button', { name: 'Website', exact: true }).click();
  await page.getByLabel('Business name *').fill('Proof Shop');
  await page.getByLabel('Industry *').fill('Specialty grooming');
  await page.getByLabel('Location *').fill('Broward County, Florida');
  await page.getByRole('button', { name: 'Continue →' }).click();
  await page.getByRole('radio', { name: 'No site yet' }).click();
  await page.getByRole('radio', { name: '1 page' }).click();
  await page.getByRole('button', { name: 'Continue →' }).click();
  await page.getByRole('button', { name: 'No thanks' }).click();
  await page.getByRole('radio', { name: '$199 starter' }).click();
  await page.getByRole('radio', { name: 'Flexible' }).click();
  await page.getByRole('button', { name: 'Continue →' }).click();
  await page.getByLabel('Email *').fill('lead@example.test');
  await page.getByRole('button', { name: 'Get my estimate' }).click();

  await expect(page.getByRole('heading', { name: 'Your starter recommendation' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Get working website demos—not just a basic mockup.' })).toBeVisible();
  const createAccount = page.getByRole('link', { name: 'Create my free account →' });
  await expect(createAccount).toHaveAttribute('href', registrationUrl);
  expect(captured.answers.email).toBe('lead@example.test');
  expect(captured.answers.businessName).toBe('Proof Shop');
  expect(captured.source).toBe('solution-finder');
  await page.screenshot({ path: testInfo.outputPath('public-basic-preview-registration-hook.png'), fullPage: true });

  await createAccount.click();
  await expect(page.getByRole('tab', { name: 'Create account' })).toHaveAttribute('aria-selected', 'true');
  await expect(page.getByLabel('Email')).toHaveValue('lead@example.test');
  await expect(page.getByLabel(/Business name/)).toHaveValue('Proof Shop');
  await expect(page.getByText(/working design demos/i)).toBeVisible();

  await mockPortal(page);
  await page.goto('/portal?start=website');
  await expect(page.getByRole('heading', { name: 'Websites & proofs' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Tell us what you want to build' })).toBeVisible();
  await expect(page.getByLabel(/How does the business make money/)).toBeVisible();
  await expect(page.getByLabel('What should we research before designing?')).toBeVisible();
  await expect(page.getByLabel('Desired domain names')).toBeVisible();
  await expect(page.getByLabel('Business email needs')).toBeVisible();
  await expect(page.getByLabel(/Something not listed/)).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('registered-detailed-intake.png'), fullPage: true });
});
