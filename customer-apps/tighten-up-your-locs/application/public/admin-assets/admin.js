// Standalone Locs owner interface. Private records stay in memory, never browser storage.
export const TIMEZONE = 'America/New_York';
const dateParts = new Intl.DateTimeFormat('en-CA', {
    timeZone: TIMEZONE, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
});
const statuses = { received: 'New request', responded: 'Responded', proposed: 'Awaiting client', confirmed: 'Confirmed', cancelled: 'Cancelled', completed: 'Completed' };
const serviceNames = { consultation: 'Consultation', establishment: 'Loc establishment', maintenance: 'Loc maintenance', retightening: 'Retightening', other: 'Other service' };
const dayBoundaries = new Map();
function partsAt(seconds) {
    return Object.fromEntries(dateParts.formatToParts(new Date(seconds * 1000)).filter(part => part.type !== 'literal').map(part => [part.type, part.value]));
}
export function dayKey(seconds) {
    if (!Number.isFinite(Number(seconds)) || Number(seconds) <= 0) return '';
    const p = partsAt(Number(seconds));
    return `${p.year}-${p.month}-${p.day}`;
}
export function localTimeValue(seconds) {
    if (!Number.isFinite(Number(seconds)) || Number(seconds) <= 0) return '';
    const p = partsAt(Number(seconds));
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
}
// Resolve a wall-clock time using the business timezone, never the owner's device zone.
// Refuse missing spring-forward times and ambiguous fall-back times instead of guessing.
export function zonedTimestamp(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(String(value));
    if (!match) throw new Error('Enter a complete date and time.');
    const [year, month, day, hour, minute] = match.slice(1).map(Number);
    const wall = Date.UTC(year, month - 1, day, hour, minute) / 1000;
    const check = new Date(wall * 1000);
    if (year < 2000 || check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== day || hour > 23 || minute > 59) {
        throw new Error('Enter a valid date and time after 1999.');
    }
    const offsets = new Set();
    for (const hours of [-36, -12, 0, 12, 36]) {
        const sample = wall + hours * 3600;
        const p = partsAt(sample);
        offsets.add(Date.UTC(+p.year, +p.month - 1, +p.day, +p.hour, +p.minute, +p.second) / 1000 - sample);
    }
    const candidates = [...offsets].map(offset => wall - offset).filter(seconds => localTimeValue(seconds) === value);
    if (candidates.length === 0) throw new Error('That time does not exist in Port St. Lucie because the clocks move forward. Choose a different time.');
    if (candidates.length !== 1) throw new Error('That time happens twice in Port St. Lucie when the clocks move back. Choose a time outside the repeated hour.');
    return candidates[0];
}
export function timeInterval(start, end) {
    const starts_at = zonedTimestamp(start), ends_at = zonedTimestamp(end);
    if (ends_at <= starts_at) throw new Error('The end time must be after the start time.');
    return { starts_at, ends_at };
}
export function actionNeedsTime(action, kind) {
    return ['propose', 'reschedule', 'create', 'update'].includes(action) || (action === 'confirm' && kind === 'request');
}
export function monthDates(month) {
    const [year, number] = month.split('-').map(Number);
    if (!year || number < 1 || number > 12) return [];
    const count = new Date(Date.UTC(year, number, 0)).getUTCDate();
    return Array.from({ length: count }, (_, i) => `${year}-${String(number).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`);
}
export function isOnDay(appointment, day) {
    if (!dayBoundaries.has(day)) {
        const following = new Date(Date.UTC(Number(day.slice(0, 4)), Number(day.slice(5, 7)) - 1, Number(day.slice(8, 10)) + 1));
        if (dayBoundaries.size > 400) dayBoundaries.clear();
        dayBoundaries.set(day, [zonedTimestamp(`${day}T00:00`), zonedTimestamp(`${following.toISOString().slice(0, 10)}T00:00`)]);
    }
    const [start, next] = dayBoundaries.get(day);
    return [[appointment.starts_at, appointment.ends_at], [appointment.pending_starts_at, appointment.pending_ends_at]]
        .some(([a, b]) => Number(a) > 0 && Number(b) > Number(a) && Number(a) < next && Number(b) > start);
}
function formatTime(seconds) {
    if (!Number(seconds)) return 'Not set';
    return new Intl.DateTimeFormat('en-US', { timeZone: TIMEZONE, month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' }).format(new Date(Number(seconds) * 1000));
}
function humanDay(day, options = { weekday: 'long', month: 'long', day: 'numeric' }) {
    return new Intl.DateTimeFormat('en-US', { timeZone: 'UTC', ...options }).format(new Date(`${day}T12:00:00Z`));
}
function service(value) { return serviceNames[value] || String(value || 'Service to discuss').replaceAll('_', ' '); }
export function errorMessage(error) {
    if (error.status === 401 || error.status === 419) return 'Your sign-in has expired. Sign in again before making changes.';
    if (error.status === 403) return 'This account is not allowed to manage the booking desk.';
    if (error.code?.includes('conflict') && /slot|overlap|time/.test(error.code)) return 'That time overlaps another confirmed appointment. Choose a different time. Your existing appointments have not changed.';
    if (error.status === 409) return 'This record changed or the time is no longer available. Close this window and refresh before trying again.';
    if (error.status === 422 && error.validation) return Object.values(error.validation).flat().join(' ');
    if (error.status === 429) return 'Too many requests were made. Wait a moment, then try again.';
    if (error.local) return error.message;
    return 'We could not verify the result. Retry the same action, or close this window and refresh to check the saved record. Do not assume the change failed.';
}
export function initAdmin(doc = document, win = window) {
    if (!doc.querySelector('#desk-main')) return;
    const $ = id => doc.getElementById(id);
    const state = { requests: [], appointments: [], openings: [], partial: {}, loaded: false, busy: false, month: dayKey(Date.now() / 1000).slice(0, 7), day: dayKey(Date.now() / 1000), section: 'requests', action: null, keys: new Map() };
    const csrf = doc.querySelector('meta[name="csrf-token"]')?.content;
    function node(tag, text, className) {
        const element = doc.createElement(tag);
        if (text !== undefined && text !== null) element.textContent = String(text);
        if (className) element.className = className;
        return element;
    }
    function button(text, handler, className = 'button button-quiet') {
        const element = node('button', text, className);
        element.type = 'button'; element.addEventListener('click', handler); return element;
    }
    function notice(id, message) { $(id).textContent = message; $(id).hidden = !message; }
    function setBusy(busy) {
        state.busy = busy;
        doc.querySelectorAll('#desk-content button, #refresh-desk, #action-dialog button, #action-dialog input, #action-dialog select').forEach(control => { control.disabled = busy || (!state.loaded && control.hasAttribute('data-mutation')); });
        $('action-form').setAttribute('aria-busy', String(busy));
    }
    async function api(path, payload) {
        const abort = new AbortController();
        const timeout = win.setTimeout(() => abort.abort(), 20000);
        try {
            const response = await win.fetch(`/admin/api/${path}`, {
                method: payload ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal: abort.signal,
                headers: { Accept: 'application/json', ...(payload ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } : {}) },
                ...(payload ? { body: JSON.stringify(payload) } : {}),
            });
            let data;
            try { data = await response.json(); } catch { throw Object.assign(new Error('Invalid response'), { status: response.status }); }
            if (!response.ok || data.ok === false) throw Object.assign(new Error(data.message || 'Request failed'), { status: response.status, code: data.code, validation: data.errors });
            return data;
        } finally { win.clearTimeout(timeout); }
    }
    function sessionLink(error, target) {
        if (![401, 403, 419].includes(error.status)) return;
        if (error.status !== 403) { const link = node('a', 'Sign in again'); link.href = '/admin/login'; $(target).append(link); }
        state.loaded = false; state.requests = []; state.appointments = []; state.openings = [];
        $('desk-content').hidden = true;
        $('request-list').replaceChildren(); $('appointment-list').replaceChildren(); $('opening-list').replaceChildren();
    }
    async function load({ afterSave = false } = {}) {
        if (state.busy) return;
        setBusy(true); notice('desk-error', '');
        $('desk-loading').textContent = 'Loading your saved booking information…'; $('desk-loading').hidden = false;
        try {
            const [requests, appointments, openings] = await Promise.all([api('requests'), api('appointments'), api('openings')]);
            if (!Array.isArray(requests.requests) || !Array.isArray(appointments.appointments) || !Array.isArray(openings.openings)) throw new Error('Incomplete data');
            state.requests = requests.requests; state.appointments = appointments.appointments; state.openings = openings.openings; state.loaded = true;
            state.partial = { requests: requests.has_more === true, appointments: appointments.has_more === true, openings: openings.has_more === true };
            notice('desk-partial', Object.values(state.partial).some(Boolean) ? 'Only part of your records could be loaded. Some dates may have additional appointments. The server still checks every reservation before a change is saved.' : '');
            render(); $('desk-content').hidden = false;
        } catch (error) {
            notice('desk-error', afterSave ? 'Your change was saved, but the updated list could not be loaded. Refresh to see the latest records.' : 'Your booking information could not be refreshed. Any information still shown may be out of date. Try Refresh before making changes.');
            if ([401, 403, 419].includes(error.status)) notice('desk-error', errorMessage(error));
            state.loaded = false; sessionLink(error, 'desk-error');
        } finally {
            $('desk-loading').hidden = true; setBusy(false);
            if (!state.loaded) doc.querySelectorAll('#desk-content [data-mutation]').forEach(control => { control.disabled = true; });
        }
    }
    function empty(title, text) { const box = node('div', null, 'empty-state'); box.append(node('h3', title), node('p', text)); return box; }
    function detail(list, label, value, linkType) {
        if (!value) return;
        const row = node('div'), content = node('dd'); row.append(node('dt', label), content);
        if (linkType) {
            const text = String(value), link = node('a', text, 'contact-link');
            link.href = linkType === 'email' ? `mailto:${encodeURIComponent(text)}` : `tel:${text.replace(/[^\d+]/g, '')}`;
            content.append(link);
        } else content.textContent = String(value);
        list.append(row);
    }
    function card(title, status) {
        const box = node('article', null, 'record-card'), heading = node('div', null, 'record-header');
        heading.append(node('h3', title), node('span', statuses[status] || status, `badge badge-${/^[a-z]+$/.test(status) ? status : 'unknown'}`));
        box.append(heading); return box;
    }
    function actionButton(label, action, row, kind = 'appointment', className = 'button button-quiet') {
        const control = button(label, () => openAction(action, row, kind), className); control.dataset.mutation = 'true'; return control;
    }
    function renderRequests() {
        const fragment = doc.createDocumentFragment();
        if (!state.requests.length) fragment.append(empty('A little room for the next client.', 'New appointment requests from your website will appear here. Nothing needs your attention right now.'));
        for (const request of [...state.requests].sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)))) {
            const box = card(request.name || 'Client request', request.status), list = node('dl', null, 'record-details');
            box.append(node('p', service(request.service_key), 'record-service'));
            detail(list, 'Email', request.email, 'email'); detail(list, 'Phone', request.phone, 'phone'); detail(list, 'Preferred time', request.requested_window); detail(list, 'Message', request.message);
            if (request.created_at) { const stamp = Date.parse(request.created_at); if (Number.isFinite(stamp)) detail(list, 'Received', formatTime(stamp / 1000)); }
            box.append(list);
            const appointment = state.appointments.find(item => item.request_id === request.id && !['cancelled', 'completed'].includes(item.status));
            if (appointment) {
                const row = node('div', null, 'record-actions');
                row.append(node('span', `${statuses[appointment.status] || 'Appointment'} · ${formatTime(appointment.starts_at)}`, 'field-note'));
                row.append(button('View in calendar', () => { state.day = dayKey(appointment.starts_at); state.month = state.day.slice(0, 7); showSection('calendar'); renderCalendar(); $('selected-day-title').scrollIntoView({ block: 'nearest' }); }));
                box.append(row);
            } else {
                const actions = node('div', null, 'record-actions');
                actions.append(actionButton('Confirm a time', 'confirm', request, 'request', 'button'), actionButton('Propose a time', 'propose', request, 'request'));
                box.append(actions);
            }
            fragment.append(box);
        }
        $('request-list').replaceChildren(fragment);
    }
    function appointmentCard(appointment) {
        const box = card(appointment.name || 'Client appointment', appointment.status), list = node('dl', null, 'record-details');
        box.append(node('p', service(appointment.service_key), 'record-service'));
        detail(list, appointment.status === 'proposed' ? 'Proposed' : 'Appointment', `${formatTime(appointment.starts_at)} – ${formatTime(appointment.ends_at)}`);
        if (appointment.pending_starts_at) detail(list, 'New proposal', `${formatTime(appointment.pending_starts_at)} – ${formatTime(appointment.pending_ends_at)}`);
        detail(list, 'Email', appointment.email, 'email');
        box.append(list);
        if (appointment.pending_starts_at) box.append(node('p', 'The original appointment stays reserved until your client accepts the new time.', 'field-note'));
        if (appointment.status === 'proposed') box.append(node('p', 'This proposed time is not reserved until it is accepted or confirmed.', 'field-note'));
        const notification = { queued: 'Notification queued — delivery is not yet confirmed.', sent: 'Notification sent by the mail service. Inbox delivery is not confirmed.', failed: 'Notification could not be sent. Contact your client directly; the saved appointment has not changed.' }[appointment.notification_status];
        if (notification) box.append(node('p', notification, 'field-note'));
        const actions = node('div', null, 'record-actions');
        if (appointment.status === 'proposed') actions.append(actionButton('Confirm agreed time', 'confirm', appointment, 'appointment', 'button'));
        if (appointment.status === 'confirmed') {
            actions.append(actionButton('Propose a new time', 'reschedule', appointment));
            if (Number(appointment.starts_at) <= Date.now() / 1000) actions.append(actionButton('Mark complete', 'complete', appointment));
        }
        if (['proposed', 'confirmed'].includes(appointment.status)) actions.append(actionButton('Cancel appointment', 'cancel', appointment));
        if (actions.childElementCount) box.append(actions);
        return box;
    }
    function renderCalendar() {
        $('calendar-month').textContent = humanDay(`${state.month}-01`, { month: 'long', year: 'numeric' });
        $('selected-day').value = state.day; $('selected-day-title').textContent = humanDay(state.day);
        const days = doc.createDocumentFragment(), offset = new Date(`${state.month}-01T12:00:00Z`).getUTCDay();
        for (let i = 0; i < offset; i++) days.append(node('span'));
        for (const day of monthDates(state.month)) {
            const matches = state.appointments.filter(item => isOnDay(item, day));
            const control = button(String(Number(day.slice(8))), () => { state.day = day; renderCalendar(); doc.querySelector(`[data-day="${day}"]`)?.focus(); }, '');
            control.dataset.day = day; control.dataset.today = String(day === dayKey(Date.now() / 1000)); control.dataset.hasBooking = String(matches.length > 0);
            control.setAttribute('aria-pressed', String(day === state.day));
            control.setAttribute('aria-label', `${humanDay(day)}${matches.length ? `, ${matches.length} appointment${matches.length === 1 ? '' : 's'} or proposal${matches.length === 1 ? '' : 's'}` : state.partial.appointments ? ', no appointments in loaded results' : ', no appointments'}`);
            days.append(control);
        }
        $('calendar-days').replaceChildren(days);
        const appointments = state.appointments.filter(item => isOnDay(item, state.day)).sort((a, b) => Number(a.starts_at) - Number(b.starts_at));
        $('appointment-list').replaceChildren(...(appointments.length ? appointments.map(appointmentCard) : [empty(state.partial.appointments ? 'No appointments in the loaded results for this day.' : 'No appointments on this day.', state.partial.appointments ? 'Additional appointments may exist outside the loaded results. Do not rely on this partial calendar to confirm availability.' : 'Choose another day, or review your client requests to arrange the next visit.')]));
    }
    function renderOpenings() {
        const elements = state.openings.map(opening => {
            const box = card(opening.label || 'Available opening', opening.published ? 'Published' : 'Draft');
            box.append(node('p', `${formatTime(opening.starts_at)} – ${formatTime(opening.ends_at)}`, 'record-service'));
            box.append(node('p', opening.published ? 'Visible on your public website as a requestable time.' : 'Private draft. Clients cannot see this opening.', 'field-note'));
            const actions = node('div', null, 'record-actions'); actions.append(actionButton('Edit opening', 'update', opening, 'opening'), actionButton('Remove opening', 'delete', opening, 'opening')); box.append(actions);
            return box;
        });
        $('opening-list').replaceChildren(...(elements.length ? elements : [empty('Let clients know when you have room.', 'Add an opening when you want to invite requests for a particular day or time. You choose when it becomes public.')]));
    }
    function render() { renderRequests(); renderCalendar(); renderOpenings(); }
    function showSection(section) {
        if (!['requests', 'calendar', 'openings'].includes(section)) return;
        state.section = section;
        for (const name of ['requests', 'calendar', 'openings']) $(`section-${name}`).hidden = name !== section;
        doc.querySelectorAll('[data-section]').forEach(control => { if (control.dataset.section === section) control.setAttribute('aria-current', 'page'); else control.removeAttribute('aria-current'); });
    }
    function field(label, name, type, value = '') {
        const wrapper = node('label', label, 'field'), input = node('input');
        input.name = name; input.type = type; input.value = value; input.required = true;
        if (type === 'datetime-local') input.step = '60';
        if (type === 'text') input.maxLength = 100;
        wrapper.append(input); return wrapper;
    }
    function openAction(action, row, kind) {
        if (state.busy || !state.loaded) return;
        state.action = { action, row, kind };
        const labels = { confirm: 'Confirm appointment', propose: 'Propose a time', reschedule: 'Propose a new time', cancel: 'Cancel appointment', complete: 'Mark appointment complete', create: 'Add an opening', update: 'Save opening', delete: 'Remove opening' };
        $('dialog-title').textContent = labels[action]; $('submit-action').textContent = labels[action];
        $('submit-action').className = ['cancel', 'delete'].includes(action) ? 'button button-danger' : 'button';
        const descriptions = {
            confirm: `Confirm the time agreed with ${row.name || 'your client'}${kind === 'appointment' ? `: ${formatTime(row.starts_at)} – ${formatTime(row.ends_at)}` : ''}. It will be reserved on your calendar, and a notification will be queued.`,
            propose: `Send ${row.name || 'your client'} a proposed time to accept. A proposal does not reserve the time.`,
            reschedule: 'Your client will be asked to accept the new time. The original appointment remains reserved until they accept.',
            cancel: `Cancel ${row.name || 'this client'}’s appointment on ${formatTime(row.starts_at)}? This releases the reserved time and queues a notification.`,
            complete: `Mark ${row.name || 'this client'}’s visit on ${formatTime(row.starts_at)} as completed only after the visit has taken place.`,
            create: 'Choose a time clients may request. Leave it as a private draft, or publish it on your website.',
            update: 'Update this requestable time. Editing an opening does not move or change any appointments.',
            delete: 'Remove this opening from your website and your openings list? Existing requests and appointments will not change.',
        };
        $('dialog-description').textContent = descriptions[action];
        const fields = node('div', null, 'form-fields');
        if (kind === 'opening' && action !== 'delete') fields.append(field('Opening label', 'label', 'text', row.label || ''));
        if (actionNeedsTime(action, kind)) {
            const pair = node('div', null, 'field-pair');
            pair.append(field('Start · Port St. Lucie time', 'start', 'datetime-local', localTimeValue(row.pending_starts_at || row.starts_at)), field('End · Port St. Lucie time', 'end', 'datetime-local', localTimeValue(row.pending_ends_at || row.ends_at)));
            fields.append(pair, node('p', 'All times use America/New_York, even if you are traveling or your device uses another timezone.', 'field-note'));
        }
        if (kind === 'opening' && action !== 'delete') {
            const label = node('label', null, 'check-field'), checkbox = node('input'); checkbox.type = 'checkbox'; checkbox.name = 'published'; checkbox.checked = row.published === true;
            label.append(checkbox, node('span', 'Publish this opening on my website')); fields.append(label);
        }
        $('dialog-fields').replaceChildren(fields); notice('dialog-error', ''); $('action-dialog').showModal();
        const first = $('dialog-fields').querySelector('input'); (first || $('cancel-action')).focus();
    }
    async function save(event) {
        event.preventDefault();
        if (state.busy || !state.loaded || !state.action) return;
        const { action, row, kind } = state.action, data = new FormData($('action-form'));
        let payload = { action };
        try {
            if (actionNeedsTime(action, kind)) payload = { ...payload, ...timeInterval(data.get('start'), data.get('end')) };
            if (kind === 'opening') {
                if (action !== 'create') Object.assign(payload, { id: row.id, expected_version: row.version });
                if (action !== 'delete') Object.assign(payload, { label: String(data.get('label') || '').trim(), published: data.get('published') === 'on' });
            } else Object.assign(payload, { [kind === 'request' ? 'request_id' : 'appointment_id']: row.id, expected_version: row.version });
        } catch (error) { error.local = true; notice('dialog-error', errorMessage(error)); return; }
        const path = kind === 'opening' ? 'openings' : 'appointments', signature = JSON.stringify([path, payload]);
        if (!state.keys.has(signature)) state.keys.set(signature, win.crypto.randomUUID());
        payload.idempotency_key = state.keys.get(signature);
        setBusy(true); notice('dialog-error', ''); notice('desk-notice', '');
        try {
            const result = await api(path, payload);
            if (result.ok !== true || (kind !== 'opening' && !result.appointment?.id) || (kind === 'opening' && action !== 'delete' && !result.opening?.id)) throw new Error('Unverified save');
            state.keys.delete(signature);
            $('action-dialog').close(); state.action = null;
            const messages = { confirm: 'Appointment confirmed and saved.', propose: 'Proposal saved. This time is not reserved.', reschedule: 'New time proposed and saved. The original appointment stays reserved until accepted.', cancel: 'Appointment cancelled and saved.', complete: 'Appointment marked complete and saved.', create: 'Opening saved.', update: 'Opening updated and saved.', delete: 'Opening removed.' };
            const notification = result.notification_status === 'queued' ? ' Notification queued; delivery is not yet confirmed.' : result.notification_status === 'failed' ? ' The notification could not be sent. Contact your client directly.' : '';
            notice('desk-notice', messages[action] + notification);
            setBusy(false); await load({ afterSave: true });
            $('desk-notice').tabIndex = -1; $('desk-notice').focus();
        } catch (error) { notice('dialog-error', errorMessage(error)); sessionLink(error, 'dialog-error'); }
        finally { setBusy(false); }
    }
    doc.querySelectorAll('[data-section]').forEach(control => control.addEventListener('click', () => showSection(control.dataset.section)));
    $('refresh-desk').addEventListener('click', () => load());
    $('new-opening').dataset.mutation = 'true'; $('new-opening').addEventListener('click', () => openAction('create', {}, 'opening'));
    $('action-form').addEventListener('submit', save);
    for (const id of ['close-dialog', 'cancel-action']) $(id).addEventListener('click', () => { if (!state.busy) { $('action-dialog').close(); state.action = null; } });
    $('action-dialog').addEventListener('cancel', event => { if (state.busy) event.preventDefault(); else state.action = null; });
    function moveMonth(amount) {
        const [year, month] = state.month.split('-').map(Number), date = new Date(Date.UTC(year, month - 1 + amount, 1));
        if (date.getUTCFullYear() < 2000 || date.getUTCFullYear() > 2100) return;
        state.month = date.toISOString().slice(0, 7); state.day = `${state.month}-01`; renderCalendar();
    }
    $('previous-month').addEventListener('click', () => moveMonth(-1)); $('next-month').addEventListener('click', () => moveMonth(1));
    $('today').addEventListener('click', () => { state.day = dayKey(Date.now() / 1000); state.month = state.day.slice(0, 7); renderCalendar(); });
    $('selected-day').min = '2000-01-01'; $('selected-day').max = '2100-12-31';
    $('selected-day').addEventListener('change', event => { if (!event.target.checkValidity() || !event.target.value) return; state.day = event.target.value; state.month = state.day.slice(0, 7); renderCalendar(); });
    // Leaving the page never persists private request details, records, or action payloads.
    win.addEventListener('pagehide', () => {
        state.keys.clear(); state.requests = []; state.appointments = []; state.openings = []; state.loaded = false; state.action = null;
        $('request-list').replaceChildren(); $('appointment-list').replaceChildren(); $('opening-list').replaceChildren(); $('desk-content').hidden = true;
        $('action-dialog').close(); $('dialog-fields').replaceChildren(); $('dialog-description').textContent = '';
    });
    win.addEventListener('pageshow', event => { if (event.persisted) load(); });
    load();
}
if (typeof document !== 'undefined' && typeof window !== 'undefined') initAdmin();
