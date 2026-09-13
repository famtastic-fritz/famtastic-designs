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
    let mode = 'ready', status = 'new', updates = 0;
    await page.route('https://**', route => route.abort());
    await page.route('**/web/session/token', route => route.fulfill({ body: 'local-csrf-fixture' }));
    await page.route('**/web/api/customer/**', async route => {
      const path = new URL(route.request().url()).pathname;
      let payload = {};
      if (path.endsWith('/session')) payload = { customer: { public_id: 'owner-fixture', display_name: 'Local Owner', email: 'owner@example.test', verified: true } };
      else if (path.endsWith('/workspace')) payload = { organization: { public_id: 'org-fixture', name: 'Local Test Business' }, booking_sites: [{ site_key: 'site-fixture', business_name: 'Local Test Business' }], website_requests: [], threads: [] };
      else if (path.endsWith('/catalog')) payload = { products: [] };
      else if (path.includes('/booking-requests')) {
        if (mode === 'error') return route.fulfill({ status: 403, contentType: 'application/json', body: '{"ok":false}' });
        if (route.request().method() === 'PATCH') {
          assert.equal(route.request().headers()['x-csrf-token'], 'local-csrf-fixture');
          status = route.request().postDataJSON().status; updates++;
          payload = { ok: true, status };
        } else payload = { ok: true, site_key: 'site-fixture', requests: mode === 'empty' ? [] : [{ id: 1, customer_name: 'Local Visitor Fixture', email: 'visitor@example.test', phone: '+1 555 010 0101', service_key: 'consultation', requested_window: 'Friday afternoon', message: '<script>not executable</script> ' + 'long'.repeat(70), status, created: 1700000000 }] };
      }
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify(payload) });
    });
    await page.goto(url + 'portal/?section=booking');
    await page.getByRole('heading', { name: 'Local Visitor Fixture' }).waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    assert.match(await page.getByRole('link', { name: 'Open email app' }).getAttribute('href'), /^mailto:/);
    assert.equal(updates, 0);
    await page.getByLabel('Request status').selectOption('reviewing');
    await page.getByText('Status saved. No reply was sent and no appointment was created.').waitFor();
    assert.equal(updates, 1);
    await page.screenshot({ path: resolve(shots, 'owner-' + width + '.png'), fullPage: true });
    mode = 'empty'; await page.getByRole('button', { name: 'Refresh requests' }).click();
    await page.getByText('No requests have been received for this website.').waitFor();
    mode = 'error'; await page.getByRole('button', { name: 'Refresh requests' }).click();
    await page.getByRole('alert').filter({ hasText: 'Requests could not be loaded' }).waitFor();
    assert.equal(await page.getByRole('heading', { name: 'Local Visitor Fixture' }).count(), 0);
    checks.push({ width, states: ['real-rendered mocked inbox', 'no overflow', 'manual contact not sent', 'CSRF status update', 'empty', 'access error hides data'] });
    await context.close();
  }
  console.log(JSON.stringify({ classification: 'LOCAL MOCKED API ONLY; no provider or customer actions', checks, screenshots: shots }, null, 2));
} finally {
  await browser.close(); await new Promise(done => server.httpServer.close(done));
}
