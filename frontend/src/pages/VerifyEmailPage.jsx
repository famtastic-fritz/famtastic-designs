import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { verifyCustomerEmail } from '../api/customer.js';

export default function VerifyEmailPage() {
  const [params] = useSearchParams();
  const [state, setState] = useState('working');
  useEffect(() => {
    verifyCustomerEmail(params.get('token') || '').then(() => setState('done')).catch(() => setState('error'));
  }, [params]);
  return <section className="login-card" aria-live="polite">
    <span className="login-card__eyebrow">Customer account</span>
    <h1 className="login-card__title">{state === 'working' ? 'Verifying…' : state === 'done' ? 'Email verified' : 'Link unavailable'}</h1>
    <p className="login-card__lede">{state === 'done' ? 'Your private customer workspace is ready.' : state === 'error' ? 'This verification link is invalid or expired. Contact support for help.' : 'Securing your account.'}</p>
    {state === 'done' && <Link className="btn btn--lime" to="/login">Sign in to my portal</Link>}
  </section>;
}
