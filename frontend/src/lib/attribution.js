/**
 * Campaign attribution capture.
 *
 * Every promotional channel points at the site with tracking params. This
 * module records them once on arrival so a lead submitted several clicks
 * later still carries the channel that produced it.
 *
 * Two touches are kept:
 *   - first  (localStorage)   the channel that originally found the visitor
 *   - last   (sessionStorage) the channel that brought them back this visit
 *
 * Offline and print channels (flyers, vehicle magnets, business cards) cannot
 * carry a full UTM string, so a short `?src=` code is accepted and expanded
 * into the same shape.
 */

const FIRST_KEY = 'famtastic.attribution.first';
const LAST_KEY = 'famtastic.attribution.last';

const UTM_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
const CLICK_IDS = ['gclid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id'];

/**
 * Short codes for channels that can only carry a few characters.
 * Keep in sync with the channel table in docs/CAMPAIGN_199.md.
 */
const SHORT_CODES = {
  card: { utm_source: 'business-card', utm_medium: 'offline' },
  flyer: { utm_source: 'flyer', utm_medium: 'offline' },
  van: { utm_source: 'vehicle', utm_medium: 'offline' },
  yard: { utm_source: 'yard-sign', utm_medium: 'offline' },
  gbp: { utm_source: 'google-business-profile', utm_medium: 'organic' },
  ig: { utm_source: 'instagram', utm_medium: 'social' },
  fb: { utm_source: 'facebook', utm_medium: 'social' },
  li: { utm_source: 'linkedin', utm_medium: 'social' },
  nd: { utm_source: 'nextdoor', utm_medium: 'social' },
  yt: { utm_source: 'youtube', utm_medium: 'social' },
  ref: { utm_source: 'referral', utm_medium: 'word-of-mouth' },
};

function safeRead(storage, key) {
  try {
    const raw = storage.getItem(key);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function safeWrite(storage, key, value) {
  try {
    storage.setItem(key, JSON.stringify(value));
  } catch {
    /* private mode or storage disabled — attribution degrades to none */
  }
}

/**
 * Read tracking params out of a query string. Returns null when the visit
 * carries no campaign markers at all, so organic visits do not overwrite a
 * previously recorded channel.
 */
export function parseAttribution(search = '', referrer = '') {
  const params = new URLSearchParams(search);
  const found = {};

  const shortCode = (params.get('src') || '').trim().toLowerCase();
  if (shortCode && SHORT_CODES[shortCode]) {
    Object.assign(found, SHORT_CODES[shortCode], { utm_campaign: 'launch-199' });
  }

  UTM_PARAMS.forEach((key) => {
    const value = params.get(key);
    if (value) found[key] = value.slice(0, 120);
  });

  CLICK_IDS.forEach((key) => {
    const value = params.get(key);
    if (value) {
      found.click_id = value.slice(0, 200);
      found.click_id_type = key;
    }
  });

  if (Object.keys(found).length === 0) return null;

  return {
    ...found,
    landing_path: typeof window !== 'undefined' ? normalizePath(window.location.pathname) : null,
    referrer: referrer || null,
  };
}

/**
 * Trailing slashes vary by how a visitor arrived (/199 vs /199/), which would
 * otherwise split one campaign across two landing_path values in reporting.
 */
function normalizePath(pathname) {
  return pathname.replace(/\/+$/, '') || '/';
}

/**
 * Record the current visit's campaign params. Call once per page load.
 * First touch is written only when absent; last touch is always refreshed.
 */
export function captureAttribution() {
  if (typeof window === 'undefined') return null;

  const touch = parseAttribution(window.location.search, document.referrer);
  if (!touch) return getAttribution();

  if (!safeRead(window.localStorage, FIRST_KEY)) {
    safeWrite(window.localStorage, FIRST_KEY, touch);
  }
  safeWrite(window.sessionStorage, LAST_KEY, touch);

  return getAttribution();
}

/** Current stored attribution, or null when the visitor arrived untagged. */
export function getAttribution() {
  if (typeof window === 'undefined') return null;

  const first = safeRead(window.localStorage, FIRST_KEY);
  const last = safeRead(window.sessionStorage, LAST_KEY);
  if (!first && !last) return null;

  return { first: first || last, last: last || first };
}

/**
 * Flatten attribution into the flat fields the pipeline `/contact` endpoint
 * accepts alongside the form values. Returns an empty object when untagged so
 * the payload shape is unchanged for organic leads.
 */
export function attributionFields() {
  const attribution = getAttribution();
  if (!attribution) return {};

  const { first, last } = attribution;
  return {
    utm_source: last.utm_source || null,
    utm_medium: last.utm_medium || null,
    utm_campaign: last.utm_campaign || null,
    utm_content: last.utm_content || null,
    utm_term: last.utm_term || null,
    click_id: last.click_id || null,
    first_touch_source: first.utm_source || null,
    first_touch_campaign: first.utm_campaign || null,
    landing_path: first.landing_path || null,
  };
}

/**
 * A queryable `source` value for the pipeline Prospect entity, which stores
 * this as a first-class field (the full attribution object still lands in
 * discovery_notes with the rest of the payload). Untagged visits keep the
 * plain form name so existing records stay comparable.
 */
export function attributionSource(formName) {
  const attribution = getAttribution();
  const channel = attribution?.last?.utm_source;
  return channel ? `${formName}:${channel}` : formName;
}

export const SHORT_CODE_MAP = SHORT_CODES;
