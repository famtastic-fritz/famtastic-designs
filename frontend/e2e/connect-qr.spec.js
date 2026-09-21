import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

// Optional installed Chrome reuse on local machines; CI can use Playwright Chromium.
test.use({ channel: process.env.FAMTASTIC_BROWSER_CHANNEL || undefined });
const evidence = process.env.FAMTASTIC_CONNECT_EVIDENCE_DIR;
async function capture(page, name, testInfo) {
  if (!evidence) return;
  mkdirSync(resolve(evidence), { recursive: true });
  await page.screenshot({ path: resolve(evidence, `${testInfo.project.name}-${name}.png`), scale: 'css' });
}

test('the visible action precedes a bounded first-visit cue and repeat visits stay quiet', async ({ page }, testInfo) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/connect/');
  await page.getByRole('button', { name: 'Skip intro' }).click();
  const action = page.getByRole('button', { name: 'SHOW MY QR', exact: true });
  await expect(action).toBeInViewport();
  await expect(action).toBeEnabled();
  const hint = page.locator('#qrPrompt');
  await expect(hint).not.toBeVisible();
  await expect(hint).toHaveClass(/is-nudging/);
  await expect(page.locator('.qr-pointer')).toHaveCSS('animation-iteration-count', '3');
  await expect(hint).not.toHaveClass(/is-nudging/);
  await expect(hint).toBeVisible();
  await capture(page, 'card', testInfo);
  await page.reload();
  await page.getByRole('button', { name: 'Skip intro' }).click();
  await expect(hint).toBeVisible();
  await expect(hint).not.toHaveClass(/is-nudging/);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  expect(errors).toEqual([]);
});

test('scan sheet contains keyboard focus and supports Close, Escape, backdrop and browser Back', async ({ page }, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/connect/');
  const primary = page.getByRole('button', { name: 'SHOW MY QR', exact: true });
  const dialog = page.getByRole('dialog', { name: 'SCAN TO CONNECT' });
  const close = page.getByRole('button', { name: 'Close QR code' });
  await primary.click();
  await expect(dialog).toBeVisible();
  await expect(close).toBeFocused();
  await expect(dialog.getByText('Point another phone at this QR code.')).toBeVisible();
  await expect(dialog.getByText('Scan to connect with Fritz')).toBeVisible();
  const qr = page.locator('#qrDialog .qr-image');
  expect(await qr.evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
  expect((await qr.boundingBox()).width).toBeGreaterThan(300);
  await capture(page, 'scan-sheet', testInfo);
  await page.keyboard.press('Shift+Tab');
  await expect(dialog.getByRole('link', { name: 'Open this card', exact: true })).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(close).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await expect(primary).toBeFocused();
  await expect(page).not.toHaveURL(/#qr$/);
  const footer = page.getByRole('button', { name: "Show this page's QR code" });
  await footer.click();
  await close.click();
  await expect(footer).toBeFocused();
  await primary.click();
  await page.mouse.click(1, 1);
  await expect(dialog).not.toBeVisible();
  await expect(primary).toBeFocused();
  await primary.click();
  await page.goBack();
  await expect(dialog).not.toBeVisible();
  await expect(primary).toBeFocused();
});

test('narrow and short screens remain usable with reduced motion', async ({ page }, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  for (const viewport of [{ width: 320, height: 568 }, { width: 568, height: 320 }]) {
    await page.setViewportSize(viewport);
    await page.goto('/connect/');
    await expect(page.locator('#qrPrompt')).toBeVisible();
    await expect(page.locator('.qr-pointer')).toHaveCSS('animation-name', 'none');
    await page.getByRole('button', { name: 'SHOW MY QR', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Close QR code' })).toBeInViewport();
    expect(await page.locator('#qrDialog').evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await capture(page, `scan-${viewport.width}`, testInfo);
    await page.getByRole('button', { name: 'Close QR code' }).click();
  }
});

test('blocked preference storage and copy failures do not block QR sharing', async ({ page }) => {
  await page.addInitScript(() => {
    for (const name of ['localStorage', 'sessionStorage']) {
      Object.defineProperty(window, name, { get() { throw new Error('Storage blocked'); } });
    }
    Object.defineProperty(navigator, 'share', { value: undefined });
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: async () => { throw new Error('Copy denied'); } } });
  });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/connect/');
  await page.getByRole('button', { name: 'SHOW MY QR', exact: true }).click();
  await page.getByRole('button', { name: 'Share Link' }).click();
  await expect(page.locator('#qrShareStatus')).toHaveText('Copy this link: https://famtasticdesigns.com/connect');
  await page.getByRole('button', { name: 'Close QR code' }).click();
  await expect(page.getByRole('button', { name: 'SHOW MY QR', exact: true })).toBeFocused();
});

test('direct QR link opens without the intro and retains the return-to-card action', async ({ page }) => {
  await page.goto('/connect/#qr');
  await expect(page.getByRole('dialog', { name: 'SCAN TO CONNECT' })).toBeVisible();
  await expect(page.locator('#intro')).not.toBeVisible();
  await page.getByRole('link', { name: 'Open this card', exact: true }).click();
  await page.getByRole('button', { name: 'Skip intro' }).click();
  await expect(page.getByRole('button', { name: 'SHOW MY QR', exact: true })).toBeVisible();
  await expect(page.locator('#qrPrompt')).not.toHaveClass(/is-nudging/);
});

test('without JavaScript, the original destinations and a direct QR fallback remain available', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  try {
    const page = await context.newPage();
    await page.goto(`${baseURL}/connect/`);
    await expect(page.locator('#intro')).not.toBeVisible();
    await expect(page.getByRole('link', { name: 'Open the QR code', exact: true })).toHaveAttribute('href', 'qr.svg');
    await expect(page.getByRole('link', { name: /Save Fritz's contact/ })).toBeVisible();
  } finally { await context.close(); }
});
