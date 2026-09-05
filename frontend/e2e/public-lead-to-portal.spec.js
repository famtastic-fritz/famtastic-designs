import { expect, test } from '@playwright/test';

const registrationUrl = '/login?mode=register&continuation=opaque-continuation&redirect=%2Fportal%3Fstart%3Dwebsite';

async function expectNoHorizontalOverflow(page) {
  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    content: document.documentElement.scrollWidth,
  }));
  expect(dimensions.content, `horizontal overflow: ${JSON.stringify(dimensions)}`).toBeLessThanOrEqual(dimensions.viewport + 1);
}

async function finishWebBasicsResearch(page) {
  await page.goto('/start');
  await page.getByRole('button', { name: /Starter Mobile Business Foundation.*\$199 starting point/ }).first().click();
  await page.getByRole('button', { name: 'I have domain & logo ready' }).click();
  await page.getByRole('button', { name: 'Solo Service Business' }).click();
  await page.getByRole('button', { name: /Planning ahead/ }).click();
  await page.getByPlaceholder('Enter your work email address...').fill('lead@example.test');
  await page.getByRole('button', { name: 'Send response' }).click();
}

test('Solution Finder shows success only after the server returns a durable request', async ({ page }) => {
  let captured;
  await page.route('**/api/public/quote', async (route) => {
    captured = route.request().postDataJSON();
    await route.fulfill({ json: {
      ok: true,
      status: 'received',
      request_id: 501,
      registration_url: registrationUrl,
      message: 'Your request is saved. Create a free account to continue the detailed brief.',
    } });
  });

  await finishWebBasicsResearch(page);

  await expect(page.getByText('Server-confirmed request #501.')).toBeVisible();
  await expect(page.getByText('Request #501 is saved.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Create a free account to continue' })).toBeVisible();
  expect(captured.source).toBe('solution-finder');
  expect(captured.answers.email).toBe('lead@example.test');
  expect(captured).not.toHaveProperty('estimate');
  await expect(page.getByText(/not a quote, reserved price, or purchase approval/i)).toBeVisible();
  await expectNoHorizontalOverflow(page);
});

test('Solution Finder leaves the answers in place and exposes retry after a failed save', async ({ page }) => {
  let attempts = 0;
  await page.route('**/api/public/quote', async (route) => {
    attempts += 1;
    if (attempts === 1) {
      await route.fulfill({ status: 503, json: { ok: false, message: 'The intake service is temporarily unavailable.' } });
      return;
    }
    await route.fulfill({ json: {
      ok: true,
      status: 'received',
      request_id: 502,
      registration_url: registrationUrl,
      message: 'Your request is saved.',
    } });
  });

  await finishWebBasicsResearch(page);

  await expect(page.getByRole('alert')).toContainText('temporarily unavailable');
  await expect(page.getByText('Nothing has been submitted yet. Your answers are still here so you can try again.')).toBeVisible();
  await page.getByRole('button', { name: 'Try saving again' }).click();
  await expect(page.getByText('Server-confirmed request #502.')).toBeVisible();
  expect(attempts).toBe(2);
});
