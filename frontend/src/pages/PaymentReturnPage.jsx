import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { getOrderStatus } from '../api/pipeline.js';
import PipelineShell from '../components/PipelineShell.jsx';

// Return route after checkout. NEVER trusts the redirect itself — it asks the
// server for the verified payment status (which reconciles against Stripe for
// real payments, or reflects the webhook-confirmed order in stub mode).
export default function PaymentReturnPage() {
  const { token } = useParams();
  const navigate = useNavigate();
  const [state, setState] = useState('checking');

  useEffect(() => {
    let cancelled = false;
    let tries = 0;

    async function poll() {
      try {
        const res = await getOrderStatus(token);
        if (cancelled) return;
        if (res.payment_status === 'paid') {
          setState('paid');
          setTimeout(() => !cancelled && navigate(`/p/${token}/intake`), 1200);
          return;
        }
      } catch {
        // fall through to retry / pending
      }
      if (cancelled) return;
      tries += 1;
      if (tries > 8) {
        setState('pending');
      } else {
        setTimeout(poll, 1000);
      }
    }
    poll();
    return () => { cancelled = true; };
  }, [token, navigate]);

  return (
    <PipelineShell step={2}>
      <div className="fp-card fp-center">
        {state === 'checking' && <><div className="fp-spinner" /><h2>Confirming your payment…</h2>
          <p className="fp-muted">We’re verifying this on our server — one moment.</p></>}
        {state === 'paid' && <><div className="fp-check-big">✓</div><h2>Payment confirmed</h2>
          <p className="fp-muted">Taking you to your website intake…</p></>}
        {state === 'pending' && <><h2>Still processing</h2>
          <p className="fp-muted">Your payment hasn’t confirmed yet. This can take a moment.</p>
          <button className="fp-btn" onClick={() => { setState('checking'); navigate(0); }}>Check again</button></>}
      </div>
    </PipelineShell>
  );
}
