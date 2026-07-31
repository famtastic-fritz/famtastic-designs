import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { useUser } from '../auth/UserContext.jsx';

/**
 * Dark-branded login form (Drupal email + password → OAuth password grant).
 * On success, navigates back to the ?redirect= target (default /admin).
 */
export default function LoginPage() {
  const { login, isAuthenticated } = useUser();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Only allow internal redirects — never bounce to an external origin.
  const rawRedirect = searchParams.get('redirect') || '/admin';
  const redirectTo = rawRedirect.startsWith('/') && !rawRedirect.startsWith('//')
    ? rawRedirect
    : '/admin';

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      await login(email.trim(), password);
      navigate(redirectTo, { replace: true });
    } catch (err) {
      setError(err.message || 'Login failed. Please try again.');
      setSubmitting(false);
    }
  }

  if (isAuthenticated) {
    return (
      <section className="login-card" aria-labelledby="login-heading">
        <h1 id="login-heading" className="login-card__title">
          Already signed <span className="accent">in</span>
        </h1>
        <p className="login-card__lede">
          You have an active session. Head to the{' '}
          <Link to="/admin">admin dashboard</Link> or{' '}
          <Link to="/">back to the site</Link>.
        </p>
      </section>
    );
  }

  return (
    <section className="login-card" aria-labelledby="login-heading">
      <h1 id="login-heading" className="login-card__title">
        Client <span className="accent">Login</span>
      </h1>
      <p className="login-card__lede">
        Sign in with your Drupal account to manage client projects.
      </p>

      {error && (
        <div className="alert alert--error" role="alert">
          {error}
        </div>
      )}

      <form className="form" onSubmit={handleSubmit} noValidate>
        <div className="form__field">
          <label className="form__label" htmlFor="login-email">
            Email
          </label>
          <input
            id="login-email"
            className="form__input"
            type="email"
            autoComplete="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="you@example.com"
          />
        </div>

        <div className="form__field">
          <label className="form__label" htmlFor="login-password">
            Password
          </label>
          <input
            id="login-password"
            className="form__input"
            type="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
          />
        </div>

        <button className="btn btn--lime" type="submit" disabled={submitting}>
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
    </section>
  );
}
