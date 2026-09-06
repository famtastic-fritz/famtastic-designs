import { expect, test } from '@playwright/test';

const email = process.env.FAMTASTIC_E2E_CUSTOMER_EMAIL;
const password = process.env.FAMTASTIC_E2E_CUSTOMER_PASSWORD;

const emptyWorkspace = (websiteRequests = []) => ({
  organization: { public_id: 'org-proof', name: 'Tighten Up Your Locs', role: 'owner' },
  organizations: [], projects: [], orders: [], entitlements: [], website_requests: websiteRequests,
  threads: [], activity: [], members: [], referrals: [], articles: [], faqs: [], offers: [],
  analytics: { entitled: false },
  preferences: { project_email: true, support_email: true, billing_email: true, product_education: true, deals_promotions: true, analytics_digest: 'monthly', topics: [] },
  topics: {},
});

async function assertNoHorizontalOverflow(page) {
  await expect.poll(() => page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    content: document.documentElement.scrollWidth,
  }))).toEqual(expect.objectContaining({ viewport: expect.any(Number) }));
  const sizes = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    content: document.documentElement.scrollWidth,
  }));
  expect(sizes.content, `horizontal overflow: ${JSON.stringify(sizes)}`).toBeLessThanOrEqual(sizes.viewport + 1);
}

async function signIn(page, redirect = '/portal') {
  test.skip(!email || !password, 'Controlled test-customer credentials are required.');
  await page.goto(`/login?redirect=${encodeURIComponent(redirect)}`);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.locator('form button[type="submit"], form button:not([type])').first().click();
  await expect(page).toHaveURL(new RegExp(redirect.replace('/', '\\/')));
}

async function mockNewCustomerPortal(page) {
  await page.route('**/api/customer/session', (route) => route.fulfill({ json: { customer: { display_name: 'Fritz', email: 'fitzgerald.medine@gmail.com' } } }));
  await page.route('**/api/customer/catalog', (route) => route.fulfill({ json: { products: [] } }));
  await page.route('**/api/customer/workspace*', (route) => route.fulfill({ json: {
    organization: { public_id: 'org-proof', name: 'Fritzoo', role: 'owner' },
    projects: [], orders: [], entitlements: [], website_requests: [], threads: [], activity: [], members: [], referrals: [], articles: [], faqs: [], offers: [],
    analytics: { entitled: false }, preferences: { project_email: true, support_email: true, billing_email: true, product_education: true, deals_promotions: true, analytics_digest: 'monthly', topics: [] }, topics: {},
  } }));
}

async function mockProofReviewPortal(page) {
  let websiteRequest = {
    public_id: 'request-proof-review', project_name: 'Tighten Up Your Locs website', status: 'submitted', proof_review_status: 'notified', changed: 1787020000,
    intake: { recommendation: { label: 'Web Basics Bundle', reasons: ['A focused website is the smallest useful path.'] } }, direct_checkout_available: false,
    proof_share: { enabled: false, url: '', changed_at: null },
    proofs: { campaign_id: 'campaign-proof-review', generation_status: 'ready', selected_variant: '', research_snapshot: {
      overview: 'Port St. Lucie clients need a faster path from style discovery to a booking request.',
      direction_rationale: { a: 'A familiar service-first path.', b: 'A visual portfolio path.', c: 'A bold mobile booking path.' },
      market_signals: ['Mobile visitors need services and availability quickly.'],
      opportunities: ['Turn portfolio views into first-party booking requests.'],
      sources: ['Customer interview', 'Local market review'],
      researched_at: '2026-09-05',
    }, review_terms: { design_reset_remaining: 1, edit_rounds_remaining: 3 }, variants: [
      { direction_id: 'a', direction_name: 'Safe', preview_url: 'https://example.test/proofs/a' },
      { direction_id: 'b', direction_name: 'Wild', preview_url: 'https://example.test/proofs/b' },
      { direction_id: 'c', direction_name: 'OMG', preview_url: 'https://example.test/proofs/c' },
    ] },
  };
  const workspace = () => ({
    organization: { public_id: 'org-proof', name: 'Tighten Up Your Locs', role: 'owner' },
    projects: [], orders: [], entitlements: [], website_requests: [websiteRequest], threads: [], activity: [], members: [], referrals: [], articles: [], faqs: [], offers: [],
    analytics: { entitled: false }, preferences: { project_email: true, support_email: true, billing_email: true, product_education: true, deals_promotions: true, analytics_digest: 'monthly', topics: [] }, topics: {},
  });
  await page.route('**/api/customer/session', (route) => route.fulfill({ json: { customer: { display_name: 'Shay', email: 'shay@example.test' } } }));
  await page.route('**/api/customer/catalog', (route) => route.fulfill({ json: { products: [] } }));
  await page.route('**/api/customer/workspace*', (route) => route.fulfill({ json: workspace() }));
  await page.route('**/session/token', (route) => route.fulfill({ body: 'mock-csrf-token' }));
  await page.route('**/api/customer/website-requests/*/proof-decision', async (route) => {
    const payload = route.request().postDataJSON();
    if (payload.action === 'select') {
      websiteRequest = { ...websiteRequest, proof_review_status: 'selected', selected_proof_direction: payload.direction, direct_checkout_available: true, proofs: { ...websiteRequest.proofs, selected_variant: payload.direction } };
    } else {
      websiteRequest = { ...websiteRequest, proof_review_status: 'revision_requested', intake: { ...websiteRequest.intake, proof_revision_request: { notes: payload.notes, requested_at: '2026-08-18T04:00:00Z' } } };
    }
    await route.fulfill({ json: { ok: true, website_request: websiteRequest } });
  });
  await page.route('**/api/customer/website-requests/*/proof-share', async (route) => {
    const { action } = route.request().postDataJSON();
    const enabled = action !== 'disable';
    const suffix = action === 'rotate' ? 'new-signature' : 'first-signature';
    websiteRequest = { ...websiteRequest, proof_share: { enabled, url: enabled ? `https://example.test/proofs/share/request-proof-review/${suffix}` : '', changed_at: 1787020100 } };
    await route.fulfill({ json: { ok: true, website_request: websiteRequest } });
  });
}

test('portal home makes the website-and-proofs revenue journey unmistakable', async ({ page }, testInfo) => {
  await mockNewCustomerPortal(page);
  await page.goto('/portal');
  await expect(page.getByRole('heading', { name: 'Your business systems, all in one place.' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Start my website & proofs', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Play tutorial', exact: true }).click();
  await expect(page.getByRole('dialog', { name: 'Register' })).toBeVisible();
  await expect(page.getByText('Website launch in seven easy steps')).toBeVisible();
  await page.getByRole('button', { name: 'Close website walkthrough' }).click();
  await expect(page.getByRole('heading', { name: 'From brief to business system' })).toBeVisible();
  await expect(page.getByText('FAMtastic AI Solutions Studio', { exact: true })).toBeVisible();
  await assertNoHorizontalOverflow(page);
  await page.screenshot({ path: testInfo.outputPath('portal-ai-studio-home.png'), fullPage: true });
  await page.getByRole('button', { name: 'Start my website & proofs', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'My Projects', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: '+ Start a new website', exact: true })).toBeVisible();
});

test('research-led proof selection makes the payment boundary unmistakable', async ({ page }, testInfo) => {
  await mockProofReviewPortal(page);
  await page.goto('/portal');
  await expect(page.getByRole('heading', { name: 'My Projects', exact: true })).toBeVisible();
  await expect(page.getByRole('status')).toContainText('Your 3 website concepts are ready below.');
  await expect(page.getByRole('heading', { name: 'Why we designed these three directions' })).toBeVisible();
  await page.getByText('See the research and growth opportunities').click();
  await expect(page.getByText('Turn portfolio views into first-party booking requests.')).toBeVisible();
  await expect(page.getByText('A visual portfolio path.')).toBeVisible();
  await page.getByRole('button', { name: 'Choose Wild', exact: true }).click();

  const selected = page.locator('[data-proof-direction="b"]');
  await expect(selected).toHaveClass(/selected/);
  await expect(selected.getByText('✓ Selected', { exact: true })).toBeVisible();
  await expect(page.locator('[data-proof-direction="a"]')).toHaveClass(/dimmed/);
  await expect(page.getByRole('heading', { name: 'Wild is your selected direction' })).toBeVisible();
  await expect(page.locator('.portal-proof-next')).toBeFocused();
  await expect(page.getByRole('heading', { name: 'Complete payment to start the build' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Continue to secure payment →', exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('mobile-selection-to-payment.png'), fullPage: true });

  await page.getByRole('button', { name: 'Request an edit round', exact: true }).click();
  const notes = page.getByLabel('Your change notes');
  await expect(notes).toBeVisible();
  await expect(notes).toBeFocused();
  await notes.fill('Keep the layout, but use royal blue and warmer photography.');
  await page.getByRole('button', { name: 'Send changes to Fritz', exact: true }).click();
  await expect(page.getByText('Changes requested. Fritz has your notes.')).toBeVisible();
  await expect(page.getByText('Changes requested ✓', { exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'We received your changes for Wild' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('proof-selection-and-revision.png'), fullPage: true });
});

test('multiple projects show whose turn it is and archive without deleting', async ({ page }, testInfo) => {
  let requests = [
    {
      public_id: 'ready-project', project_name: 'Tighten Up Your Locs website', status: 'submitted',
      proof_review_status: 'notified', customer_archived: false, changed: 1787021000,
      selected_proof_direction: '', direct_checkout_available: false, intake: {}, assets: [],
      proof_handoff: { state: 'choose_direction', label: 'Choose one direction', detail: 'Three directions are ready.' },
      proofs: { variants: [
        { direction_id: 'a', direction_name: 'Safe', preview_url: 'https://example.test/a' },
        { direction_id: 'b', direction_name: 'Wild', preview_url: 'https://example.test/b' },
        { direction_id: 'c', direction_name: 'OMG', preview_url: 'https://example.test/c' },
      ], research_snapshot: { overview: 'Research summary.', direction_rationale: {}, market_signals: [], opportunities: [], sources: ['Interview'], researched_at: '2026-09-05' } },
    },
    {
      public_id: 'draft-project', project_name: 'Holiday campaign page', status: 'draft',
      proof_review_status: 'not_started', customer_archived: false, changed: 1787020000,
      direct_checkout_available: false, intake: {}, assets: [], proofs: { variants: [] },
      proof_handoff: { state: 'draft', label: 'Finish and submit your brief', detail: 'Finish the brief before proof work begins.' },
    },
    {
      public_id: 'old-project', project_name: 'Old menu idea', status: 'draft',
      proof_review_status: 'not_started', customer_archived: true, changed: 1787010000,
      direct_checkout_available: false, intake: {}, assets: [], proofs: { variants: [] },
      proof_handoff: { state: 'draft', label: 'Finish and submit your brief', detail: 'Saved.' },
    },
  ];
  const workspace = () => emptyWorkspace(requests);
  await page.route('**/api/customer/session', (route) => route.fulfill({ json: { customer: { display_name: 'Shay', email: 'shay@example.test' } } }));
  await page.route('**/api/customer/catalog', (route) => route.fulfill({ json: { products: [] } }));
  await page.route('**/session/token', (route) => route.fulfill({ body: 'mock-csrf-token' }));
  await page.route('**/api/customer/website-requests/*/archive', async (route) => {
    const id = route.request().url().split('/').at(-2);
    const { action } = route.request().postDataJSON();
    requests = requests.map((request) => request.public_id === id ? { ...request, customer_archived: action === 'archive' } : request);
    await route.fulfill({ json: { ok: true, website_request: requests.find((request) => request.public_id === id) } });
  });
  await page.route('**/api/customer/workspace*', (route) => route.fulfill({ json: workspace() }));

  await page.goto('/portal?section=projects');
  await expect(page.getByRole('heading', { name: 'Choose one website direction' })).toBeVisible();
  await expect(page.getByRole('tab', { name: /Holiday campaign page.*Your turn.*Finish your website brief/ })).toBeVisible();
  await page.getByRole('tab', { name: /Holiday campaign page/ }).click();
  await expect(page.getByRole('heading', { name: 'Finish your website brief' })).toBeVisible();
  await page.getByRole('button', { name: 'Move project to Archive' }).click();
  await expect(page.getByRole('alertdialog')).toContainText('Nothing is deleted or cancelled.');
  await page.getByRole('button', { name: 'Yes, move to Archive' }).click();
  await expect(page.getByRole('status')).toContainText('Nothing was deleted or cancelled.');
  await page.getByRole('button', { name: 'Archive (2)' }).click();
  await expect(page.getByText('Holiday campaign page')).toBeVisible();
  await expect(page.getByText('Saved—not deleted', { exact: false }).first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('mobile-multiple-projects-and-archive.png'), fullPage: true });
  await page.getByRole('button', { name: 'Restore project' }).first().click();
  await expect(page.getByRole('status')).toContainText('Project restored to your active list.');
});

test('proof email deep link opens the exact request and identifies a wrong signed-in account', async ({ page }) => {
  await mockProofReviewPortal(page);
  await page.goto('/portal/?section=projects&request=request-proof-review');
  await expect(page.getByRole('heading', { name: 'My Projects', exact: true })).toBeVisible();
  await expect(page.locator('#website-request-request-proof-review')).toHaveClass(/portal-request-target/);
  await expect(page.getByRole('status')).toContainText('Your 3 website concepts are ready below. Compare each direction and choose when you are ready.');
  await expect(page.getByRole('button', { name: 'Choose Safe', exact: true })).toBeVisible();

  await page.unrouteAll({ behavior: 'wait' });
  await mockNewCustomerPortal(page);
  await page.goto('/portal/?section=projects&request=request-proof-review');
  await expect(page.getByRole('alert')).toContainText('This proof link is not connected to the account signed in as fitzgerald.medine@gmail.com.');
  await expect(page.getByRole('alert')).toContainText('Sign out, then sign in with the email address that received the proof-ready message.');
});

test('proof owner can create, replace, and revoke a view-only share link', async ({ page }) => {
  await mockProofReviewPortal(page);
  await page.goto('/portal/?section=projects&request=request-proof-review');
  await page.getByText('Share concepts with someone else', { exact: false }).click();
  const sharing = page.getByRole('switch', { name: 'Sharing off' });
  await expect(sharing).toHaveAttribute('aria-checked', 'false');
  await sharing.click();
  await expect(page.getByRole('switch', { name: 'Sharing on' })).toHaveAttribute('aria-checked', 'true');
  await expect(page.getByLabel('Unlisted link')).toHaveValue(/first-signature$/);
  await page.getByRole('button', { name: 'Create a new link' }).click();
  await expect(page.getByLabel('Unlisted link')).toHaveValue(/new-signature$/);
  await page.getByRole('switch', { name: 'Sharing on' }).click();
  await page.getByText('Share concepts with someone else', { exact: false }).click();
  await expect(page.getByRole('switch', { name: 'Sharing off' })).toHaveAttribute('aria-checked', 'false');
  await expect(page.getByLabel('Unlisted link')).toHaveCount(0);
});

test('unlisted proof room works without an account and exposes view links only', async ({ page }, testInfo) => {
  await page.route('**/api/proof-shares/request-proof-review/public-signature', (route) => route.fulfill({ json: { ok: true, proof_share: {
    project_name: 'Church outreach website', business_name: 'Crown & Coast Church', proof_count: 3,
    variants: [
      { direction_id: 'a', direction_name: 'Safe', preview_url: '/web/api/proof-shares/request-proof-review/public-signature/proofs/a' },
      { direction_id: 'b', direction_name: 'Wild', preview_url: '/web/api/proof-shares/request-proof-review/public-signature/proofs/b' },
      { direction_id: 'c', direction_name: 'OMG', preview_url: '/web/api/proof-shares/request-proof-review/public-signature/proofs/c' },
    ],
  } } }));
  await page.goto('/proofs/share/request-proof-review/public-signature');
  await expect(page.getByRole('heading', { name: 'Crown & Coast Church' })).toBeVisible();
  await expect(page.getByRole('link', { name: /Open working concept/ })).toHaveCount(3);
  await expect(page.getByText('No account, pricing, selection, or revision access')).toBeVisible();
  await expect(page.getByRole('button')).toHaveCount(0);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow, noarchive');
  await assertNoHorizontalOverflow(page);
  await page.screenshot({ path: testInfo.outputPath('unlisted-proof-room.png'), fullPage: true });
});

test('public preview room is a private decision aid with one workspace handoff', async ({ page }) => {
  await page.route('**/api/public-preview/preview-pit/public-signature', (route) => route.fulfill({ json: { ok: true, preview_delivery: {
    business_name: 'Pros In Training', proof_count: 3, private_label: 'Private review concept · Not yet published.',
    registration_url: '/login?mode=register&continuation=preview-pit.public-continuation',
    variants: [
      { direction_id: 'a', direction_name: 'The Launchpad', preview_url: '/web/api/public-preview/preview-pit/public-signature/proofs/a' },
      { direction_id: 'b', direction_name: 'The Field Guide', preview_url: '/web/api/public-preview/preview-pit/public-signature/proofs/b' },
      { direction_id: 'c', direction_name: 'Future Pros Studio', preview_url: '/web/api/public-preview/preview-pit/public-signature/proofs/c' },
    ],
  } } }));
  await page.goto('/proofs/preview/preview-pit/public-signature');
  await expect(page.getByRole('heading', { name: '3 exploratory directions for Pros In Training' })).toBeVisible();
  await expect(page.getByText('Private review concept · Not yet published.')).toBeVisible();
  await expect(page.getByRole('link', { name: /Open working concept/ })).toHaveCount(3);
  await expect(page.getByText('No selection, price, checkout, or publishing happens here')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Create your free workspace' })).toHaveAttribute('href', /continuation=preview-pit\.public-continuation/);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow, noarchive');
  await assertNoHorizontalOverflow(page);
});

test('revoked or unknown proof link reveals no project data', async ({ page }) => {
  await page.route('**/api/proof-shares/request-proof-review/revoked-signature', (route) => route.fulfill({ status: 404, json: { ok: false, error: 'proof_share_not_found' } }));
  await page.goto('/proofs/share/request-proof-review/revoked-signature');
  await expect(page.getByRole('heading', { name: 'This proof link is unavailable.' })).toBeVisible();
  await expect(page.getByText('Crown & Coast Church')).toHaveCount(0);
});

test('customer account, portal, project, and purchase UI are mobile-safe', async ({ page }, testInfo) => {
  await signIn(page);
  await expect(page.getByRole('button', { name: 'Start my website & proofs', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Start my website & proofs', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'My Projects', exact: true })).toBeVisible();
  for (const section of ['Services', 'Messages', 'Projects']) {
    const menu = page.getByRole('button', { name: 'Menu', exact: true });
    if (await menu.isVisible()) await menu.click();
    await page.getByRole('button', { name: section, exact: true }).click();
    await expect(page.getByRole('heading', { name: section, exact: true })).toBeVisible();
    await assertNoHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`${section.toLowerCase().replaceAll(/[^a-z]+/g, '-')}.png`), fullPage: true });
  }
  await page.getByRole('button', { name: /Start a new website/i }).click();
  await expect(page.getByRole('heading', { name: /Tell us what you want to build/i })).toBeVisible();
  await page.getByLabel('Request name').fill('Mobile bakery website');
  await page.getByLabel('What are we building?').selectOption('online_store');
  await page.getByLabel('What should this website accomplish?').fill('Accept bakery orders for pickup.');
  await page.getByLabel('What does the business sell or provide?').fill('Cakes and pastries.');
  await assertNoHorizontalOverflow(page);
  await page.screenshot({ path: testInfo.outputPath('website-request-mobile-form.png'), fullPage: true });
  await page.goto('/buy');
  await assertNoHorizontalOverflow(page);
  await expect(page.getByRole('heading', { name: /Web Basics/i })).toBeVisible();
  await expect(page.getByText('$199.00', { exact: true })).toBeVisible();
  await expect(page.getByText(/\$9\.99\/month/i)).toBeVisible();
});

test('authenticated customer command-center sections remain mobile-safe', async ({ page }, testInfo) => {
  await signIn(page);
  for (const section of ['Home', 'Services', 'Projects', 'Messages', 'Billing', 'Account']) {
    const menu = page.getByRole('button', { name: 'Menu', exact: true });
    if (await menu.isVisible()) await menu.click();
    await page.getByRole('button', { name: section, exact: true }).click();
    await expect(page.locator('.portal-main > header h1')).toHaveText(section);
    await assertNoHorizontalOverflow(page);
  }
  await page.screenshot({ path: testInfo.outputPath('authenticated-portal-mobile.png'), fullPage: true });
});

test('sandbox Commerce order completes with Stripe test payment', async ({ page }) => {
  test.skip(process.env.FAMTASTIC_RUN_SANDBOX_PAYMENT !== '1', 'Explicit sandbox-payment gate is required.');
  await signIn(page, '/buy');
  await page.getByLabel(/Connect a domain I already own/i).check();
  await page.getByLabel(/Additional revision round/i).check();
  await page.getByLabel(/I authorize basic hosting/i).check();
  await page.getByLabel(/I accept the recorded product scope/i).check();
  await page.getByRole('button', { name: /Continue to secure payment/i }).click();
  await expect(page).toHaveURL(/\/web\/checkout\/\d+\/order_information/);
  await expect(page.getByRole('heading', { name: 'Order information' })).toBeVisible();
  await assertNoHorizontalOverflow(page);
  await expect(page.getByText('$274.00', { exact: true }).first()).toBeVisible();
  await page.getByLabel('First name').fill('FAMtastic');
  await page.getByLabel('Last name').fill('Proof');
  await page.getByLabel('Company').fill('FAMtastic Launch Proof');
  await page.getByLabel('Street address', { exact: true }).fill('1729 NW St. Lucie West Blvd #1181');
  await page.getByLabel('City').fill('Port Saint Lucie');
  await page.getByLabel('State').selectOption('FL');
  await page.getByLabel(/Zip code/i).fill('34986');
  await page.getByRole('button', { name: /Continue to review/i }).click();
  await expect(page).toHaveURL(/\/review/);
  await expect(page.getByRole('heading', { name: 'Review' })).toBeVisible();
  await assertNoHorizontalOverflow(page);
  const stripe = page.locator('iframe[title*="Secure payment input frame" i]').first().contentFrame();
  await stripe.getByLabel(/Card number/i).fill('4242424242424242');
  await stripe.getByLabel(/Expiration date/i).fill('1230');
  await stripe.getByLabel(/Security code/i).fill('123');
  const saveForFasterCheckout = stripe.getByLabel(/Save my information for faster checkout/i);
  if (await saveForFasterCheckout.isChecked().catch(() => false)) await saveForFasterCheckout.uncheck();
  await page.getByRole('button', { name: /Pay and complete purchase/i }).click();
  await expect(page).toHaveURL(/\/complete/, { timeout: 30_000 });
  await expect(page.getByRole('heading', { name: /complete|thank/i })).toBeVisible();
  await assertNoHorizontalOverflow(page);
});
