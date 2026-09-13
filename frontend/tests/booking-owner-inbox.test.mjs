import { preview } from 'vite';
import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { resolve } from 'node:path';

const frontend = resolve(import.meta.dirname, '..');
const server = await preview({ root: frontend, preview: { host: '127.0.0.1', port: 0 } });
const url = server.resolvedUrls.local[0];
const shots = await mkdtemp(resolve(tmpdir(), 'booking-owner-qa-'));
const browser = await chromium.launch();
const checks = [];
try {
  for (const width of [390, 1280]) {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();
    let appointment = null;
    const commands = [];
    await page.route('https://**', route => route.abort());
    await page.route('**/web/session/token', route => route.fulfill({ body: 'local-csrf-fixture' }));
    await page.route('**/web/api/customer/**', async route => {
      const path = new URL(route.request().url()).pathname;
      const method = route.request().method();
      let payload = {};
      if (path.endsWith('/session')) payload = { customer: { public_id: 'owner-fixture', display_name: 'Shay', email: 'owner@example.test', verified: true } };
      else if (path.endsWith('/workspace')) payload = { organization: { public_id: 'org-fixture', name: 'Tighten Up Your Locs' }, booking_sites: [{ site_key: 'tighten-up-your-locs', business_name: 'Tighten Up Your Locs' }], website_requests: [], threads: [] };
      else if (path.endsWith('/catalog')) payload = { products: [] };
      else if (path.endsWith('/booking-requests')) payload = { ok: true, site_key: 'tighten-up-your-locs', requests: [{ id: 1, customer_name: 'Local Visitor Fixture', email: 'visitor@example.test', phone: '+1 555 010 0101', service_key: 'loc-care', requested_window: 'Friday afternoon', message: 'Starter loc care request', status: appointment ? 'responded' : 'new' }] };
      else if (path.endsWith('/appointments')) {
        if (method === 'POST') {
          assert.equal(route.request().headers()['x-csrf-token'], 'local-csrf-fixture');
          const command = route.request().postDataJSON();
          commands.push(command);
          appointment = { id: 8, public_id: 'appointment-fixture', request_id: 1, service_key: 'loc-care', starts_at: command.starts_at || 1700100000, ends_at: command.ends_at || 1700103600, proposed_starts_at: 0, proposed_ends_at: 0, status: command.action === 'cancel' ? 'cancelled' : command.action === 'complete' ? 'completed' : 'confirmed', revision: 1, timezone: 'America/New_York', customer: { name: 'Local Visitor Fixture', email: 'visitor@example.test', phone: '' } };
          payload = { ok: true, appointment };
        } else payload = { ok: true, site_key: 'tighten-up-your-locs', appointments: appointment ? [appointment] : [] };
      } else if (path.endsWith('/availability')) payload = { ok: true, site_key: 'tighten-up-your-locs', windows: [] };
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify(payload) });
    });

    await page.goto(url + 'portal/?section=booking');
    await page.getByRole('heading', { name: 'Owner Desk', level: 1 }).waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.getByRole('tab', { name: 'Requests' }).click();
    await page.getByRole('heading', { name: 'Local Visitor Fixture' }).waitFor();
    await page.getByText('Confirm or propose a time').click();
    const form = page.locator('form').filter({ hasText: 'Confirm this request' });
    await form.getByLabel('Start').fill('2026-09-14T11:00');
    await form.getByLabel('End').fill('2026-09-14T12:00');
    await form.getByRole('button', { name: 'Confirm appointment' }).click();
    await page.getByText('Appointment confirmed and saved.').waitFor();
    assert.equal(commands[0].action, 'confirm');
    await page.getByRole('tab', { name: 'Today' }).click();
    await page.getByText('Confirmed', { exact: true }).waitFor();
    await page.screenshot({ path: resolve(shots, 'owner-desk-' + width + '.png'), fullPage: true });
    checks.push({ width, states: ['owner-bound site', 'saved appointment command', 'CSRF', 'no overflow', 'today appointment'] });
    await context.close();
  }
  console.log(JSON.stringify({ classification: 'LOCAL MOCKED API ONLY; no provider or customer actions', checks, screenshots: shots }, null, 2));
} finally {
  await browser.close();
  await new Promise(done => server.httpServer.close(done));
}
