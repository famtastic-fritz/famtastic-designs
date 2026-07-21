/**
 * FAMtastic Designs — headless Drupal 11 JSON:API client.
 *
 * Phase 1 uses *anonymous* JSON:API access only: the backend grants the
 * anonymous user role read access to published content via JSON:API, so this
 * client sends no credentials, tokens, or OAuth flows — just the JSON:API
 * Accept header. Writes are out of scope for Phase 1.
 *
 * Base URL resolution:
 *   1. VITE_DRUPAL_BASE_URL (absolute, e.g. http://localhost:8080)
 *   2. '' (relative) — falls back to the Vite dev-server proxy for /jsonapi,
 *      which keeps the SPA working even when CORS is misconfigured.
 *
 * Every helper degrades gracefully: on network/CORS failure (or non-2xx),
 * it returns clearly-marked stub data (`__stub: true`) so the UI still
 * renders while the backend is down.
 */

const DRUPAL_BASE = (import.meta.env.VITE_DRUPAL_BASE_URL ?? '').replace(/\/+$/, '');
const JSONAPI_BASE = `${DRUPAL_BASE}/jsonapi`;

/** Flag attached to stub payloads so the UI can badge them. */
const STUB_FLAG = '__stub';

/* ------------------------------------------------------------------ */
/* Stub data (returned only when the backend is unreachable)           */
/* ------------------------------------------------------------------ */

const STUB_NODES = [
  {
    id: 'stub-0001-aaaa-bbbb-cccc-000000000001',
    type: 'article',
    title: 'Welcome to FAMtastic Designs',
    summary:
      'This is stub content rendered because the Drupal backend could not be reached. Start Drupal on http://localhost:8080 to see live JSON:API content.',
    body: '<p>This is <strong>stub content</strong>. The headless Drupal 11 backend is unreachable, so the frontend is rendering built-in sample data instead of live JSON:API resources.</p><p>Once the backend is up, this page will be replaced by the real node.</p>',
    path: '/node/stub-0001-aaaa-bbbb-cccc-000000000001',
    [STUB_FLAG]: true,
  },
  {
    id: 'stub-0002-aaaa-bbbb-cccc-000000000002',
    type: 'article',
    title: 'Headless Drupal 11 + React 18',
    summary:
      'A decoupled stack: Drupal 11 exposes JSON:API, this React 18 + Vite SPA consumes it. Stub teaser shown while the API is offline.',
    body: '<p>Drupal 11 serves structured content over <code>/jsonapi</code>; React 18 renders it. This stub stands in for the real article while the backend is down.</p>',
    path: '/node/stub-0002-aaaa-bbbb-cccc-000000000002',
    [STUB_FLAG]: true,
  },
  {
    id: 'stub-0003-aaaa-bbbb-cccc-000000000003',
    type: 'article',
    title: 'Designing in the Dark',
    summary:
      'Brand palette: near-black surfaces, lime accent (#7CFC00). Stub teaser shown while the API is offline.',
    body: '<p>Premium dark UI with a lime accent — rendered locally as a stub while JSON:API is unavailable.</p>',
    path: '/node/stub-0003-aaaa-bbbb-cccc-000000000003',
    [STUB_FLAG]: true,
  },
];

const STUB_MENU = [
  { id: 'stub-menu-home', title: 'Home', url: '/', [STUB_FLAG]: true },
  { id: 'stub-menu-articles', title: 'Articles', url: '/content/article', [STUB_FLAG]: true },
  { id: 'stub-menu-pages', title: 'Pages', url: '/content/page', [STUB_FLAG]: true },
];

/* ------------------------------------------------------------------ */
/* Low-level fetch wrapper                                             */
/* ------------------------------------------------------------------ */

/**
 * Fetch a JSON:API resource. Throws on network/CORS failure or non-2xx so
 * callers can fall back to stub data.
 * @param {string} path JSON:API path beginning with `/jsonapi/...`.
 * @returns {Promise<object>} Parsed JSON:API document.
 */
async function apiFetch(path) {
  const res = await fetch(`${DRUPAL_BASE}${path}`, {
    headers: { Accept: 'application/vnd.api+json' },
    credentials: 'omit', // anonymous access only — never send cookies.
  });
  if (!res.ok) {
    throw new Error(`JSON:API ${res.status} ${res.statusText} for ${path}`);
  }
  return res.json();
}

/* ------------------------------------------------------------------ */
/* OAuth2 (drupal/simple_oauth) — Phase 2                              */
/* ------------------------------------------------------------------ */

/**
 * localStorage key holding the persisted OAuth token bundle:
 *   { access_token, refresh_token, expires_at, user_email }
 * `expires_at` is epoch milliseconds (Date.now() + expires_in * 1000).
 *
 * NOTE: localStorage is convenient but readable by any JS on the origin
 * (XSS risk). For production, prefer a BFF/proxy that stores tokens in
 * secure httpOnly SameSite cookies so tokens never touch JS-accessible
 * storage; this client would then call that proxy instead of /oauth/token.
 */
const TOKEN_STORAGE_KEY = 'famtastic_oauth';

/**
 * Custom window event dispatched whenever the stored token changes outside
 * React state (e.g. an automatic refresh inside apiFetchAuth). UserContext
 * subscribes to stay in sync without a circular import.
 */
const AUTH_EVENT = 'famtastic:auth';

/** OAuth client_id (Drupal Consumer) for the password/refresh_token grants. */
const OAUTH_CLIENT_ID = import.meta.env.VITE_OAUTH_CLIENT_ID || 'famtastic_spa';

/** Read the persisted token bundle, or null when absent/corrupt. */
export function readStoredToken() {
  try {
    const raw = localStorage.getItem(TOKEN_STORAGE_KEY);
    if (!raw) return null;
    const token = JSON.parse(raw);
    return token && token.access_token ? token : null;
  } catch {
    return null;
  }
}

/** Persist a token bundle and notify subscribers. */
export function storeToken(token) {
  localStorage.setItem(TOKEN_STORAGE_KEY, JSON.stringify(token));
  window.dispatchEvent(new CustomEvent(AUTH_EVENT, { detail: { token } }));
}

/** Drop the persisted token and notify subscribers (used on logout/401). */
export function clearStoredToken() {
  localStorage.removeItem(TOKEN_STORAGE_KEY);
  window.dispatchEvent(new CustomEvent(AUTH_EVENT, { detail: { token: null } }));
}

/** True when the token bundle is missing or past its expiry time. */
export function isTokenExpired(token) {
  return !token?.access_token || !token.expires_at || Date.now() >= token.expires_at;
}

/**
 * POST a grant request to /oauth/token and normalize the simple_oauth
 * response into the stored token bundle shape.
 * @param {URLSearchParams} params Grant-specific form fields.
 * @returns {Promise<object>} { access_token, refresh_token, expires_at }.
 */
async function oauthRequest(params) {
  const res = await fetch(`${DRUPAL_BASE}/oauth/token`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      Accept: 'application/json',
    },
    credentials: 'omit',
    body: params.toString(),
  });

  let payload = {};
  try {
    payload = await res.json();
  } catch {
    // Non-JSON error body (e.g. proxy/500 HTML) — handled below.
  }

  if (!res.ok) {
    const code = payload.error ?? '';
    const description = payload.error_description ?? payload.message ?? '';
    // invalid_grant on the password grant = wrong username/password.
    if (code === 'invalid_grant' || res.status === 400 || res.status === 401) {
      throw new Error('Invalid email or password.');
    }
    throw new Error(
      description || `OAuth token request failed (${res.status} ${res.statusText}).`,
    );
  }

  if (!payload.access_token) {
    throw new Error('OAuth token response did not include an access_token.');
  }

  return {
    access_token: payload.access_token,
    refresh_token: payload.refresh_token ?? null,
    expires_at: Date.now() + (Number(payload.expires_in) || 0) * 1000,
  };
}

/**
 * Password grant: exchange user credentials for an OAuth token bundle.
 * The simple_oauth module expects `username` — the SPA logs in with the
 * Drupal account email, so the email is sent as the username.
 * @param {string} email Drupal user email.
 * @param {string} password Drupal user password.
 * @returns {Promise<object>} Token bundle (not yet persisted).
 */
export async function login(email, password) {
  const params = new URLSearchParams({
    grant_type: 'password',
    client_id: OAUTH_CLIENT_ID,
    username: email,
    password,
  });
  const token = await oauthRequest(params);
  return { ...token, user_email: email };
}

/**
 * Refresh_token grant: exchange a refresh token for a new token bundle.
 * simple_oauth rotates refresh tokens, but if the response omits one we
 * keep the existing refresh token so the session is not lost.
 * @param {string} refreshTokenValue Current refresh token.
 * @returns {Promise<object>} Token bundle (not yet persisted).
 */
export async function refreshToken(refreshTokenValue) {
  const params = new URLSearchParams({
    grant_type: 'refresh_token',
    client_id: OAUTH_CLIENT_ID,
    refresh_token: refreshTokenValue,
  });
  const token = await oauthRequest(params);
  return { ...token, refresh_token: token.refresh_token ?? refreshTokenValue };
}

/**
 * Authenticated JSON:API request — like apiFetch but with an
 * `Authorization: Bearer` header and a one-time transparent token refresh
 * on 401. On refresh failure the stored session is cleared and the 401 is
 * re-thrown so the UI can drop back to the login screen.
 *
 * @param {string} path JSON:API path beginning with `/jsonapi/...`.
 * @param {object|string} token Stored token bundle (or raw access token).
 * @param {object} [options] { method, body, _retried } — body is JSON-stringified.
 * @returns {Promise<object|null>} Parsed JSON:API document (null for 204).
 */
export async function apiFetchAuth(path, token, options = {}) {
  const bundle = typeof token === 'string' ? { access_token: token } : { ...token };

  const res = await fetch(`${DRUPAL_BASE}${path}`, {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/vnd.api+json',
      ...(options.body ? { 'Content-Type': 'application/vnd.api+json' } : {}),
      Authorization: `Bearer ${bundle.access_token}`,
    },
    credentials: 'omit',
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  if (res.status === 401 && !options._retried) {
    if (!bundle.refresh_token) {
      clearStoredToken();
      throw new Error('Session expired — please log in again.');
    }
    try {
      const refreshed = await refreshToken(bundle.refresh_token);
      const next = { ...bundle, ...refreshed };
      storeToken(next); // persists + notifies UserContext
      return apiFetchAuth(path, next, { ...options, _retried: true });
    } catch {
      clearStoredToken();
      throw new Error('Session expired — please log in again.');
    }
  }

  if (!res.ok) {
    let detail = '';
    try {
      const errDoc = await res.json();
      detail = errDoc?.errors?.[0]?.detail ?? errDoc?.errors?.[0]?.title ?? '';
    } catch {
      // keep the generic message
    }
    throw new Error(
      detail || `JSON:API ${res.status} ${res.statusText} for ${path}`,
    );
  }

  if (res.status === 204) return null;
  return res.json();
}

/* ------------------------------------------------------------------ */
/* Normalizers                                                         */
/* ------------------------------------------------------------------ */

/** Strip HTML tags for a plain-text teaser fallback. */
function stripHtml(html) {
  return String(html ?? '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Normalize a JSON:API node resource into the flat shape the components use.
 * @param {object} resource JSON:API resource object.
 */
function normalizeNode(resource) {
  const { id, attributes = {} } = resource;
  const bundle = resource.type?.split('--')[1] ?? 'node';
  const bodyHtml = attributes.body?.processed ?? attributes.body?.value ?? '';
  const summary =
    attributes.body?.summary ||
    (bodyHtml ? `${stripHtml(bodyHtml).slice(0, 220)}…` : '');

  return {
    id,
    type: bundle,
    title: attributes.title ?? 'Untitled',
    summary,
    body: bodyHtml,
    path: attributes.path?.alias ?? `/node/${id}`,
    created: attributes.created ?? null,
    [STUB_FLAG]: false,
  };
}

/* ------------------------------------------------------------------ */
/* Public helpers                                                      */
/* ------------------------------------------------------------------ */

/**
 * List published nodes of a content type.
 * @param {string} type Drupal node bundle (e.g. 'article', 'page').
 * @returns {Promise<Array>} Normalized nodes (stub-marked on failure).
 */
export async function getNodes(type = 'article') {
  try {
    const json = await apiFetch(
      `/jsonapi/node/${encodeURIComponent(type)}?filter[status]=1&sort=-created&page[limit]=24`,
    );
    return (json.data ?? []).map(normalizeNode);
  } catch (err) {
    console.warn(`[drupal] getNodes("${type}") failed, using stub data:`, err.message);
    return STUB_NODES.map((node) => ({ ...node, type }));
  }
}

/**
 * Fetch a single node by UUID across the common bundles. Tries 'article'
 * then 'page' (Phase 1 only ships these two types).
 * @param {string} uuid Node UUID.
 * @returns {Promise<object|null>} Normalized node, or null when not found.
 */
export async function getNode(uuid) {
  // Stub nodes resolve locally — the backend never knew them.
  const stub = STUB_NODES.find((node) => node.id === uuid);
  if (stub) return stub;

  for (const type of ['article', 'page']) {
    try {
      const json = await apiFetch(`/jsonapi/node/${type}/${encodeURIComponent(uuid)}`);
      if (json.data) return normalizeNode(json.data);
    } catch (err) {
      console.warn(`[drupal] getNode("${uuid}") as ${type} failed:`, err.message);
    }
  }
  return null;
}

/**
 * Fetch main-menu links. Menu items are not exposed by JSON:API core without
 * extra config, so this tries the Decoupled Menus endpoint first and falls
 * back to a static stub menu on any failure.
 * @returns {Promise<Array>} Menu items: { id, title, url }.
 */
export async function getMenus() {
  try {
    const json = await apiFetch('/jsonapi/menu_items/main');
    const items = (json.data ?? []).map((item) => ({
      id: item.id,
      title: item.attributes?.title ?? 'Link',
      url: item.attributes?.url ?? item.attributes?.link?.uri ?? '/',
      [STUB_FLAG]: false,
    }));
    return items.length ? items : STUB_MENU;
  } catch (err) {
    console.warn('[drupal] getMenus() failed, using stub menu:', err.message);
    return STUB_MENU;
  }
}

/* ------------------------------------------------------------------ */
/* Client Projects (authenticated admin operations)                    */
/* ------------------------------------------------------------------ */

/**
 * Machine names of the Phase 2 'Client Project' bundle fields. These must
 * match the Drupal field config (field.storage.node.*) shipped by the
 * backend; the ?? fallbacks keep rendering graceful if a field is absent.
 */
const PROJECT_TYPE = 'client_project';
const PROJECT_RESOURCE = `node--${PROJECT_TYPE}`;

/** Flatten one JSON:API client_project resource for the admin table. */
function normalizeProject(resource) {
  const { id, attributes = {} } = resource;
  return {
    id,
    title: attributes.title ?? 'Untitled project',
    client: attributes.field_client_name ?? '',
    status: attributes.field_project_status ?? 'discovery',
    budget:
      attributes.field_budget !== null && attributes.field_budget !== undefined
        ? Number(attributes.field_budget)
        : null,
    dueDate: attributes.field_due_date ?? null,
    created: attributes.created ?? null,
    [STUB_FLAG]: false,
  };
}

/**
 * List client projects for the admin dashboard (authenticated equivalent
 * of getNodes('client_project')). Returns [] on failure rather than stub
 * data — the dashboard needs to know the truth.
 * @param {object|string} token Stored token bundle (or raw access token).
 * @returns {Promise<Array>} Normalized projects.
 */
export async function getMyProjects(token) {
  try {
    const json = await apiFetchAuth(
      `/jsonapi/node/${PROJECT_TYPE}?sort=-created&page[limit]=50`,
      token,
    );
    return (json.data ?? []).map(normalizeProject);
  } catch (err) {
    console.warn('[drupal] getMyProjects() failed:', err.message);
    throw err;
  }
}

/** Build a JSON:API attributes object from dashboard form data. */
function projectAttributes(data) {
  const attributes = {
    title: String(data.title ?? '').trim(),
    field_client_name: String(data.client ?? '').trim(),
    field_project_status: String(data.status ?? 'discovery'),
  };
  if (data.budget !== '' && data.budget !== null && data.budget !== undefined) {
    attributes.field_budget = Number(data.budget);
  }
  if (data.dueDate) {
    attributes.field_due_date = String(data.dueDate); // ISO date: YYYY-MM-DD
  }
  return attributes;
}

/**
 * Create a Client Project node.
 * @param {object} data { title, client, status, budget, dueDate }.
 * @param {object|string} token Stored token bundle.
 * @returns {Promise<object>} Normalized created project.
 */
export async function createProject(data, token) {
  const json = await apiFetchAuth(`/jsonapi/node/${PROJECT_TYPE}`, token, {
    method: 'POST',
    body: {
      data: {
        type: PROJECT_RESOURCE,
        attributes: projectAttributes(data),
      },
    },
  });
  return normalizeProject(json.data);
}

/**
 * Update a Client Project node by UUID.
 * @param {string} uuid Node UUID.
 * @param {object} data Partial { title, client, status, budget, dueDate }.
 * @param {object|string} token Stored token bundle.
 * @returns {Promise<object>} Normalized updated project.
 */
export async function updateProject(uuid, data, token) {
  const json = await apiFetchAuth(
    `/jsonapi/node/${PROJECT_TYPE}/${encodeURIComponent(uuid)}`,
    token,
    {
      method: 'PATCH',
      body: {
        data: {
          type: PROJECT_RESOURCE,
          id: uuid,
          attributes: projectAttributes(data),
        },
      },
    },
  );
  return normalizeProject(json.data);
}

/**
 * Delete a Client Project node by UUID (JSON:API answers 204 No Content).
 * @param {string} uuid Node UUID.
 * @param {object|string} token Stored token bundle.
 */
export async function deleteProject(uuid, token) {
  await apiFetchAuth(
    `/jsonapi/node/${PROJECT_TYPE}/${encodeURIComponent(uuid)}`,
    token,
    { method: 'DELETE' },
  );
}

/* ------------------------------------------------------------------ */
/* Raw JSON:API helpers (field-level access for the marketing pages)   */
/*                                                                     */
/* These are ADDITIVE: getNodes()/getNode()/getMenus() above keep      */
/* their normalized flat shapes and stub fallbacks untouched. The raw  */
/* helpers below expose the full JSON:API resource objects (attributes */
/* + relationships + `included`) so pages can render custom fields.    */
/* They never return stub data — on failure they resolve to empty      */
/* collections with the error attached, and pages render friendly      */
/* empty/fallback states instead.                                      */
/* ------------------------------------------------------------------ */

/**
 * List published nodes of a bundle as RAW JSON:API resources.
 * @param {string} type Drupal node bundle (e.g. 'service_page').
 * @param {object} [options] { include: 'field_a,field_b', limit: 50 }.
 * @returns {Promise<{data: Array, included: Array, error: Error|null}>}
 */
export async function getNodesRaw(type, { include = '', limit = 50 } = {}) {
  try {
    let path = `/jsonapi/node/${encodeURIComponent(type)}?filter[status]=1&sort=title&page[limit]=${limit}`;
    if (include) path += `&include=${encodeURIComponent(include)}`;
    const json = await apiFetch(path);
    return { data: json.data ?? [], included: json.included ?? [], error: null };
  } catch (err) {
    console.warn(`[drupal] getNodesRaw("${type}") failed:`, err.message);
    return { data: [], included: [], error: err };
  }
}

/**
 * Fetch a single node of a bundle by its URL alias (e.g. '/services/seo').
 * Falls back to client-side matching because core JSON:API cannot filter
 * on the computed path.alias field directly.
 * @param {string} type Drupal node bundle.
 * @param {string} alias URL alias to match (leading slash optional).
 * @param {object} [options] { include } — passed to getNodesRaw.
 * @returns {Promise<{node: object|null, included: Array, error: Error|null}>}
 */
export async function getNodeByAlias(type, alias, { include = '' } = {}) {
  const { data, included, error } = await getNodesRaw(type, { include });
  const clean = `/${String(alias).replace(/^\/+|\/+$/g, '')}`;
  const node =
    data.find(
      (n) =>
        (n.attributes?.path?.alias ?? '').replace(/\/+$/, '') === clean,
    ) ?? null;
  return { node, included, error };
}

/**
 * Resolve a paragraph/entity-reference relationship on a raw node against
 * the JSON:API `included` array, preserving relationship order.
 * @param {object} node Raw JSON:API resource.
 * @param {Array} included JSON:API included array.
 * @param {string} relName Relationship field name (e.g. 'field_faq_qa').
 * @returns {Array} Resolved included resources ([] when unresolved).
 */
export function resolveIncluded(node, included, relName) {
  const refs = node?.relationships?.[relName]?.data;
  if (!Array.isArray(refs)) return [];
  const byKey = new Map((included ?? []).map((r) => [`${r.type}:${r.id}`, r]));
  return refs.map((r) => byKey.get(`${r.type}:${r.id}`)).filter(Boolean);
}

/** Exported for diagnostics (e.g. footer/debug display). */
export { DRUPAL_BASE, JSONAPI_BASE, STUB_FLAG, TOKEN_STORAGE_KEY, AUTH_EVENT };
