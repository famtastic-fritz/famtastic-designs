import { expect, test } from '@playwright/test';

// Every API call is intercepted; these tests never send mail or write customer records.
test.skip(!/^http:\/\/127\.0\.0\.1:\d+$/.test(process.env.FAMTASTIC_E2E_BASE_URL || ''), 'Inbox interaction tests require the local frontend.');

async function setup(page, { staff = true, failReply = false, failInbox = false, signedIn = true, customerRecord = false } = {}) {
  const state = { reads: [], replies: [], protectedRequests: [], inboxCalls: 0, logins: [] };
  let authenticated = signedIn;
  const context = { request_public_id: 'owned-request', project_name: 'North Harbor Website', proof_status: 'not_started', admin_request_url: '/web/admin/famtastic/website-requests/42/proofs' };
  const thread = { public_id: 'conversation-one', subject: 'Website inquiry', source: 'contact_form', customer_name: 'North Harbor', customer_email: 'owner@example.test', status: 'open', kind: 'support', unread_count: 1, needs_reply: true, last_message_preview: 'Can I see the website directions?', last_author_type: staff ? 'customer' : 'staff', last_message_at: 1789381200, last_message_id: 101, context };
  const history = [{ id: 101, author_type: staff ? 'customer' : 'staff', body: 'Can I see the website directions?', created: 1789381200, delivery_status: staff ? 'received' : 'sent' }];
  const waiting = { ...thread, public_id: 'conversation-waiting', subject: 'Launch schedule', customer_name: 'Oak Studio', unread_count: 0, needs_reply: false, last_message_id: 90, last_author_type: staff ? 'staff' : 'customer', last_message_preview: 'Your answer is saved.', context: {} };
  const workspace = { organization: { public_id: 'owned-org', name: 'North Harbor' }, orders: [], projects: [], entitlements: [], website_requests: [{ public_id: 'owned-request', project_name: 'North Harbor Website', proof_handoff: { state: 'needs_attention', label: 'Proof handoff needs FAMtastic attention' } }], threads: [], activity: [], members: [], faqs: [], offers: [], preferences: {}, topics: [], articles: [], referrals: [] };
  const session = staff
    ? { ok: true, customer: customerRecord ? { display_name: 'Staff member', email: 'staff@example.test', verified: true } : null, can_manage_messages: true, staff: { display_name: 'Staff member', email: 'staff@example.test', can_access_command_center: true, command_center_url: '/web/admin/famtastic' }, organizations: customerRecord ? [{ public_id: 'owned-org' }] : [] }
    : { ok: true, customer: { display_name: 'North Harbor', email: 'owner@example.test', verified: true }, organizations: [] };
  await page.route('**/api/customer/**', async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname.replace(/^\/web/, '');
    if (path === '/api/customer/session') return authenticated ? route.fulfill({ json: session }) : route.fulfill({ status: 401, json: { message: 'Sign in to continue.' } });
    if (path === '/api/customer/login') {
      state.logins.push(route.request().postDataJSON());
      authenticated = true;
      return route.fulfill({ json: session });
    }
    if (path === '/api/customer/workspace' || path === '/api/customer/catalog') {
      state.protectedRequests.push(path);
      return route.fulfill({ json: path.endsWith('workspace') ? workspace : { products: [] } });
    }
    if (path === '/api/customer/messages') {
      state.inboxCalls += 1;
      if (failInbox && state.inboxCalls === 1) return route.fulfill({ status: 503, json: { message: 'The inbox is temporarily unavailable.' } });
      return route.fulfill({ json: { ok: true, is_staff: staff, can_reply: true, unread_count: thread.unread_count, needs_reply_count: Number(thread.needs_reply), threads: [thread, ...(staff ? [waiting] : [])], ...(staff ? { admin_inbox_url: '/web/admin/famtastic/messages', admin_orders_url: '/web/admin/commerce/orders' } : {}) } });
    }
    if (path === '/api/customer/messages/conversation-one/read') {
      state.reads.push(route.request().postDataJSON());
      thread.unread_count = 0;
      return route.fulfill({ json: { ok: true } });
    }
    if (path === '/api/customer/messages/conversation-one') {
      if (route.request().method() === 'POST') {
        const body = route.request().postDataJSON();
        state.replies.push(body);
        if (failReply && state.replies.length === 1) return route.fulfill({ status: 503, json: { message: 'The reply could not be confirmed. Please retry.' } });
        thread.needs_reply = false;
        thread.last_author_type = staff ? 'staff' : 'customer';
        thread.last_message_id = 102;
        history.push({ id: 102, author_type: staff ? 'staff' : 'customer', body: body.body, created: 1789381260, delivery_status: staff ? 'queued' : 'portal_only' });
        return route.fulfill({ json: { ok: true, message_id: 102, delivery_status: staff ? 'queued' : 'portal_only' } });
      }
      return route.fulfill({ json: { ok: true, is_staff: staff, thread, messages: history } });
    }
    return route.fulfill({ status: 404, json: { message: 'No fixture for this endpoint.' } });
  });
  await page.route('**/session/token', (route) => route.fulfill({ body: 'local-csrf-fixture' }));
  return state;
}

async function expectFits(page) {
  const result = await page.evaluate(() => ({ width: document.documentElement.clientWidth, content: document.documentElement.scrollWidth }));
  expect(result.content).toBeLessThanOrEqual(result.width + 1);
  const shortButtons = await page.locator('.portal-inbox button:visible').evaluateAll((buttons) => buttons.filter((button) => button.getBoundingClientRect().height < 43).map((button) => button.textContent));
  expect(shortButtons).toEqual([]);
}

test('staff inbox shows contact context, saves bounded read receipts, and preserves retry identity after a failed reply', async ({ page }) => {
  const state = await setup(page, { failReply: true });
  await page.goto('/portal?tab=messages');
  await expect(page.getByText('All client conversations. Know who needs your reply.')).toBeVisible();
  expect(state.protectedRequests).toEqual([]);
  await page.locator('.portal-thread-list button').filter({ hasText: 'North Harbor' }).click();
  await expect(page.getByRole('list', { name: 'Conversation history' })).toContainText('Can I see the website directions?');
  await expect(page.getByRole('link', { name: 'Open project / proofs' })).toHaveAttribute('href', '/web/admin/famtastic/website-requests/42/proofs');
  await expect.poll(() => state.reads).toEqual([{ last_message_id: 101 }]);
  await expectFits(page);
  await page.getByLabel('Reply to client').fill('The proof work is in progress. I will update this conversation.');
  await page.getByRole('button', { name: 'Send reply', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('could not be confirmed');
  await expect(page.getByLabel('Reply to client')).toHaveValue('The proof work is in progress. I will update this conversation.');
  await page.getByRole('button', { name: 'Send reply', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Reply saved. Email queued.');
  expect(state.replies).toHaveLength(2);
  expect(state.replies[0].client_message_id).toBe(state.replies[1].client_message_id);
  await expect(page.getByLabel('Reply to client')).toHaveValue('');
  await expect(page.getByText('Waiting for customer', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Email delivery confirmed')).toHaveCount(0);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: `.artifacts/portal-inbox-staff-${test.info().project.name}.png`, fullPage: true });
});

test('customer inbox remains scoped, explains whose turn it is, and shows saved proof progress in Billing', async ({ page }) => {
  const state = await setup(page, { staff: false });
  await page.goto('/portal?tab=messages');
  await page.locator('.portal-thread-list button').click();
  await expect(page.getByRole('link', { name: /^Open project/ })).toHaveAttribute('href', '/portal?tab=projects&request=owned-request');
  await expect(page.getByRole('link', { name: 'Open in admin' })).toHaveCount(0);
  await expect(page.getByText('Oak Studio')).toHaveCount(0);
  await expect(page.getByText('Your turn to reply', { exact: true }).last()).toBeVisible();
  await page.getByLabel('Reply to FAMtastic').fill('Thank you. Please continue with the directions.');
  await page.getByRole('button', { name: 'Send reply', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Saved in this conversation');
  await expect(page.getByText('Waiting for FAMtastic', { exact: true }).last()).toBeVisible();
  expect(state.replies).toHaveLength(1);
  await expectFits(page);
  await page.goto('/portal?tab=billing');
  await expect(page.getByRole('heading', { name: 'Website requests & proofs' })).toBeVisible();
  await expect(page.getByText('Proof handoff needs FAMtastic attention')).toBeVisible();
  await expect(page.getByText('Your website request is saved above.')).toBeVisible();
  await expectFits(page);
});

test('an inbox failure stays visible and can recover without claiming an empty inbox', async ({ page }) => {
  await setup(page, { failInbox: true });
  await page.goto('/portal?tab=messages');
  await expect(page.getByRole('alert')).toContainText('temporarily unavailable');
  await expect(page.getByText('No conversations need your reply.')).toHaveCount(0);
  await page.getByRole('button', { name: 'Refresh', exact: true }).click();
  await expect(page.locator('.portal-thread-list')).toContainText('North Harbor');
  await expect(page.getByRole('alert')).toHaveCount(0);
  await expectFits(page);
});

test('tablet filters and search find waiting conversations without marking them read', async ({ page }) => {
  test.skip(test.info().project.name !== 'desktop-chromium', 'One dedicated tablet layout check.');
  await page.setViewportSize({ width: 768, height: 1024 });
  const state = await setup(page);
  await page.goto('/portal?tab=messages');
  await page.getByRole('button', { name: /^Waiting/ }).click();
  await expect(page.locator('.portal-thread-list')).toContainText('Oak Studio');
  await expect(page.locator('.portal-thread-list')).not.toContainText('North Harbor');
  expect(state.reads).toEqual([]);
  await page.getByRole('button', { name: /^All\s*\d/ }).click();
  await page.getByRole('searchbox', { name: 'Search conversations' }).fill('owner@example.test');
  await expect(page.locator('.portal-thread-list')).toContainText('North Harbor');
  await page.getByRole('searchbox', { name: 'Search conversations' }).fill('North Harbor Website');
  await expect(page.locator('.portal-thread-list button')).toHaveCount(1);
  await page.locator('.portal-thread-list button').click();
  await expect(page.getByLabel('Reply to client')).toBeVisible();
  await expectFits(page);
  await page.screenshot({ path: '.artifacts/portal-inbox-tablet-768.png', fullPage: true });
});

test('staff can sign in through the portal with an existing password and return directly to Messages', async ({ page }) => {
  const state = await setup(page, { signedIn: false });
  await page.goto('/portal?tab=messages');
  await expect(page).toHaveURL(/\/login\?redirect=/);
  await expect(page.getByLabel('Password', { exact: true })).not.toHaveAttribute('minlength', /.+/);
  await page.getByLabel('Email', { exact: true }).fill('staff@example.test');
  await page.getByLabel('Password', { exact: true }).fill('valid7!');
  await page.getByRole('button', { name: 'Open my portal', exact: true }).click();
  await expect(page).toHaveURL(/\/portal\?tab=messages$/);
  await expect(page.getByText('All client conversations. Know who needs your reply.')).toBeVisible();
  expect(state.logins).toEqual([{ email: 'staff@example.test', password: 'valid7!' }]);
  expect(state.protectedRequests).toEqual([]);
  if (test.info().project.name === 'mobile-chromium') await expect(page.getByRole('navigation', { name: 'Primary customer navigation' }).getByRole('button', { name: 'Client orders' })).toBeVisible();
  await expectFits(page);
});

test('staff with a customer workspace signs in to the shared inbox and keeps personal Billing separate from client orders', async ({ page }) => {
  const state = await setup(page, { signedIn: false, customerRecord: true });
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('staff@example.test');
  await page.getByLabel('Password', { exact: true }).fill('valid7!');
  await page.getByRole('button', { name: 'Open my portal', exact: true }).click();
  await expect(page).toHaveURL(/\/portal\?tab=messages$/);
  await expect(page.getByRole('link', { name: 'Staff Command Center' })).toBeVisible();
  expect(state.protectedRequests).toContain('/api/customer/workspace');
  await page.locator('.portal-thread-list button').filter({ hasText: 'North Harbor' }).click();
  await expect(page.getByLabel('Reply to client')).toBeVisible();
  await expectFits(page);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: `.artifacts/portal-inbox-staff-with-workspace-${test.info().project.name}.png`, fullPage: true });
  if (test.info().project.name === 'mobile-chromium') await page.getByRole('navigation', { name: 'Primary customer navigation' }).getByRole('button', { name: 'Billing' }).click();
  else await page.getByRole('navigation', { name: 'Customer portal' }).getByRole('button', { name: 'Billing & Orders' }).click();
  await expect(page.getByRole('link', { name: 'Client orders' })).toHaveAttribute('href', '/web/admin/commerce/orders');
  await expect(page.getByRole('heading', { name: 'Website requests & proofs' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'No purchases yet' })).toBeVisible();
  await expectFits(page);
});
