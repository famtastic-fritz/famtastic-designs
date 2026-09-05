/**
 * Shared helpers for working with RAW JSON:API node resources on the
 * marketing pages (services, packages, work, blog, faq, homepage).
 *
 * Field shapes in Drupal vary (plain string, formatted text {value,
 * processed}, link {uri, title}, multi-value arrays) and content is still
 * being seeded, so every helper here is defensive: missing/odd values
 * degrade to '' or [] instead of throwing.
 */

/** Slugify arbitrary text: lowercase, non-alnum runs → '-', trimmed. */
export function titleSlug(title) {
  return String(title ?? '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/**
 * Best slug for a raw JSON:API node: last segment of its path alias when
 * present (e.g. '/services/ai-chatbot' → 'ai-chatbot'), else title-slug.
 */
export function nodeSlug(node) {
  const alias = node?.attributes?.path?.alias ?? '';
  const segment = alias.split('/').filter(Boolean).pop();
  return segment || titleSlug(node?.attributes?.title);
}

/**
 * Match a URL :slug param against a list of raw nodes. Alias segment wins;
 * title-slug is the fallback (per the site routing contract).
 */
export function matchBySlug(nodes, slug) {
  const want = titleSlug(slug);
  if (!want) return null;
  return (
    nodes.find((n) => titleSlug(nodeSlug(n)) === want) ??
    nodes.find((n) => titleSlug(n?.attributes?.title) === want) ??
    null
  );
}

/**
 * Extract displayable text from a Drupal field value: plain string,
 * formatted text ({processed}/{value}), or link ({uri}). '' when absent.
 */
export function textValue(v) {
  if (v === null || v === undefined) return '';
  if (typeof v === 'string') return v;
  if (typeof v === 'object') return v.processed ?? v.value ?? v.uri ?? '';
  return String(v);
}

/** Plain text for cards, headings, labels, metadata, and other text nodes. */
export function plainTextValue(v) {
  const raw = textValue(v);
  if (!raw) return '';

  return String(raw)
    .replace(/<\s*br\s*\/?>/gi, ' ')
    .replace(/<\/(p|div|li|h[1-6])\s*>/gi, ' ')
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;|&#160;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;|&#34;/gi, '"')
    .replace(/&#39;|&apos;/gi, "'")
    .replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Number(code)))
    .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCodePoint(parseInt(code, 16)))
    .replace(/\s+/g, ' ')
    .trim();
}

/** Normalize a multi-value text field into a clean string array. */
export function listValues(v) {
  if (!Array.isArray(v)) return [];
  return v
    .map((item) => plainTextValue(item))
    .filter(Boolean);
}

/**
 * Resolve a Drupal link field (or plain string) to a public SPA href.
 * Drupal is mounted below /web, while public React routes are mounted at the
 * domain root. Content editors can therefore use either `internal:/start` or
 * Drupal's `internal:/web/start` without sending a public CTA to backend
 * chrome. Backend-only paths intentionally retain their /web prefix.
 */
export function linkHref(v, fallback = '/contact') {
  if (!v) return fallback;
  const raw = typeof v === 'string' ? v : (v.resolvable_uri ?? v.uri ?? v.url ?? '');
  const clean = String(raw).replace(/^internal:/, '').trim();
  if (!clean) return fallback;

  if (clean === '/web' || clean === '/web/') return '/';
  if (/^\/web\/(?!api(?:\/|$)|admin(?:\/|$)|checkout(?:\/|$)|session(?:\/|$)|user(?:\/|$))/.test(clean)) {
    return clean.slice('/web'.length) || '/';
  }
  return clean;
}

/** True when a link points off-site (http/https/mailto/tel). */
export function isExternalHref(href) {
  return /^(https?:|mailto:|tel:)/i.test(href);
}

/**
 * Read the first non-empty text attribute from a paragraph/entity resource,
 * trying several candidate field names (backend field names evolved during
 * seeding, so we accept the common variants).
 */
export function paraField(resource, keys) {
  const attrs = resource?.attributes ?? {};
  for (const key of keys) {
    const val = plainTextValue(attrs[key]);
    if (val) return val;
  }
  return '';
}
