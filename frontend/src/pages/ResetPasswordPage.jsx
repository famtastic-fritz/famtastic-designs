import { useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { resetCustomerPassword } from '../api/customer.js';

export default function ResetPasswordPage() {
  const [params] = useSearchParams();
  const [done, setDone] = useState(false);
  const [error, setError] = useState('');
  async function submit(event) {
    event.preventDefault(); setError('');
    try { await resetCustomerPassword(params.get('token') || '', new FormData(event.currentTarget).get('password')); setDone(true); }
    catch (exception) { setError(exception.message); }
  }
  return <section className="login-card"><span className="login-card__eyebrow">Account recovery</span><h1 className="login-card__title">Choose a new password</h1>{error && <div className="alert alert--error">{error}</div>}{done ? <><p className="login-card__lede">Your password has been updated.</p><Link className="btn btn--lime" to="/login">Sign in</Link></> : <form className="form" onSubmit={submit}><div className="form__field"><label className="form__label">New password</label><input className="form__input" name="password" type="password" minLength="12" required autoComplete="new-password" /></div><button className="btn btn--lime">Update password</button></form>}</section>;
}
