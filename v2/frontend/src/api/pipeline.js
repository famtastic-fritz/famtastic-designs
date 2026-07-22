// FAMtastic prospect pipeline API client.
//
// The prospect authenticates with the link token from the URL (/p/:token),
// sent as the X-Prospect-Token header — never in the query string or a cookie.
// By default requests are relative ("/api/pipeline/...") so the Vite dev proxy
// (and, in production, the same-origin frontend) forwards them to Drupal.

const BASE = (import.meta.env.VITE_DRUPAL_BASE_URL ?? '').replace(/\/+$/, '');
const API = `${BASE}/api/pipeline`;

function tokenHeaders(token, extra = {}) {
  return { 'X-Prospect-Token': token, ...extra };
}

async function parse(res) {
  let body = null;
  try {
    body = await res.json();
  } catch {
    body = null;
  }
  if (!res.ok) {
    const message = body?.message || body?.error || `Request failed (${res.status})`;
    const err = new Error(message);
    err.status = res.status;
    err.code = body?.error;
    throw err;
  }
  return body;
}

export async function getSession(token) {
  const res = await fetch(`${API}/session`, { headers: tokenHeaders(token) });
  return parse(res);
}

export async function confirmProspect(token, payload) {
  const res = await fetch(`${API}/confirm`, {
    method: 'POST',
    headers: tokenHeaders(token, { 'Content-Type': 'application/json' }),
    body: JSON.stringify(payload),
  });
  return parse(res);
}

export async function startCheckout(token) {
  const res = await fetch(`${API}/checkout`, {
    method: 'POST',
    headers: tokenHeaders(token, { 'Content-Type': 'application/json' }),
    body: '{}',
  });
  return parse(res);
}

export async function getOrderStatus(token) {
  const res = await fetch(`${API}/order-status`, { headers: tokenHeaders(token) });
  return parse(res);
}

// Stub-mode only: drives the same fulfillment path a real Stripe webhook would.
export async function simulatePayment(token) {
  const res = await fetch(`${API}/stripe/simulate`, {
    method: 'POST',
    headers: tokenHeaders(token),
  });
  return parse(res);
}

// Public, unauthenticated intake for the SolutionFinder lead-capture form.
// Same base + /intake path as the token-scoped submitIntake below, but for
// anonymous prospects who have no link token yet.
export async function postIntake(payload) {
  const res = await fetch(`${API}/intake`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  return parse(res);
}

export async function submitIntake(token, payload) {
  const res = await fetch(`${API}/intake`, {
    method: 'POST',
    headers: tokenHeaders(token, { 'Content-Type': 'application/json' }),
    body: JSON.stringify(payload),
  });
  return parse(res);
}

export async function uploadAsset(token, file) {
  const form = new FormData();
  form.append('file', file);
  const res = await fetch(`${API}/asset`, {
    method: 'POST',
    headers: tokenHeaders(token),
    body: form,
  });
  return parse(res);
}

export async function submitApproval(token, action, note = '') {
  const res = await fetch(`${API}/approval`, {
    method: 'POST',
    headers: tokenHeaders(token, { 'Content-Type': 'application/json' }),
    body: JSON.stringify({ action, note }),
  });
  return parse(res);
}

// --- Proof Campaign -------------------------------------------------------
// Token-scoped proof-campaign endpoints: 3 auto-generated design directions
// the prospect previews and picks from before checkout. Same X-Prospect-Token
// auth style as the rest of the pipeline client — never fake success.

export async function getProofCampaign(token) {
  const res = await fetch(`${API}/proof-campaign`, { headers: tokenHeaders(token) });
  return parse(res);
}

export async function createProofCampaign(token, payload = {}) {
  const res = await fetch(`${API}/proof-campaign`, {
    method: 'POST',
    headers: tokenHeaders(token, { 'Content-Type': 'application/json' }),
    body: JSON.stringify(payload),
  });
  return parse(res);
}

export async function selectProofVariant(token, selection) {
  const res = await fetch(`${API}/proof-campaign/select`, {
    method: 'POST',
    headers: tokenHeaders(token, { 'Content-Type': 'application/json' }),
    body: JSON.stringify(selection),
  });
  return parse(res);
}

export function formatPrice(minorUnits, currency = 'usd') {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency.toUpperCase() })
    .format((minorUnits || 0) / 100);
}
