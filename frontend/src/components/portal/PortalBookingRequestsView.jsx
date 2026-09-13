import { useEffect, useMemo, useState } from 'react';
import {
  commandBookingAppointment,
  createBookingAvailability,
  getBookingAppointments,
  getBookingAvailability,
  getBookingRequests,
  updateBookingAvailability,
} from '../../api/customer.js';
import { Empty, Panel } from './PortalShared.jsx';

const target = { minHeight: 44, padding: '10px 14px' };
const tabs = [['today', 'Today'], ['openings', 'Openings'], ['requests', 'Requests']];
const statusLabels = { confirmed: 'Confirmed', proposal_pending: 'Waiting for client', cancelled: 'Cancelled', completed: 'Completed', declined: 'Declined' };

function unix(value) {
  const time = new Date(value).getTime();
  return Number.isFinite(time) ? Math.floor(time / 1000) : 0;
}

function key(action, id) {
  return `owner:${action}:${id}:${Date.now()}:${globalThis.crypto?.randomUUID?.() || Math.random().toString(36).slice(2)}`;
}

function AppointmentComposer({ request, site, mode = 'confirm', onSaved, disabled }) {
  const [start, setStart] = useState('');
  const [end, setEnd] = useState('');
  const [error, setError] = useState('');
  async function submit(event) {
    event.preventDefault(); setError('');
    try {
      const result = await commandBookingAppointment(site, {
        action: mode,
        request_id: request.id,
        starts_at: unix(start),
        ends_at: unix(end),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/New_York',
        idempotency_key: key(mode, request.id),
      });
      if (result.ok !== true || !result.appointment) throw new Error('unconfirmed');
      onSaved(`${mode === 'confirm' ? 'Appointment confirmed' : 'Alternate time sent'} and saved.`);
    } catch (cause) {
      setError(cause?.code === 'appointment_slot_conflict' ? 'That time overlaps another appointment.' : 'The appointment was not saved. Check the times and try again.');
    }
  }
  return <form onSubmit={submit} style={{ display: 'grid', gap: 12, marginTop: 16 }}>
    <strong>{mode === 'confirm' ? 'Confirm this request' : 'Propose another time'}</strong>
    <label>Start<input style={{ ...target, width: '100%' }} type="datetime-local" required value={start} onChange={event => setStart(event.target.value)} /></label>
    <label>End<input style={{ ...target, width: '100%' }} type="datetime-local" required value={end} onChange={event => setEnd(event.target.value)} /></label>
    <button style={target} disabled={disabled} type="submit">{mode === 'confirm' ? 'Confirm appointment' : 'Send proposed time'}</button>
    {error && <p role="alert">{error}</p>}
  </form>;
}

function RescheduleComposer({ appointment, site, onSaved, disabled }) {
  const [start, setStart] = useState('');
  const [end, setEnd] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  async function submit(event) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      const result = await commandBookingAppointment(site, {
        action: 'reschedule',
        appointment_id: appointment.id,
        expected_revision: appointment.revision,
        starts_at: unix(start),
        ends_at: unix(end),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/New_York',
        idempotency_key: key('reschedule', appointment.id),
      });
      if (result.ok !== true || !result.appointment) throw new Error('unconfirmed');
      onSaved('The new time was saved and sent to the client.');
    } catch (cause) {
      setError(cause?.code === 'appointment_slot_conflict' ? 'That time overlaps another appointment.' : 'The new time was not saved. Refresh and try again.');
    } finally { setSaving(false); }
  }
  return <form onSubmit={submit} style={{ display: 'grid', gap: 12, marginTop: 12 }}>
    <label>New start<input style={{ ...target, width: '100%' }} type="datetime-local" required value={start} onChange={event => setStart(event.target.value)} /></label>
    <label>New end<input style={{ ...target, width: '100%' }} type="datetime-local" required value={end} onChange={event => setEnd(event.target.value)} /></label>
    <button style={target} disabled={disabled || saving} type="submit">Send new time</button>
    {error && <p role="alert">{error}</p>}
  </form>;
}

export default function PortalBookingRequestsView({ sites = [] }) {
  const [selected, setSelected] = useState('');
  const site = sites.find(item => item.site_key === selected)?.site_key || sites[0]?.site_key || '';
  const [tab, setTab] = useState('today');
  const [requests, setRequests] = useState(null);
  const [appointments, setAppointments] = useState(null);
  const [availability, setAvailability] = useState(null);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [refresh, setRefresh] = useState(0);

  useEffect(() => {
    let current = true;
    setRequests(null); setAppointments(null); setAvailability(null); setError('');
    if (!site) return undefined;
    Promise.all([getBookingRequests(site), getBookingAppointments(site), getBookingAvailability(site)])
      .then(([requestData, appointmentData, availabilityData]) => {
        if (!current || requestData.site_key !== site || appointmentData.site_key !== site || availabilityData.site_key !== site) return;
        setRequests(requestData.requests || []); setAppointments(appointmentData.appointments || []); setAvailability(availabilityData.windows || []);
      })
      .catch(() => { if (current) setError('The Owner Desk could not be loaded. No private customer information is shown.'); });
    return () => { current = false; };
  }, [site, refresh]);

  const active = useMemo(() => (appointments || []).filter(item => !['cancelled', 'completed', 'declined'].includes(item.status)), [appointments]);
  function saved(message) { setNotice(message); setRefresh(value => value + 1); }

  async function command(item, action) {
    setBusy(true); setError(''); setNotice('');
    try {
      const result = await commandBookingAppointment(site, {
        action, appointment_id: item.id, expected_revision: item.revision, idempotency_key: key(action, item.id),
      });
      if (result.ok !== true) throw new Error('unconfirmed');
      saved(`${action === 'cancel' ? 'Cancellation' : 'Completion'} saved.`);
    } catch { setError('That change was not saved. Refresh before trying again.'); }
    finally { setBusy(false); }
  }

  async function addOpening(event) {
    event.preventDefault(); setBusy(true); setError('');
    const form = new FormData(event.currentTarget);
    try {
      await createBookingAvailability(site, {
        label: form.get('label'), starts_at: unix(form.get('start')), ends_at: unix(form.get('end')), status: form.get('published') ? 'published' : 'draft', service_keys: [],
      });
      event.currentTarget.reset(); saved('Opening saved.');
    } catch { setError('The opening was not saved. Check the times and try again.'); }
    finally { setBusy(false); }
  }

  async function toggleOpening(item) {
    setBusy(true); setError('');
    try {
      await updateBookingAvailability(site, item.id, { status: item.status === 'published' ? 'hidden' : 'published' });
      saved(item.status === 'published' ? 'Opening hidden.' : 'Opening published.');
    } catch { setError('The opening was not changed. Refresh and try again.'); }
    finally { setBusy(false); }
  }

  if (!site) return <Panel title="Owner Desk"><Empty>No owner-operated website is linked to this workspace yet.</Empty></Panel>;
  return <div style={{ minWidth: 0, overflowX: 'clip' }}>
    <Panel eyebrow="Mobile business command center" title="Owner Desk">
      <p>Publish openings, review requests, and manage real appointments for your website.</p>
      <label>Website<select style={{ ...target, width: '100%', display: 'block' }} value={site} disabled={busy} onChange={event => setSelected(event.target.value)}>
        {sites.map(item => <option key={item.site_key} value={item.site_key}>{item.business_name || item.site_key}</option>)}
      </select></label>
      <div role="tablist" aria-label="Owner Desk sections" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 8, marginTop: 18 }}>
        {tabs.map(([id, label]) => <button type="button" key={id} role="tab" aria-selected={tab === id} style={target} onClick={() => setTab(id)}>{label}</button>)}
      </div>
      {notice && <p role="status">{notice}</p>}{error && <p role="alert">{error}</p>}
    </Panel>

    {tab === 'today' && <>
      <Panel eyebrow="Today and ahead" title="Appointments">
        {!appointments && !error && <p role="status">Loading appointments…</p>}
        {appointments && active.length === 0 && <Empty>No confirmed or proposed appointments yet.</Empty>}
      </Panel>
      {active.map(item => <Panel key={item.id} title={item.customer?.name || 'Client appointment'}>
        <p><strong>{statusLabels[item.status] || item.status}</strong></p>
        <p>{item.starts_at ? new Date(item.starts_at * 1000).toLocaleString() : `Proposed: ${new Date(item.proposed_starts_at * 1000).toLocaleString()}`}</p>
        <p>{item.customer?.email}</p>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
          {item.status === 'confirmed' && <button type="button" style={target} disabled={busy} onClick={() => command(item, 'complete')}>Mark complete</button>}
          <button type="button" style={target} disabled={busy} onClick={() => command(item, 'cancel')}>Cancel appointment</button>
        </div>
        <details style={{ marginTop: 12 }}>
          <summary style={{ ...target, cursor: 'pointer' }}>Propose a new time</summary>
          <RescheduleComposer appointment={item} site={site} onSaved={saved} disabled={busy} />
        </details>
      </Panel>)}
    </>}

    {tab === 'openings' && <>
      <Panel eyebrow="Your availability" title="Publish an opening">
        <form onSubmit={addOpening} style={{ display: 'grid', gap: 12 }}>
          <label>Client-facing label<input name="label" required maxLength="100" style={{ ...target, width: '100%' }} placeholder="Saturday care opening" /></label>
          <label>Start<input name="start" type="datetime-local" required style={{ ...target, width: '100%' }} /></label>
          <label>End<input name="end" type="datetime-local" required style={{ ...target, width: '100%' }} /></label>
          <label><input name="published" type="checkbox" /> Publish on my website now</label>
          <button style={target} disabled={busy}>Save opening</button>
        </form>
      </Panel>
      {(availability || []).map(item => <Panel key={item.id} title={item.label}>
        <p>{new Date(item.starts_at * 1000).toLocaleString()} · {item.status}</p>
        <button type="button" style={target} disabled={busy} onClick={() => toggleOpening(item)}>{item.status === 'published' ? 'Hide opening' : 'Publish opening'}</button>
      </Panel>)}
    </>}

    {tab === 'requests' && <>
      <Panel eyebrow="Client requests" title="Choose the next step">
        {!requests && !error && <p role="status">Loading requests…</p>}
        {requests?.length === 0 && <Empty>No requests have been received.</Empty>}
      </Panel>
      {(requests || []).map(item => {
        const existing = (appointments || []).find(appointment => appointment.request_id === item.id && !['cancelled', 'declined'].includes(appointment.status));
        return <Panel key={item.id} title={item.customer_name || 'Website visitor'}>
          <p><strong>{item.service_key}</strong> · {item.requested_window}</p>
          <p style={{ whiteSpace: 'pre-wrap' }}>{item.message || 'No message supplied.'}</p>
          <p>{item.email}<br />{item.phone || ''}</p>
          {existing ? <p>This request has an active {statusLabels[existing.status]?.toLowerCase() || 'appointment'} record.</p> : <details>
            <summary style={{ ...target, cursor: 'pointer' }}>Confirm or propose a time</summary>
            <AppointmentComposer request={item} site={site} onSaved={saved} disabled={busy} />
            <AppointmentComposer request={item} site={site} mode="propose" onSaved={saved} disabled={busy} />
          </details>}
        </Panel>;
      })}
    </>}
  </div>;
}
