const WEB_PREFIX = import.meta.env.DEV ? '' : '/web';
const API = `${WEB_PREFIX}/api/customer`;

export class CustomerApiError extends Error {
  constructor(message, status, code) { super(message); this.status = status; this.code = code; }
}

async function request(path, options = {}) {
  let csrfHeaders = {};
  if (options.csrf) {
    const token = await fetch(`${WEB_PREFIX}/session/token`, { credentials: 'same-origin' }).then((response) => response.text());
    csrfHeaders = { 'X-CSRF-Token': token };
  }
  const response = await fetch(`${API}${path}`, {
    credentials: 'same-origin',
    ...options,
    headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...csrfHeaders, ...options.headers },
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new CustomerApiError(payload.message || 'Please try again.', response.status, payload.error);
  return payload;
}

export const customerSession = () => request('/session');
export const customerLogin = (email, password) => request('/login', { method: 'POST', body: JSON.stringify({ email, password }) });
export const customerLogout = () => request('/logout', { method: 'POST', csrf: true });
export const customerRegister = (payload) => request('/register', { method: 'POST', body: JSON.stringify(payload) });
export const verifyCustomerEmail = (token) => request('/verify', { method: 'POST', body: JSON.stringify({ token }) });
export const forgotCustomerPassword = (email) => request('/forgot-password', { method: 'POST', body: JSON.stringify({ email }) });
export const resetCustomerPassword = (token, password) => request('/reset-password', { method: 'POST', body: JSON.stringify({ token, password }) });
export const getCustomerWorkspace = (organization = '') => request(`/workspace${organization ? `?organization=${encodeURIComponent(organization)}` : ''}`);
export const getCustomerCatalog = () => request('/catalog');
export const createCommerceCheckout = (payload) => request('/checkout', { method: 'POST', csrf: true, body: JSON.stringify(payload) });
export const getPaymentHandoff = (organization) => request(`/payment-handoff?organization=${encodeURIComponent(organization)}`);
export const savePaymentHandoff = (payload) => request('/payment-handoff', { method: 'PUT', csrf: true, body: JSON.stringify(payload) });
export const createWebsiteRequest = (payload) => request('/website-requests', { method: 'POST', csrf: true, body: JSON.stringify(payload) });
export const updateWebsiteRequest = (id, payload) => request(`/website-requests/${encodeURIComponent(id)}`, { method: 'PATCH', csrf: true, body: JSON.stringify(payload) });
export const decideWebsiteRequestProof = (id, payload) => request(`/website-requests/${encodeURIComponent(id)}/proof-decision`, { method: 'POST', csrf: true, body: JSON.stringify(payload) });
export const updateWebsiteRequestProofShare = (id, action) => request(`/website-requests/${encodeURIComponent(id)}/proof-share`, { method: 'POST', csrf: true, body: JSON.stringify({ action }) });
export const sendWebsiteRequestToSiteStudio = (id) => request(`/website-requests/${encodeURIComponent(id)}/send-to-site-studio`, { method: 'POST', csrf: true });
export async function uploadWebsiteRequestAsset(id, formData) {
  const token = await fetch(`${WEB_PREFIX}/session/token`, { credentials: 'same-origin' }).then((response) => response.text());
  const response = await fetch(`${API}/website-requests/${encodeURIComponent(id)}/assets`, {
    method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': token }, body: formData,
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new CustomerApiError(payload.message || 'Please try again.', response.status, payload.error);
  return payload;
}
export const updateCustomerProfile = (payload) => request('/profile', { method: 'PATCH', csrf: true, body: JSON.stringify(payload) });
export const updateCustomerPreferences = (payload) => request('/preferences', { method: 'PATCH', csrf: true, body: JSON.stringify(payload) });
export const createCustomerReferral = (payload) => request('/referrals', { method: 'POST', csrf: true, body: JSON.stringify(payload) });
export const createCustomerThread = (payload) => request('/threads', { method: 'POST', csrf: true, body: JSON.stringify(payload) });
export const getCustomerThread = (id) => request(`/threads/${encodeURIComponent(id)}`);
export const replyCustomerThread = (id, body) => request(`/threads/${encodeURIComponent(id)}`, { method: 'POST', csrf: true, body: JSON.stringify({ body }) });

async function deepDiveRequest(invitation, secret, path = '', options = {}) {
  const response = await fetch(`${WEB_PREFIX}/api/deep-dive/${encodeURIComponent(invitation)}${path}`, {
    credentials: 'omit',
    ...options,
    headers: {
      Accept: 'application/json',
      'X-Deep-Dive-Token': secret,
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new CustomerApiError(payload.message || 'This private interview is unavailable.', response.status, payload.error);
  return payload.deep_dive;
}

export const getDeepDive = (invitation, secret) => deepDiveRequest(invitation, secret);
export const answerDeepDive = (invitation, secret, key, answer) => deepDiveRequest(invitation, secret, '/answer', {
  method: 'POST', body: JSON.stringify({ key, answer }),
});
