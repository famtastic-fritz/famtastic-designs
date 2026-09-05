(() => {
  const body = document.body;
  const form = document.getElementById('contact-form');
  const status = document.getElementById('status');
  const availabilityApi = body.dataset.availabilityApi;
  const availabilityTitle = document.getElementById('availability-title');
  const availabilityCopy = document.getElementById('availability-copy');
  const availabilityList = document.getElementById('availability-list');
  const formatWindow = (window) => new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(window.starts_at * 1000));
  const requestWindow = (window) => {
    const field = form.elements.requested_window;
    field.value = `${window.label} · ${formatWindow(window)}`;
    document.getElementById('contact').scrollIntoView({ behavior: 'smooth' });
    field.focus({ preventScroll: true });
  };
  const renderAvailability = (windows) => {
    if (!windows.length) return;
    availabilityTitle.textContent = 'Fresh openings from Shay.';
    availabilityCopy.textContent = 'Choose a window to include it in your request. Shay will review and confirm the next step personally.';
    availabilityList.replaceChildren(...windows.map((window) => {
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'opening';
      const label = document.createElement('span'); label.textContent = window.label;
      const time = document.createElement('b'); time.textContent = `${formatWindow(window)} →`;
      button.append(label, time); button.addEventListener('click', () => requestWindow(window));
      return button;
    }));
  };
  if (availabilityApi) fetch(availabilityApi, { headers: { Accept: 'application/json' } })
    .then((response) => response.ok ? response.json() : Promise.reject())
    .then((payload) => renderAvailability(Array.isArray(payload.windows) ? payload.windows : []))
    .catch(() => { availabilityCopy.textContent = 'Availability is temporarily unavailable. Send Shay the days that work for you and she can reply directly.'; });
  document.querySelectorAll('[data-service-request]').forEach((link) => link.addEventListener('click', () => { form.elements.service_key.value = link.dataset.serviceRequest; }));
  form.addEventListener('submit', async (event) => {
    event.preventDefault(); const api = body.dataset.bookingApi;
    if (!api) { status.textContent = 'Preview mode: Shay’s secure request route is not enabled yet. No message has been sent.'; return; }
    const button = form.querySelector('button'); button.disabled = true; status.textContent = 'Sending…';
    try {
      const response = await fetch(api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(form))) });
      const payload = await response.json(); if (!response.ok) throw new Error(payload.error || 'unavailable');
      status.textContent = 'Request received. Shay will review it before anything is confirmed.'; form.reset();
    } catch { status.textContent = 'We could not save that request. Please try again later.'; }
    finally { button.disabled = false; }
  });
})();
