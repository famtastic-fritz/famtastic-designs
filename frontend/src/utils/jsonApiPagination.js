/** Keep dev JSON:API pagination on the configured proxy; production stays absolute. */
export function resolveJsonApiNext(next, { origin, useDevelopmentProxy = false }) {
  const url = new URL(next, origin);
  if (useDevelopmentProxy && /^\/(?:web\/)?jsonapi\//.test(url.pathname)) {
    return url.pathname.replace(/^\/web(?=\/jsonapi\/)/, '') + url.search;
  }
  return url.href;
}
