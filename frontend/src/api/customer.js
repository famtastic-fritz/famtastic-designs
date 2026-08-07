const API = '/web/api/customer';

export class CustomerApiError extends Error {
  constructor(message, status, code) { super(message); this.status = status; this.code = code; }
}

async function request(path, options = {}) {
  let csrfHeaders = {};
  if (options.csrf) {
    const token = await fetch('/web/session/token', { credentials: 'same-origin' }).then((response) => response.text());
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
export const updateCustomerProfile = (payload) => request('/profile', { method: 'PATCH', csrf: true, body: JSON.stringify(payload) });
export const createCustomerThread = (payload) => request('/threads', { method: 'POST', csrf: true, body: JSON.stringify(payload) });
export const getCustomerThread = (id) => request(`/threads/${encodeURIComponent(id)}`);
export const replyCustomerThread = (id, body) => request(`/threads/${encodeURIComponent(id)}`, { method: 'POST', csrf: true, body: JSON.stringify({ body }) });
