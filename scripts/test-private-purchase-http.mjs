import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';

const sandbox = fs.realpathSync(process.env.SELECTED_DRUPAL_SANDBOX || '');
const port = Number(process.env.PRIVATE_HTTP_PORT);
if (!/\/famtastic-selected-drupal\.[A-Za-z0-9]{6}$/.test(sandbox) || fs.realpathSync(path.dirname(new URL(import.meta.url).pathname)) !== `${sandbox}/scripts`
  || !Number.isInteger(port) || port < 29500 || port >= 29800 || process.env.FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT !== 'memory') throw Error('Unsafe HTTP harness');
const base = `http://127.0.0.1:${port}`;
const fixture = JSON.parse(fs.readFileSync(`${sandbox}/private-http-fixture.json`, 'utf8'));
const evidence = process.env.SELECTED_DRUPAL_EVIDENCE;
const report = { schema: 'famtastic.private-purchase-http-proof.v1', status: 'running', source_sha: process.env.SELECTED_DRUPAL_SOURCE_SHA,
  classification: 'launch-blocked', checks: {}, requests: [], limits: ['Disposable SQLite, local PHP /web mount, no production Apache/cPanel proof.',
    'Real authenticated HTTP/forms and native records; no browser/layout proof from these requests.',
    'All provider/mail transports disabled; fake test gateway credentials never invoked. No Stripe/3DS/webhook/refund proof.'] };
const hash = file => createHash('sha256').update(fs.readFileSync(file)).digest('hex');
report.tested_source_sha256 = Object.fromEntries(['Form/PrivatePurchaseForm.php', 'Service/PrivatePurchaseService.php', 'Service/PrivatePurchaseAuthorityInterface.php', 'Service/ApprovedPrivatePurchaseAuthority.php', 'EventSubscriber/PrivateScopeCheckoutGuard.php']
  .map(file => [file, hash(`${sandbox}/backend/web/modules/custom/famtastic_pipeline/src/${file}`)]));
report.tested_source_sha256['famtastic_pipeline.services.yml'] = hash(`${sandbox}/backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.services.yml`);
report.harness_sha256 = hash(new URL(import.meta.url));
report.presentation_sha256 = Object.fromEntries([
  'modules/custom/famtastic_pipeline/css/private-purchase.css',
  'themes/custom/famtastic_customer/famtastic_customer.theme',
  'themes/custom/famtastic_customer/templates/layout/page--famtastic-private-purchase.html.twig',
  'themes/custom/famtastic_customer/famtastic-designs-logo-v1.png',
].map(file => [file, hash(`${sandbox}/backend/web/${file}`)]));
const check = (value, key) => { report.checks[key] = Boolean(value); if (!value) throw Error(`FAIL: ${key}`); console.log(`PASS: ${key}`); };
const phpFlags = ['-d', 'memory_limit=512M', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail', '-d', 'sendmail_path=/usr/bin/false'];
function state(action = 'snapshot') {
  const output = execFileSync(process.env.PRIVATE_HTTP_PHP, [...phpFlags, `${sandbox}/backend/vendor/drush/drush/drush.php`,
    `--root=${sandbox}/backend/web`, '--uri=http://selected-drupal.example.test', 'php:script', `${sandbox}/scripts/private-purchase-http-state.php`],
  { env: { ...process.env, PRIVATE_HTTP_ACTION: action }, encoding: 'utf8', timeout: 30000 });
  return JSON.parse(output.trim());
}
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
// Drupal adds check_logged_in=1 to the first post-login redirect. Compare the
// exact local order path, allowing only that known transient query parameter.
function checkoutDestination(location, orderId) {
  if (!location) return false;
  const url = new URL(location, base);
  return url.origin === base && url.pathname === `/web/checkout/${orderId}` && !url.hash
    && [...url.searchParams].every(([key, value]) => key === 'check_logged_in' && value === '1');
}
const jars = { reunion: new Map(), stock: new Map(), anonymous: new Map() };
async function request(target, who = 'anonymous', form = null, json = null) {
  const url = new URL(target, base);
  if (url.origin !== base || !url.pathname.startsWith('/web/')) throw Error('Nonlocal HTTP destination refused');
  const headers = { Cookie: [...jars[who]].map(([k, v]) => `${k}=${v}`).join('; ') };
  let body;
  if (form) { headers['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form); }
  if (json) { headers['Content-Type'] = 'application/json'; headers.Accept = 'application/json'; body = JSON.stringify(json); }
  const response = await fetch(url, { method: body ? 'POST' : 'GET', headers, body, redirect: 'manual', signal: AbortSignal.timeout(20000) });
  report.requests.push({ method: body ? 'POST' : 'GET', path: url.pathname, status: response.status });
  for (const raw of response.headers.getSetCookie()) {
    const [pair] = raw.split(';'); const pos = pair.indexOf('='); if (pos > 0) jars[who].set(pair.slice(0, pos), pair.slice(pos + 1));
  }
  return { status: response.status, location: response.headers.get('location'), cache: response.headers.get('cache-control'), html: await response.text() };
}
const decode = text => text.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
function fields(html) {
  const result = {};
  for (const input of html.matchAll(/<input\b[^>]*>/gi)) {
    const attributes = Object.fromEntries([...input[0].matchAll(/([\w-]+)="([^"]*)"/g)].map(m => [m[1], decode(m[2])]));
    if (attributes.name && ['hidden', 'submit'].includes(attributes.type)) result[attributes.name] = attributes.value || '';
  }
  if (!result.form_token || !result.form_build_id || !result.form_id || !result.scope_snapshot) throw Error('Native form controls absent');
  return { ...result, domain_choice: 'undecided', domain: '', accept_terms: '1' };
}
const route = kind => `/web/customer/private-purchase/${fixture.requests[kind]}`;
async function deniedPost(form, key) {
  const before = state(); const response = await request(route('reunion'), 'reunion', form);
  check(response.status >= 200 && response.status < 500 && !/\/checkout\//.test(response.location || '') && same(before, state()), key);
  return response;
}
try {
  for (let attempt = 0; attempt < 40; attempt++) {
    try { await request('/web/user/login'); break; } catch (error) { if (attempt === 39) throw error; await new Promise(resolve => setTimeout(resolve, 200)); }
  }
  const initial = state();
  const front = await request('/web/');
  check(front.status === 302 && front.location === 'https://famtasticdesigns.com/', 'native_front_redirect_observed_without_following');
  check((await request('/web/sites/default/settings.php')).status === 404
    && (await request('/web/sites/default/files/.ht.sqlite')).status === 404, 'local_router_does_not_serve_settings_or_database');
  check((await request('/web/themes/custom/famtastic_customer/famtastic-designs-logo-v1.png')).status === 200, 'canonical_logo_asset_is_served');
  check(initial.counts.commerce_order === 1 && initial.counts.commerce_payment === 1 && initial.completion === 'issued', 'fresh_http_fixture_has_only_one_prepaid_order_payment');
  const anon = await request(route('reunion'));
  check([403, 404].includes(anon.status) && !anon.html.includes('Your agreed scope'), 'anonymous_private_purchase_denied_without_scope_leak');
  for (const who of ['reunion', 'stock']) {
    const login = await request('/web/api/customer/login', who, null, fixture.accounts[who]);
    check(login.status === 200 && JSON.parse(login.html).customer?.verified === true && jars[who].size > 0, `${who}_real_verified_cookie_login`);
    check((await request('/web/admin/famtastic', who)).status === 403, `${who}_customer_is_not_admin`);
  }
  check((await request(route('reunion'), 'stock')).status === 404 && (await request(route('stock'), 'reunion')).status === 404, 'foreign_customer_private_routes_reject');
  const page = await request(route('reunion'), 'reunion');
  check(page.status === 200 && page.html.includes('Your agreed scope') && page.html.includes('199.00') && !page.html.includes('completion_code'), 'owned_reunion_form_renders_exact_scope');
  check(page.html.includes('famtastic-purchase-shell') && page.html.includes('famtastic-designs-logo-v1.png')
    && page.html.includes('data-famtastic-creator-credit="v1"') && !page.html.includes('site-header__inner'), 'private_route_has_scoped_brand_shell_and_creator_credit');
  check(/private|no-cache/.test(page.cache || '') && same(initial, state()), 'financial_get_has_private_cache_and_no_financial_side_effect');
  const form = fields(page.html);
  check(form.form_id === 'famtastic_private_purchase', 'native_form_has_csrf_build_id_and_signed_scope');
  for (const [key, patch, remove] of [
    ['missing_csrf_rejected', {}, 'form_token'], ['wrong_csrf_rejected', { form_token: 'invalid' }],
    ['missing_raw_scope_rejected', {}, 'scope_snapshot'], ['tampered_raw_scope_rejected', { scope_snapshot: `${form.scope_snapshot.slice(0, -1)}x` }],
    ['terms_consent_required', {}, 'accept_terms'], ['unsafe_domain_rejected', { domain: 'https://evil.example/' }],
  ]) { const input = { ...form, ...patch }; if (remove) delete input[remove]; await deniedPost(input, key); }
  const beforeForeign = state(); const foreign = await request(route('reunion'), 'stock', form);
  check(foreign.status === 404 && same(beforeForeign, state()), 'foreign_post_cannot_reuse_owner_csrf_snapshot');
  state('scope-change'); await deniedPost(form, 'scope_changed_after_get_rejects_stale_post'); state('scope-restore');
  const created = await request(route('reunion'), 'reunion', form);
  const afterCreate = state();
  check([302, 303].includes(created.status) && afterCreate.reunion_order > 0 && checkoutDestination(created.location, afterCreate.reunion_order), 'valid_authenticated_post_creates_native_order_and_redirects');
  check(afterCreate.counts.commerce_order === 2 && afterCreate.counts.commerce_order_item === 2 && afterCreate.counts.commerce_payment === 1, 'http_create_adds_one_order_but_no_payment');
  const replay = await request(route('reunion'), 'reunion', form);
  check([302, 303].includes(replay.status) && checkoutDestination(replay.location, afterCreate.reunion_order)
    && same(afterCreate, state()), 'repeated_http_post_reuses_same_order_without_payment');
  // Test the allowed native route as well as denials. Follow only this exact
  // local order and its native steps; never navigate a fixture into production.
  let checkoutPage = await request(`/web/checkout/${afterCreate.reunion_order}`, 'reunion');
  for (let redirects = 0; [301, 302, 303, 307, 308].includes(checkoutPage.status) && redirects < 3; redirects++) {
    if (!checkoutPage.location) throw Error('Native checkout redirect missing destination');
    const next = new URL(checkoutPage.location, base);
    if (next.origin !== base || !new RegExp(`^/web/checkout/${afterCreate.reunion_order}(?:/[a-z_]+)?$`).test(next.pathname)
      || next.hash || [...next.searchParams].some(([key, value]) => key !== 'check_logged_in' || value !== '1')) throw Error('Unsafe native checkout redirect');
    checkoutPage = await request(next.href, 'reunion');
  }
  check(checkoutPage.status === 200 && checkoutPage.html.includes('commerce-checkout-flow')
    && checkoutPage.html.includes('form_token'), 'enabled_native_checkout_form_renders_for_owner');
  check(same(afterCreate, state()), 'native_checkout_get_preserves_financial_and_delivery_projection');
  check([403, 404].includes((await request(`/web/checkout/${afterCreate.reunion_order}`, 'stock')).status), 'foreign_native_checkout_rejected');
  state('gateway-disable');
  check((await request(`/web/checkout/${afterCreate.reunion_order}`, 'reunion')).status === 403, 'disabled_saved_gateway_rejected_by_actual_http_middleware');
  const stockPage = await request(route('stock'), 'stock'); const stockForm = fields(stockPage.html);
  check(stockPage.status === 200 && stockPage.html.includes('$200.00') && stockPage.html.includes('$0.00') && stockPage.html.includes('no charge'), 'paid_customer_sees_existing_receipt_and_no_charge_form');
  const beforeCode = state();
  const invalidCode = await request(route('stock'), 'stock', { ...stockForm, completion_code: 'a'.repeat(48) });
  check(invalidCode.status === 200 && same(beforeCode, state()), 'invalid_completion_code_has_no_financial_effect');
  const completed = await request(route('stock'), 'stock', { ...stockForm, completion_code: fixture.completion_code });
  const afterCode = state();
  check([302, 303].includes(completed.status) && afterCode.completion === 'consumed' && afterCode.stock_order === initial.stock_order
    && same(afterCode.counts, beforeCode.counts) && afterCode.launch_authorized === false, 'real_http_completion_consumes_same_paid_purchase_without_launch');
  await request(route('stock'), 'stock', { ...stockForm, completion_code: fixture.completion_code });
  check(same(afterCode, state()), 'completion_http_replay_creates_no_second_sale');
  state('gateway-clear'); // Flag-OFF denial must not be masked by a disabled saved gateway.
  state('checkout-off');
  const offPage = await request(route('reunion'), 'reunion');
  check(offPage.status === 200 && offPage.html.includes('payment is not open yet') && !offPage.html.includes('Continue to secure $199 checkout'), 'disabled_flag_shows_honest_waiting_state');
  await deniedPost(form, 'disabled_flag_rejects_previously_valid_post');
  check((await request(`/web/checkout/${afterCreate.reunion_order}`, 'reunion')).status === 403, 'disabled_flag_blocks_native_checkout_http');
  const final = state();
  check(final.received === '200.00' && final.outstanding === '0.00' && final.launch_authorized === false, 'prepaid_amount_and_final_acceptance_boundary_unchanged');
  for (const table of ['famtastic_job', 'famtastic_notification_outbox', 'famtastic_commerce_fulfillment', 'famtastic_entitlement']) check(final.counts[table] === initial.counts[table], `http_has_no_side_effect_${table}`);
  report.records = final; report.status = 'passed'; report.classification = 'locally proven';
} catch (error) { report.status = 'failed'; report.error = error.message; process.exitCode = 1; console.error(error.message); }
finally { fs.writeFileSync(`${evidence}/private-purchase-http.json`, `${JSON.stringify(report, null, 2)}\n`); }
