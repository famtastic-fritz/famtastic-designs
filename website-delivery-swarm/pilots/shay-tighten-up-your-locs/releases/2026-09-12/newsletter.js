export function newsletterPayload(form) {
  return { email: form.elements.email.value.trim(), consent: form.elements.consent.checked === true, website: form.elements.website.value };
}
export function isPendingSubscription(response) { return response?.status === 'pending'; }
export function initNewsletter(doc = document, win = window) {
  const form = doc.getElementById('newsletter-form');
  if (!form || form.dataset.ready) return;
  form.dataset.ready = 'true';
  const button = doc.getElementById('newsletter-submit'), status = doc.getElementById('newsletter-status');
  let sending = false; button.disabled = false;
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (sending || !form.reportValidity()) return;
    const payload = newsletterPayload(form);
    if (!payload.consent) return;
    sending = true; button.disabled = true; status.dataset.state = 'pending'; status.textContent = 'Sending your confirmation request…';
    try {
      const response = await win.fetch('/api/newsletter/signup', {
        method: 'POST', credentials: 'omit', redirect: 'error', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload), signal: AbortSignal.timeout(15000),
      });
      if (!response.ok) throw Object.assign(new Error('Subscription unavailable'), { status: response.status });
      if (!isPendingSubscription(await response.json())) throw new Error('Unverified response');
      status.dataset.state = 'success';
      status.textContent = 'One last step: check your email and confirm you want The Locs Letter. You are not subscribed until you confirm.';
      form.reset();
    } catch (error) {
      status.dataset.state = 'error';
      status.textContent = error.status === 429 ? 'A few too many requests. Please wait a moment before trying again.'
        : error.status === 422 ? 'Please check your email address and newsletter consent, then try again.'
        : error.status === 503 ? 'The Locs Letter signup is temporarily unavailable. Please try again later.'
        : 'We could not verify your signup request. Check your inbox for a confirmation email before trying again. You are not subscribed without confirming.';
    } finally { sending = false; button.disabled = false; }
  });
}
