import { useEffect, useState } from 'react';
import { getBookingRequests, updateBookingRequest } from '../../api/customer.js';
import { Panel, Empty } from './PortalShared.jsx';

const LABELS = { new: 'New', reviewing: 'Reviewing', responded: 'Marked responded', closed: 'Closed', declined: 'Declined' };
const target = { minHeight: 44, padding: '10px 14px' };

export default function PortalBookingRequestsView({ sites = [] }) {
  const [selected, setSelected] = useState('');
  const site = sites.find(item => item.site_key === selected)?.site_key || sites[0]?.site_key || '';
  const [snapshot, setSnapshot] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [refresh, setRefresh] = useState(0);
  const [notice, setNotice] = useState('');
  useEffect(() => {
    let current = true;
    setSnapshot(null); setError(''); setNotice('');
    if (site) getBookingRequests(site).then(data => {
      if (data.ok !== true || data.site_key !== site || !Array.isArray(data.requests)) throw new Error('invalid_snapshot');
      if (current) setSnapshot(data);
    }).catch(() => { if (current) setError('Requests could not be loaded. No request information is shown.'); });
    return () => { current = false; };
  }, [site, refresh]);
  async function changeStatus(item, status) {
    setBusy(true); setError(''); setNotice('');
    try {
      const result = await updateBookingRequest(site, item.id, status);
      if (result.ok !== true || result.status !== status) throw new Error('unconfirmed');
      setSnapshot(old => old?.site_key === site ? { ...old, requests: old.requests.map(row => row.id === item.id ? { ...row, status } : row) } : old);
      setNotice('Status saved. No reply was sent and no appointment was created.');
    } catch {
      setError('The status change could not be confirmed. Refresh before trying again.');
      setSnapshot(null);
    } finally { setBusy(false); }
  }
  if (!site) return <Panel title="Website requests"><Empty>No website request inbox is linked to this workspace yet.</Empty></Panel>;
  return <div style={{ minWidth: 0, overflowX: 'clip' }}>
    <Panel eyebrow="Your business inbox" title="Review a website request">
      <p>These are requests for your review, not confirmed appointments. Open your own email or phone app to respond; this inbox does not send replies.</p>
      <label>Website
        <select style={{ ...target, maxWidth: '100%', display: 'block' }} disabled={busy} value={site} onChange={e => setSelected(e.target.value)}>
          {sites.map(item => <option key={item.site_key} value={item.site_key}>{item.business_name || 'Your website'}</option>)}
        </select>
      </label>
      <button type="button" style={target} disabled={busy} onClick={() => setRefresh(value => value + 1)}>Refresh requests</button>
      {notice && <p role="status">{notice}</p>}
      {error && <p role="alert">{error}</p>}
      {!snapshot && !error && <p role="status">Loading your requests…</p>}
      {snapshot?.site_key === site && snapshot.requests.length === 0 && <Empty>No requests have been received for this website.</Empty>}
    </Panel>
    {snapshot?.site_key === site && snapshot.requests.map(item => <Panel key={item.id} title={item.customer_name || 'Website visitor'}>
      <p><strong>{LABELS[item.status] || 'Status unavailable'}</strong> · {item.created ? new Date(Number(item.created) * 1000).toLocaleString() : 'Time unavailable'}</p>
      <dl style={{ overflowWrap: 'anywhere' }}>
        <dt>Service requested</dt><dd>{item.service_key}</dd>
        <dt>Preferred time</dt><dd>{item.requested_window}</dd>
        <dt>Message</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{item.message || 'No message supplied.'}</dd>
        <dt>Email</dt><dd>{item.email}</dd>
        <dt>Phone</dt><dd>{item.phone || 'Not supplied'}</dd>
      </dl>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>
        {/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(item.email || '') && <a style={target} href={`mailto:${encodeURIComponent(item.email)}`}>Open email app</a>}
        {/^[+\d\s().-]{5,40}$/.test(item.phone || '') && <a style={target} href={`tel:${item.phone.replace(/[^+\d]/g, '')}`}>Open phone app</a>}
      </div>
      <p>Opening an app does not record a sent reply. Update the status only after taking the corresponding action yourself.</p>
      <label>Request status
        <select style={{ ...target, maxWidth: '100%', display: 'block' }} disabled={busy} value={item.status} onChange={e => changeStatus(item, e.target.value)}>
          {Object.entries(LABELS).map(([value, label]) => <option value={value} key={value}>{label}</option>)}
        </select>
      </label>
    </Panel>)}
  </div>;
}
