import { useEffect, useState } from 'react';
import { getPaymentHandoff, savePaymentHandoff } from '../../api/customer.js';
import { Panel } from './PortalShared.jsx';

export default function PaymentHandoffSettings({ organization }) {
  const [handoff, setHandoff] = useState({ mode: 'disabled', destination_url: '', qr_image_url: '', label: 'Pay this business', instructions: '' });
  const [status, setStatus] = useState('Loading payment handoff…');
  useEffect(() => {
    if (!organization?.public_id) return;
    getPaymentHandoff(organization.public_id).then((data) => {
      setHandoff(data.payment_handoff || handoff); setStatus('');
    }).catch(() => setStatus('Payment handoff settings are unavailable for this account.'));
  // Load once for the currently selected organization.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [organization?.public_id]);
  async function save(event) {
    event.preventDefault(); setStatus('Saving…');
    try { const data = await savePaymentHandoff({ ...handoff, organization: organization.public_id }); setHandoff(data.payment_handoff || handoff); setStatus('Saved. This is an external payment handoff, not payment confirmation.'); }
    catch (error) { setStatus(error.message || 'Could not save the payment handoff.'); }
  }
  return <Panel eyebrow="Client-owned payments" title="Configure a payment handoff" className="offer">
    <p>Show your own Cash App, existing QR image, or HTTPS payment link on an eligible branded site. FAMtastic does not collect, verify, or settle these payments.</p>
    <form onSubmit={save} className="portal-stack">
      <label>Method<select value={handoff.mode} onChange={(e) => setHandoff({ ...handoff, mode: e.target.value })}><option value="disabled">Disabled</option><option value="cash_app">Cash App</option><option value="payment_link">Payment link</option><option value="qr">Existing QR image</option></select></label>
      {handoff.mode !== 'disabled' && <label>Destination URL{handoff.mode === 'qr' ? ' (optional accessible fallback)' : ''}<input required={handoff.mode !== 'qr'} value={handoff.destination_url || ''} onChange={(e) => setHandoff({ ...handoff, destination_url: e.target.value })} placeholder="https://… or $cashtag" /></label>}
      {handoff.mode === 'qr' && <label>QR image URL<input required value={handoff.qr_image_url || ''} onChange={(e) => setHandoff({ ...handoff, qr_image_url: e.target.value })} placeholder="https://…" /></label>}
      <label>Button label<input value={handoff.label || ''} onChange={(e) => setHandoff({ ...handoff, label: e.target.value })} /></label>
      <label>Instructions<textarea value={handoff.instructions || ''} onChange={(e) => setHandoff({ ...handoff, instructions: e.target.value })} /></label>
      <button type="submit">Save payment handoff</button><small role="status">{status}</small>
    </form>
  </Panel>;
}
