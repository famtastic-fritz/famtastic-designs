import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { CREDIT_NAME, CREDIT_HREF, creditRowStyle, creditLinkStyle, creditImageStyle, creditCss } from '../frontend/src/lib/creatorCredit.js';

export const LOGO_SHA256 = 'ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950';
export const sha256 = bytes => createHash('sha256').update(bytes).digest('hex');
export function approvedLogo() {
  const bytes = readFileSync(new URL('../frontend/public/brand/famtastic-designs-logo-v1.png', import.meta.url));
  if (sha256(bytes) !== LOGO_SHA256) throw new Error('Creator credit requires the exact owner PNG');
  return bytes;
}
const css = style => Object.entries(style).map(([key, value]) => `${key.replace(/[A-Z]/g, c => `-${c.toLowerCase()}`)}:${value}`).join(';');
export function creatorCreditHtml({ embedded = false } = {}) {
  const src = embedded ? `data:image/png;base64,${approvedLogo().toString('base64')}` : 'https://famtasticdesigns.com/brand/famtastic-designs-logo-v1.png';
  return `<style>${creditCss}</style><div data-famtastic-creator-credit="v1" style="${css(creditRowStyle)}"><a href="${CREDIT_HREF.replaceAll('&', '&amp;')}" aria-label="${CREDIT_NAME}" referrerpolicy="no-referrer" style="${css(creditLinkStyle)}"><img src="${src}" alt="${CREDIT_NAME}" width="2172" height="724" style="${css(creditImageStyle)}"></a></div>`;
}
export function addCreatorCredit(html, options) {
  if (html.includes('data-famtastic-creator-credit="v1"')) return html;
  const credit = creatorCreditHtml(options);
  if (/<\/body\s*>/i.test(html)) return html.replace(/<\/body\s*>/i, `${credit}\n</body>`);
  if (/<\/html\s*>/i.test(html)) return html.replace(/<\/html\s*>/i, `${credit}\n</html>`);
  return `${html}\n${credit}`;
}
