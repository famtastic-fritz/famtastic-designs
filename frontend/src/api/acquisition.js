const PREFIX = import.meta.env?.DEV ? '' : '/web';
export const validSampleToken = (value) => typeof value === 'string' && /^[a-f0-9]{64}$/.test(value);
export function privatePreviewPath(value, token) {
  if (!validSampleToken(token) || typeof value !== 'string') return null;
  const expected = `${PREFIX}/api/acquisition/samples/${token}/preview/`;
  // Drupal publishes /web paths; Vite proxies the same route without /web.
  const normalized = import.meta.env?.DEV ? value.replace(/^\/web(?=\/api\/)/, '') : value;
  return normalized.startsWith(expected) && /^[a-z0-9_-]+$/.test(normalized.slice(expected.length)) ? normalized : null;
}
async function request(path, options = {}) {
  const headers = { Accept: 'application/json', ...(options.body ? {'Content-Type':'application/json'} : {}) };
  if (options.csrf) {
    const response = await fetch(`${PREFIX}/session/token`, {credentials:'same-origin', referrerPolicy:'no-referrer'});
    if (!response.ok) throw new Error('Please sign in again.');
    headers['X-CSRF-Token'] = await response.text();
  }
  const response = await fetch(`${PREFIX}/api/acquisition/${path}`, {...options, headers, credentials:'same-origin', referrerPolicy:'no-referrer', cache:'no-store'});
  const result = await response.json().catch(()=>({}));
  if (!response.ok) throw new Error(result.message || 'This private invitation is unavailable. Please ask FAMtastic Designs for help.');
  return result;
}
export const getAcquisitionSample = (token, signal) => request(`samples/${encodeURIComponent(token)}`, {signal});
export const saveSamplePreference = (token, recipe_id) => request(`samples/${encodeURIComponent(token)}/preference`, {method:'POST', body:JSON.stringify({recipe_id})});
export const claimAcquisitionSample = (token) => request(`samples/${encodeURIComponent(token)}/claim`, {method:'POST',csrf:true,body:'{}'});
export const getAcquisitionReport = (campaign, signal) => request(`report/${encodeURIComponent(campaign)}`, {signal});
export function continuationReturn(continuation) {
  return continuation?.kind === 'acquisition_sample' && continuation?.return_path === '/portal?start=website&section=projects' ? continuation.return_path : null;
}
