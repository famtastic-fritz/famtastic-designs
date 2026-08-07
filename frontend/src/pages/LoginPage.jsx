import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { customerLogin, customerRegister, forgotCustomerPassword } from '../api/customer.js';

export default function LoginPage() {
  const navigate = useNavigate();
  const [mode, setMode] = useState('login');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  async function recover() {
    const email = window.prompt('Enter the email on your customer account.');
    if (!email) return;
    try { const result = await forgotCustomerPassword(email); setNotice(result.message); }
    catch (exception) { setError(exception.message); }
  }
  async function submit(event) {
    event.preventDefault(); setError(''); setNotice(''); setBusy(true);
    const data = Object.fromEntries(new FormData(event.currentTarget));
    try {
      if (mode === 'login') { await customerLogin(data.email, data.password); navigate('/portal'); }
      else { await customerRegister(data); setNotice('Check your email to verify your account, then sign in.'); setMode('login'); }
    } catch (e) { setError(e.message); } finally { setBusy(false); }
  }
  return <section className="login-card" aria-labelledby="portal-heading">
    <span className="login-card__eyebrow">Private customer workspace</span>
    <h1 id="portal-heading" className="login-card__title">Client <span className="accent">Portal</span></h1>
    <p className="login-card__lede">Manage your projects, purchases, support, hosting, domains, team, and next opportunities—all in one place.</p>
    <div className="login-tabs"><button className={mode === 'login' ? 'active' : ''} onClick={() => setMode('login')}>Sign in</button><button className={mode === 'register' ? 'active' : ''} onClick={() => setMode('register')}>Create account</button></div>
    {error && <div className="alert alert--error" role="alert">{error}</div>}
    {notice && <div className="alert alert--success" role="status">{notice}</div>}
    <form className="form" onSubmit={submit}>
      {mode === 'register' && <><div className="form__field"><label className="form__label">Your name</label><input className="form__input" name="name" required autoComplete="name" /></div><div className="form__field"><label className="form__label">Business name <small>(optional)</small></label><input className="form__input" name="business_name" autoComplete="organization" /></div></>}
      <div className="form__field"><label className="form__label">Email</label><input className="form__input" name="email" type="email" required autoComplete="email" /></div>
      <div className="form__field"><label className="form__label">Password</label><input className="form__input" name="password" type="password" minLength="12" required autoComplete={mode === 'login' ? 'current-password' : 'new-password'} /></div>
      {mode === 'register' && <label className="portal-consent"><input type="checkbox" name="marketing_opt_out" value="1" /> Transactional messages only; do not send relevant product news and offers.</label>}
      <button className="btn btn--lime" disabled={busy}>{busy ? 'Please wait…' : mode === 'login' ? 'Open my portal' : 'Create my account'}</button>
      {mode === 'login' && <button className="login-recover" type="button" onClick={recover}>Forgot password?</button>}
    </form>
    <div className="login-card__help"><div><strong>Need account help?</strong><span>We’ll verify your identity and get you back in.</span></div><a href="mailto:support@famtasticdesigns.com?subject=Customer%20account%20help">Contact support</a></div>
    <p className="login-card__staff">Have a personalized pre-sale link? Open it directly from your email. FAMtastic staff can use the <a href="/web/user/login?destination=/admin/famtastic">operations login</a>.</p>
  </section>;
}
