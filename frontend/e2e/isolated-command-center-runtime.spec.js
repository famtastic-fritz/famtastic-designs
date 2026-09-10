import { expect, test } from '@playwright/test';

const ADMIN = process.env.FAMTASTIC_RUNTIME_BACKEND_URL || 'http://127.0.0.1:18081';

async function noHorizontalOverflow(page) {
  const size = await page.evaluate(() => {
    const viewport = document.documentElement.clientWidth;
    const offenders = [...document.querySelectorAll('*')]
      .map(element => {
        const rect = element.getBoundingClientRect();
        return {
          selector: `${element.tagName.toLowerCase()}${element.id ? `#${element.id}` : ''}${[...element.classList].slice(0, 3).map(name => `.${name}`).join('')}`,
          left: Math.round(rect.left),
          right: Math.round(rect.right),
          width: Math.round(rect.width),
          scrollWidth: element.scrollWidth,
          containedBy: element.closest('.famtastic-table-scroll, .famtastic-ops__table-scroll, .view-content, .portal-proof-grid')?.className || null,
        };
      })
      .filter(item => item.right > viewport + 1)
      .sort((first, second) => second.right - first.right)
      .slice(0, 12);
    return {
      viewport,
      content: document.documentElement.scrollWidth,
      layout: [document.documentElement, document.body, document.querySelector('.famtastic-admin-shell__frame'), document.querySelector('.famtastic-admin-shell__body'), document.querySelector('.region-content'), document.querySelector('.famtastic-ops__table-scroll'), document.querySelector('.famtastic-table-scroll')]
        .filter(Boolean)
        .map(element => {
          const rect = element.getBoundingClientRect();
          const style = getComputedStyle(element);
          return {
            selector: element.tagName.toLowerCase() + (element.className ? `.${String(element.className).trim().split(/\s+/).join('.')}` : ''),
            left: Math.round(rect.left),
            right: Math.round(rect.right),
            width: Math.round(rect.width),
            marginLeft: style.marginLeft,
            marginRight: style.marginRight,
            paddingLeft: style.paddingLeft,
            paddingRight: style.paddingRight,
            position: style.position,
          };
        }),
      offenders,
    };
  });
  const scrollX = await page.evaluate(async () => {
    window.scrollTo(document.documentElement.scrollWidth, 0);
    await new Promise(resolve => requestAnimationFrame(resolve));
    const value = window.scrollX;
    window.scrollTo(0, 0);
    return value;
  });
  const uncontained = size.offenders.filter(item => !item.containedBy);
  expect(scrollX, `page can scroll horizontally: ${JSON.stringify(size)}`).toBe(0);
  expect(uncontained, `uncontained overflow: ${JSON.stringify(size)}`).toEqual([]);
}

function luminance([red, green, blue]) {
  const linear = [red, green, blue].map(value => {
    const channel = value / 255;
    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
}

function contrastRatio(foreground, background) {
  const parse = color => (color.match(/\d+(?:\.\d+)?/g) || []).slice(0, 3).map(Number);
  const first = luminance(parse(foreground));
  const second = luminance(parse(background));
  return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

async function signIn(page, username = 'admin', password = 'admin') {
  await page.context().clearCookies();
  await page.goto(`${ADMIN}/user/login`);
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Log in' }).click();
  await expect(page).toHaveURL(/user\/\d+/);
}

test('fresh Drupal runtime renders branded login/reset and protects staff routes', async ({ page }, testInfo) => {
  for (const width of [390, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`${ADMIN}/user/login`);
    await expect(page.getByLabel('Username')).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(page.locator('img[src*="/themes/custom/famtastic_admin/logo.svg"]')).toHaveCount(1);
    await expect(page.locator('body')).toHaveClass(/famtastic-auth-page/);
    await expect(page.getByText('Powered by Drupal')).toHaveCount(0);
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`drupal-login-${width}.png`), fullPage: true });
  }

  await page.goto(`${ADMIN}/user/password`);
  await expect(page.getByLabel('Username or email address')).toBeVisible();
  await expect(page.locator('img[src*="/themes/custom/famtastic_admin/logo.svg"]')).toHaveCount(1);
  await noHorizontalOverflow(page);

  const protectedResponse = await page.goto(`${ADMIN}/admin/famtastic`);
  expect(protectedResponse?.status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Access denied' })).toBeVisible();
});

test('native forms, tables, status states, focus, and custom operations render in the FAMtastic theme', async ({ page }, testInfo) => {
  await signIn(page);
  for (const width of [390, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`${ADMIN}/admin/famtastic`);
    await expect(page.getByRole('heading', { name: 'Operations Home' })).toBeVisible();
    await expect(page.locator('body')).toHaveClass(/famtastic-admin-shell/);
    await expect(page.locator('a[href="#"]')).toHaveCount(0);
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`operations-${width}.png`), fullPage: true });

    if (width === 390) {
      const recordLinks = await page.locator('.famtastic-admin-shell__body a[href*="/admin/famtastic/metric/"]').evaluateAll(links =>
        [...new Set(links.map(link => link.href))],
      );
      expect(recordLinks).toHaveLength(14);
      for (const href of recordLinks) {
        const response = await page.goto(href);
        expect(response?.status(), `record link failed: ${href}`).toBeLessThan(400);
        await expect(page.locator('body')).toHaveClass(/famtastic-admin-shell/);
        await expect(page.locator('a[href="#"]')).toHaveCount(0);
        await noHorizontalOverflow(page);
      }

      const attentionResponse = await page.goto(`${ADMIN}/admin/famtastic/attention`);
      expect(attentionResponse?.status()).toBeLessThan(400);
      await expect(page.getByRole('heading', { name: 'Attention Queue' })).toBeVisible();
      await noHorizontalOverflow(page);
    }

    await page.goto(`${ADMIN}/admin/content`);
    await expect(page.locator('table')).toBeVisible();
    await expect(page.locator('th')).not.toHaveCount(0);
    await noHorizontalOverflow(page);

    await page.goto(`${ADMIN}/node/add/article`);
    const title = page.getByRole('textbox', { name: /^Title \*/ });
    await expect(title).toBeVisible();
    await expect(page.locator('body')).toHaveClass(/famtastic-admin-shell/);
    await title.focus();
    await expect(title).toBeFocused();
    await expect(title).toHaveCSS('min-height', /4[4-9]px|[5-9]\dpx/);
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`core-form-${width}.png`), fullPage: true });

    await page.goto(`${ADMIN}/admin/famtastic/settings`);
    await expect(page.getByRole('heading', { name: 'FAMtastic notification settings' })).toBeVisible();
    await expect(page.locator('form')).toBeVisible();
    await noHorizontalOverflow(page);
  }

  await page.context().clearCookies();
  await page.goto(`${ADMIN}/user/login`);
  await page.getByLabel('Username').fill('not-a-user');
  await page.getByLabel('Password').fill('wrong-password');
  await page.getByRole('button', { name: 'Log in' }).click();
  await expect(page.getByRole('alert')).toBeVisible();
});

test('customer portal mocked runtime keeps reachable actions, focus, touch sizing, and 390/768/1280 containment', async ({ page }, testInfo) => {
  const workspace = {
    organization: { public_id: 'runtime-org', name: 'Runtime Studio', role: 'owner' },
    organizations: [], projects: [], orders: [], entitlements: [], website_requests: [],
    threads: [], activity: [], members: [], referrals: [], articles: [], faqs: [], offers: [],
    analytics: { entitled: false }, preferences: { project_email: true, support_email: true, billing_email: true, product_education: true, deals_promotions: true, analytics_digest: 'monthly', topics: [] }, topics: {},
  };
  await page.route('**/api/customer/session', route => route.fulfill({ json: { customer: { display_name: 'Runtime Operator', email: 'runtime@example.test' } } }));
  await page.route('**/api/customer/catalog', route => route.fulfill({ json: { products: [] } }));
  await page.route('**/api/customer/workspace*', route => route.fulfill({ json: workspace }));

  const sections = {
    projects: 'My Projects',
    services: 'Services & Add-ons',
    files: 'Files & Assets',
    results: 'Growth & Analytics',
    messages: 'Messages',
    shay: 'Shay AI Advisor',
    support: 'Support',
    faq: 'Knowledge & FAQs',
    grow: 'Growth Ideas',
    referrals: 'Referrals',
    billing: 'Billing & Orders',
    settings: 'Settings & Alerts',
    account: 'Profile & Team',
  };

  for (const width of [390, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/portal');
    await expect(page.getByRole('button', { name: 'Open Projects', exact: true })).toBeVisible();
    const target = page.getByRole('button', { name: 'Open Projects', exact: true });
    const box = await target.boundingBox();
    expect(box?.height).toBeGreaterThanOrEqual(44);
    const colors = await target.evaluate(element => {
      const style = getComputedStyle(element);
      return { foreground: style.color, background: style.backgroundColor };
    });
    expect(contrastRatio(colors.foreground, colors.background)).toBeGreaterThanOrEqual(4.5);
    await target.focus();
    await expect(target).toBeFocused();
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`portal-${width}.png`), fullPage: true });

    for (const [section, label] of Object.entries(sections)) {
      await page.goto(`/portal?section=${section}`);
      await expect(page.getByRole('heading', { name: label, exact: true, level: 1 })).toBeVisible();
      await expect(page.locator('a[href="#"]')).toHaveCount(0);
      if (section === 'services') {
        await expect(page.locator('.portal-service-hub a[href="/intake"]')).toHaveCount(0);
        await page.getByRole('button', { name: 'Open project briefs →', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'My Projects', exact: true, level: 1 })).toBeVisible();
        expect(new URL(page.url()).pathname).toBe('/portal');
      }
      await noHorizontalOverflow(page);
    }

    await page.goto('/portal');
    await page.getByRole('button', { name: 'Open Projects', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'My Projects', exact: true, level: 1 })).toBeVisible();
  }
});

test('proof cards use a readable mobile viewport instead of a shrunken desktop strip', async ({ page }, testInfo) => {
  const proofUrl = 'http://127.0.0.1:18173/proofs/readable-a';
  const workspace = {
    organization: { public_id: 'proof-org', name: 'Proof Studio', role: 'owner' },
    organizations: [], projects: [], orders: [], entitlements: [],
    website_requests: [{
      public_id: 'request-readable-proof',
      project_name: 'Readable proof project',
      status: 'submitted',
      proof_review_status: 'notified',
      customer_archived: false,
      direct_checkout_available: false,
      intake: {},
      proofs: {
        selected_variant: '',
        review_terms: { design_reset_remaining: 1, edit_rounds_remaining: 3 },
        variants: [
          { direction_id: 'a', direction_name: 'Safe', preview_url: proofUrl },
          { direction_id: 'b', direction_name: 'Wild', preview_url: `${proofUrl}-b` },
          { direction_id: 'c', direction_name: 'OMG', preview_url: `${proofUrl}-c` },
        ],
      },
    }],
    threads: [], activity: [], members: [], referrals: [], articles: [], faqs: [], offers: [],
    analytics: { entitled: false },
    preferences: { project_email: true, support_email: true, billing_email: true, product_education: true, deals_promotions: true, analytics_digest: 'monthly', topics: [] },
    topics: {},
  };
  await page.route('**/api/customer/session', route => route.fulfill({ json: { customer: { display_name: 'Proof Reviewer', email: 'proof@example.test' } } }));
  await page.route('**/api/customer/catalog', route => route.fulfill({ json: { products: [] } }));
  await page.route('**/api/customer/workspace*', route => route.fulfill({ json: workspace }));
  await page.route('**/proofs/readable-a*', route => route.fulfill({
    contentType: 'text/html',
    body: '<!doctype html><meta name="viewport" content="width=device-width"><style>body{margin:0;background:#28152d;color:white;font:24px system-ui}header{padding:28px;background:#fff;color:#111}main{min-height:650px;padding:28px;background:linear-gradient(135deg,#28152d,#7b2147)}h1{font-size:42px}</style><header>Readable concept navigation</header><main><h1>Full mobile concept</h1><p>The approved design fills this card.</p></main>',
  }));

  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/portal?section=projects&request=request-readable-proof');
  const preview = page.locator('[data-proof-direction="a"] .portal-proof-preview');
  await expect(preview).toBeVisible();
  await expect(page.frameLocator('[data-proof-direction="a"] iframe').getByRole('heading', { name: 'Full mobile concept' })).toBeVisible();
  const previewBox = await preview.boundingBox();
  const frame = preview.locator('iframe');
  const frameBox = await frame.boundingBox();
  const sourceViewportWidth = await frame.evaluate(element => element.contentDocument.documentElement.clientWidth);
  expect(previewBox?.height).toBeGreaterThanOrEqual(390);
  expect(Math.abs((previewBox?.width || 0) - (frameBox?.width || 0))).toBeLessThanOrEqual(2);
  expect(sourceViewportWidth).toBeGreaterThanOrEqual(280);
  expect(sourceViewportWidth).toBeLessThanOrEqual(390);
  await expect(frame).toHaveCSS('transform', 'none');
  await noHorizontalOverflow(page);
  await page.screenshot({ path: testInfo.outputPath('portal-proof-readable-mobile.png'), fullPage: true });
});
