import { expect, test } from '@playwright/test';

const emptyWorkspace = (websiteRequests = []) => ({
  organization: { public_id: 'org-locs', name: 'Tighten Up Your Locs', role: 'owner' },
  organizations: [],
  projects: [],
  orders: [],
  entitlements: [],
  website_requests: websiteRequests,
  threads: [],
  activity: [],
  members: [],
  referrals: [],
  articles: [],
  faqs: [],
  offers: [],
  analytics: { entitled: false },
  preferences: {
    project_email: true,
    support_email: true,
    billing_email: true,
    product_education: true,
    deals_promotions: true,
    analytics_digest: 'monthly',
    topics: [],
  },
  topics: {},
});

test('a normal Booksy address is normalized before the deep-dive answer is saved', async ({ page }) => {
  const deepDive = {
    invitation: {
      public_id: 'locs-invitation',
      business_name: 'Tighten Up Your Locs',
      status: 'active',
      completed: false,
      progress: { complete: 7, total: 18 },
    },
    question: {
      key: 'booksy_url',
      title: 'What is your current Booksy or booking link?',
      help: 'Paste the public link only. You can paste booksy.com/... — we add https:// when it is missing.',
      type: 'url',
      required: true,
    },
  };
  let savedAnswer = '';

  await page.route('**/api/deep-dive/locs-invitation', (route) => route.fulfill({ json: { ok: true, deep_dive: deepDive } }));
  await page.route('**/api/deep-dive/locs-invitation/answer', async (route) => {
    savedAnswer = route.request().postDataJSON().answer;
    await route.fulfill({ json: { ok: true, deep_dive: deepDive } });
  });

  await page.goto('/deep-dive/locs-invitation#private-token');
  const answer = page.getByLabel('What is your current Booksy or booking link?');
  await answer.fill('booksy.com/tighten-up-your-locs');
  await answer.blur();
  await expect(answer).toHaveValue('https://booksy.com/tighten-up-your-locs');
  await page.getByRole('button', { name: 'Save and continue →' }).click();
  await expect.poll(() => savedAnswer).toBe('https://booksy.com/tighten-up-your-locs');
});

test('a linked draft says what remains and cannot be falsely dispatched to Site Studio', async ({ page }) => {
  await page.route('**/api/customer/session', (route) => route.fulfill({ json: { customer: { display_name: 'Locs Owner', email: 'owner@example.test' } } }));
  await page.route('**/api/customer/catalog', (route) => route.fulfill({ json: { catalog: [] } }));
  await page.route('**/api/customer/workspace*', (route) => route.fulfill({ json: emptyWorkspace([{
    public_id: 'locs-brief',
    project_name: 'Tighten Up Your Locs website',
    status: 'draft',
    proof_review_status: 'not_started',
    changed: 1788406461,
    intake: {},
    proofs: { variants: [] },
    assets: [],
    proof_handoff: {
      state: 'draft',
      label: 'Finish and submit your brief',
      detail: 'Your private interview is connected to this account. Finish the brief before FAMtastic can queue a proof run.',
    },
  }]) }));

  await page.goto('/portal?section=projects');
  await expect(page.getByRole('heading', { name: 'Tighten Up Your Locs website' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Finish and submit your brief' })).toBeVisible();
  await expect(page.getByText('Your private interview is connected to this account. Finish the brief before FAMtastic can queue a proof run.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Finish and submit brief →' })).toBeVisible();
  await expect(page.getByText(/Site Studio is assembling/i)).toHaveCount(0);
  await expect(page.getByRole('button', { name: /send to site studio/i })).toHaveCount(0);
});
