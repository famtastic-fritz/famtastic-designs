import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { customerLogin, customerRegister, forgotCustomerPassword } from '../api/customer.js';

export default function LoginPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [mode, setMode] = useState(searchParams.get('mode') === 'register' ? 'register' : 'login');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  async function submit(event) {
    event.preventDefault(); setError(''); setNotice(''); setBusy(true);
    const data = Object.fromEntries(new FormData(event.currentTarget));
    try {
      if (mode === 'login') { await customerLogin(data.email, data.password); navigate(searchParams.get('redirect') || '/portal'); }
      else if (mode === 'recover') { const result = await forgotCustomerPassword(data.email); setNotice(result.message); }
      else { await customerRegister(data); setNotice('Check your email to verify your free account. Your saved request will be waiting in the portal after you sign in.'); setMode('login'); }
    } catch (e) { setError(e.message); } finally { setBusy(false); }
  }
  return <section className="login-card" aria-labelledby="portal-heading">
    <span className="login-card__eyebrow">Private customer workspace</span>
    <h1 id="portal-heading" className="login-card__title">Client <span className="accent">Portal</span></h1>
    <p className="login-card__lede">{(searchParams.get('source')?.startsWith('public_') || searchParams.has('continuation')) ? 'Continue your saved request with a detailed website brief and working design demos. Your account is free, and you will not be asked for payment to complete the brief.' : 'Manage your projects, purchases, support, hosting, domains, team, and next opportunities—all in one place.'}</p>
    <div className="login-tabs" role="tablist"><button type="button" role="tab" aria-selected={mode === 'login'} className={mode === 'login' ? 'active' : ''} onClick={() => setMode('login')}>Sign in</button><button type="button" role="tab" aria-selected={mode === 'register'} className={mode === 'register' ? 'active' : ''} onClick={() => setMode('register')}>Create account</button></div>
    {error && <div className="alert alert--error" role="alert">{error}</div>}
    {notice && <div className="alert alert--success" role="status">{notice}</div>}
    <form className="form" onSubmit={submit}>
      {mode === 'register' && <input type="hidden" name="source" value={searchParams.get('source') || (searchParams.has('continuation') ? 'public_preview' : 'direct')} />}
      {mode === 'register' && searchParams.has('continuation') && <input type="hidden" name="preview_continuation" value={searchParams.get('continuation') || ''} />}
      {mode === 'register' && <><div className="form__field"><label className="form__label" htmlFor="portal-name">Your name</label><input id="portal-name" className="form__input" name="name" required autoComplete="name" /></div><div className="form__field"><label className="form__label" htmlFor="portal-business">Business name <small>(optional)</small></label><input id="portal-business" className="form__input" name="business_name" defaultValue={searchParams.get('business') || ''} autoComplete="organization" /></div></>}
      <div className="form__field"><label className="form__label" htmlFor="portal-email">Email</label><input id="portal-email" className="form__input" name="email" type="email" inputMode="email" defaultValue={searchParams.get('email') || ''} required autoComplete="email" /></div>
      {mode !== 'recover' && <div className="form__field"><label className="form__label" htmlFor="portal-password">Password</label><input id="portal-password" className="form__input" name="password" type="password" minLength="12" required autoComplete={mode === 'login' ? 'current-password' : 'new-password'} /></div>}
      {mode === 'register' && <label className="portal-consent"><input type="checkbox" name="marketing_opt_out" value="1" /> Transactional messages only; do not send relevant product news and offers.</label>}
      <button className="btn btn--lime" disabled={busy}>{busy ? 'Please wait…' : mode === 'login' ? 'Open my portal' : mode === 'recover' ? 'Send recovery email' : 'Create my account'}</button>
      {mode === 'login' && <button className="login-recover" type="button" onClick={() => { setMode('recover'); setError(''); setNotice(''); }}>Forgot password?</button>}
      {mode === 'recover' && <button className="login-recover" type="button" onClick={() => setMode('login')}>Back to sign in</button>}
    </form>
    <div className="login-card__help"><div><strong>Need account help?</strong><span>We’ll verify your identity and get you back in.</span></div><a href="mailto:support@famtasticdesigns.com?subject=Customer%20account%20help">Contact support</a></div>
  </section>;
}
