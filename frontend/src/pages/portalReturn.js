/** Preserve a private workspace destination without accepting external redirects. */
export function portalReturn(value) {
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || /[\\\s\u0000-\u001f]/u.test(value)) return '/portal';
  try {
    const url = new URL(value, 'https://workspace.invalid');
    if (url.origin !== 'https://workspace.invalid' || !/^\/(?:portal|admin|buy|purchase)(?:\/|$)/.test(url.pathname)) return '/portal';
    return url.pathname + url.search;
  } catch { return '/portal'; }
}
