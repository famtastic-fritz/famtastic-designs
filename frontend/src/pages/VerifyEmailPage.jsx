import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { verifyCustomerEmail } from '../api/customer.js';
import { continuationReturn } from '../api/acquisition.js';

export default function VerifyEmailPage() {
  const [params] = useSearchParams();
  const token = params.get('token') || '';
  const verification = useRef(null);
  const [state, setState] = useState('working');
  const [destination, setDestination] = useState('/portal');
  useEffect(() => {
    let active = true;
    if (verification.current?.token !== token) {
      setState('working');
      verification.current = { token, promise: verifyCustomerEmail(token) };
    }
    verification.current.promise.then((result) => { if (active) { setDestination(continuationReturn(result.continuation) || '/portal'); setState('done'); } }).catch(() => { if (active) setState('error'); });
    return () => { active = false; };
  }, [token]);
  return <section className="login-card" aria-live="polite">
    <span className="login-card__eyebrow">Customer account</span>
    <h1 className="login-card__title">{state === 'working' ? 'Verifying…' : state === 'done' ? 'Email verified' : 'Link unavailable'}</h1>
    <p className="login-card__lede">{state === 'done' ? 'Your private customer workspace is ready.' : state === 'error' ? 'This verification link is invalid or expired. Contact support for help.' : 'Securing your account.'}</p>
    {state === 'done' && <Link className="btn btn--lime" to={`/login?redirect=${encodeURIComponent(destination)}`}>Sign in to my portal</Link>}
  </section>;
}
