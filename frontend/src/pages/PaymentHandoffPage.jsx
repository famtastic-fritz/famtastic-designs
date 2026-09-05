import { useEffect, useState } from 'react';
import { useParams } from 'react-router';

const prefix = import.meta.env.DEV ? '' : '/web';
export default function PaymentHandoffPage() {
  const { organization, siteKey } = useParams(); const [state, setState] = useState({ loading: true });
  useEffect(() => { fetch(`${prefix}/api/payment-handoff/${encodeURIComponent(organization)}/${encodeURIComponent(siteKey)}`).then(async (r) => r.ok ? r.json() : Promise.reject()).then((data) => { setState({ handoff: data.payment_handoff }); fetch(`${prefix}/api/payment-handoff/${encodeURIComponent(organization)}/${encodeURIComponent(siteKey)}/events`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event: 'viewed', surface: 'starter' }) }); }).catch(() => setState({ unavailable: true })); }, [organization, siteKey]);
  if (state.loading) return <main className="purchase-shell"><p>Loading payment options…</p></main>;
  if (state.unavailable) return <main className="purchase-shell"><h1>Payment option unavailable</h1><p>This business has not enabled a payment handoff here.</p></main>;
  const h = state.handoff;
  return <main className="purchase-shell"><span>Client-owned payment handoff</span><h1>{h.label}</h1>{h.qr_image_url && <img src={h.qr_image_url} alt="Payment QR code" />}<p>{h.instructions}</p><a className="btn btn--lime" href={h.destination_url} target="_blank" rel="noreferrer" onClick={() => fetch(`${prefix}/api/payment-handoff/${encodeURIComponent(organization)}/${encodeURIComponent(siteKey)}/events`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event: 'opened', surface: 'starter' }) })}>{h.label}</a><p><small>Opening this link is not a payment confirmation, order, or service reservation.</small></p></main>;
}
