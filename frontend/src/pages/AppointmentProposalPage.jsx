import { useEffect, useState } from 'react';
import { useParams, useSearchParams } from 'react-router';
import { getBookingProposal, respondBookingProposal } from '../api/customer.js';

export default function AppointmentProposalPage() {
  const { appointment } = useParams();
  const [search] = useSearchParams();
  const token = search.get('token') || '';
  const [state, setState] = useState({ loading: true });
  useEffect(() => {
    let current = true;
    getBookingProposal(appointment, token).then(result => {
      if (current) setState({ proposal: result.appointment });
    }).catch(() => { if (current) setState({ unavailable: true }); });
    return () => { current = false; };
  }, [appointment, token]);

  async function decide(decision) {
    setState(old => ({ ...old, busy: true, error: '' }));
    try {
      const result = await respondBookingProposal(appointment, token, decision);
      setState({ completed: decision, appointment: result.appointment });
    } catch {
      setState(old => ({ ...old, busy: false, error: 'That response could not be saved. The time may no longer be available.' }));
    }
  }

  if (state.loading) return <main className="purchase-shell"><p>Loading Shay’s proposed time…</p></main>;
  if (state.unavailable) return <main className="purchase-shell"><h1>This appointment link is unavailable.</h1><p>It may have expired or already been replaced. Contact Shay for the current time.</p></main>;
  if (state.completed) return <main className="purchase-shell"><span>Response saved</span><h1>{state.completed === 'accept' ? 'Your appointment is confirmed.' : 'The proposed time was declined.'}</h1><p>{state.completed === 'accept' ? 'Shay now has your confirmed time in her Owner Desk.' : 'Contact Shay to choose another time.'}</p></main>;
  const proposal = state.proposal;
  const start = new Date(Number(proposal.proposed_starts_at) * 1000);
  const end = new Date(Number(proposal.proposed_ends_at) * 1000);
  return <main className="purchase-shell">
    <span>Tighten Up Your Locs</span>
    <h1>Shay proposed a time.</h1>
    <p><strong>{start.toLocaleString([], { dateStyle: 'full', timeStyle: 'short', timeZone: proposal.timezone })}</strong><br />until {end.toLocaleTimeString([], { timeStyle: 'short', timeZone: proposal.timezone })}</p>
    <p>Choose one response. This does not charge you or connect an outside calendar.</p>
    <div style={{ display: 'grid', gap: 12, maxWidth: 420 }}>
      <button className="btn btn--lime" disabled={state.busy} onClick={() => decide('accept')}>Accept this time</button>
      <button className="btn" disabled={state.busy} onClick={() => decide('decline')}>I need another time</button>
    </div>
    {state.error && <p role="alert">{state.error}</p>}
  </main>;
}
