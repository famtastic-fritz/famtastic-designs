const measurementId = import.meta.env?.VITE_GA_MEASUREMENT_ID?.trim();

let initialized = false;

const sensitiveQueryKeys = new Set(['token', 'key', 'code', 'session', 'secret', 'continuation']);
const locationEventKeys = new Set(['page_location', 'page_path', 'page_referrer', 'link_url', 'url', 'href', 'destination_url', 'redirect_url']);
const continuationValue = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.[0-9a-f]{64}/i;

function redactSearch(url) {
  [...url.searchParams.keys()].forEach((key) => {
    if (sensitiveQueryKeys.has(key.toLowerCase())) url.searchParams.delete(key);
  });
  url.hash = '';
  return url;
}

function isContinuationValue(value) {
  if (typeof value !== 'string') return false;
  try {
    return continuationValue.test(decodeURIComponent(value));
  } catch {
    return continuationValue.test(value);
  }
}

function hasSensitiveQuery(value) {
  if (typeof value !== 'string') return false;
  try {
    return /(?:^|[?&])(token|key|code|session|secret|continuation)=/i.test(decodeURIComponent(value));
  } catch {
    return /(?:^|[?&])(token|key|code|session|secret|continuation)=/i.test(value);
  }
}

function cleanPathname(pathname) {
  return pathname
    .replace(/^\/proofs\/share\/[^/]+\/[^/]+/, '/proofs/share/unlisted')
    .replace(/^\/proofs\/preview\/[^/]+\/[^/]+/, '/proofs/preview/unlisted')
    .replace(/^\/portal\/[^/]+/, '/portal/personalized')
    .replace(/^\/p\/[^/]+/, '/p/personalized');
}

export function safeLocation(path) {
  const current = new URL(window.location.href);
  // React Router supplies `pathname + search`, so parse it as a URL before
  // assigning a pathname. Otherwise `?continuation=...` is encoded into the
  // pathname and survives query redaction.
  const requested = path ? new URL(String(path), current.origin) : current;
  const url = new URL(current.origin);
  url.pathname = cleanPathname(requested.pathname);
  url.search = requested.search;
  redactSearch(url);
  return { path: `${url.pathname}${url.search}`, location: url.toString() };
}

function safeEventUrl(value) {
  if (typeof value !== 'string') return value;
  const current = new URL(window.location.href);
  const url = redactSearch(new URL(value, current.origin));
  return url.toString();
}

export function safeEventParams(params = {}) {
  if (!params || typeof params !== 'object' || Array.isArray(params)) return {};

  return Object.fromEntries(Object.entries(params).flatMap(([key, value]) => {
    const normalizedKey = key.toLowerCase();
    // A continuation is a bearer credential. Reject direct values, aliases
    // such as `preview_continuation`, and any arbitrary field that embeds one.
    if (normalizedKey === 'continuation' || normalizedKey.endsWith('_continuation')) return [];

    if (normalizedKey === 'page_location') return [[key, safeLocation(value).location]];
    if (normalizedKey === 'page_path') return [[key, safeLocation(value).path]];
    if (locationEventKeys.has(normalizedKey)) return [[key, safeEventUrl(value)]];
    if (isContinuationValue(value) || hasSensitiveQuery(value)) return [];
    return [[key, value]];
  }));
}

export function initializeGoogleAnalytics() {
  if (!measurementId || initialized || typeof window === 'undefined') return false;

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() {
    window.dataLayer.push(arguments);
  };

  window.gtag('js', new Date());
  window.gtag('config', measurementId, { send_page_view: false });

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
  document.head.appendChild(script);

  initialized = true;
  return true;
}

export function trackPageView(path) {
  if (!initializeGoogleAnalytics() && !initialized) return;
  const safe = safeLocation(path);

  window.gtag('event', 'page_view', {
    page_location: safe.location,
    page_path: safe.path,
    page_title: document.title,
  });
}

/**
 * Custom commerce/lifecycle event (view_item, select_item, purchase...).
 * Safe no-op when GA is unavailable; never throws into render paths.
 */
export function trackEvent(name, params = {}) {
  try {
    if (!initializeGoogleAnalytics() && !initialized) return;
    window.gtag('event', name, safeEventParams(params));
  } catch {
    /* analytics must never break the page */
  }
}
