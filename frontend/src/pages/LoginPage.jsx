import { useState } from 'react';
import { useNavigate } from 'react-router';

function extractPortalToken(value) {
  const input = value.trim();
  if (/^[A-Za-z0-9_-]{43}$/.test(input)) return input;

  try {
    const url = new URL(input);
    const match = url.pathname.match(/^\/(?:portal|p)\/([A-Za-z0-9_-]{43})(?:\/|$)/);
    return match?.[1] || '';
  } catch {
    return '';
  }
}

/**
 * Customer portal entry. Customers authenticate with the private, random link
 * delivered for their project; Drupal staff authentication stays separate.
 */
export default function LoginPage() {
  const navigate = useNavigate();
  const [portalLink, setPortalLink] = useState('');
  const [error, setError] = useState('');

  function openPortal(event) {
    event.preventDefault();
    const token = extractPortalToken(portalLink);
    if (!token) {
      setError('Paste the complete private portal link from your FAMtastic email.');
      return;
    }
    navigate(`/portal/${token}`);
  }

  return (
    <section className="login-card login-card--portal" aria-labelledby="portal-heading">
      <span className="login-card__eyebrow">Private project workspace</span>
      <h1 id="portal-heading" className="login-card__title">
        Client <span className="accent">Portal</span>
      </h1>
      <p className="login-card__lede">
        Your project portal uses a secure private link—there is no customer password to remember.
        Open the link from your FAMtastic project email, or paste it below.
      </p>

      {error && <div className="alert alert--error" role="alert">{error}</div>}

      <form className="form" onSubmit={openPortal} noValidate>
        <div className="form__field">
          <label className="form__label" htmlFor="portal-link">Private portal link</label>
          <input
            id="portal-link"
            className="form__input"
            type="text"
            autoComplete="off"
            spellCheck="false"
            value={portalLink}
            onChange={(event) => setPortalLink(event.target.value)}
            placeholder="https://famtasticdesigns.com/portal/…"
          />
        </div>
        <button className="btn btn--lime" type="submit">Open my portal</button>
      </form>

      <div className="login-card__help">
        <div>
          <strong>Can’t find your link?</strong>
          <span>We’ll verify your project and resend it securely.</span>
        </div>
        <a href="mailto:support@famtasticdesigns.com?subject=Please%20resend%20my%20client%20portal%20link">Request my link</a>
      </div>

      <p className="login-card__staff">
        FAMtastic staff? <a href="/web/user/login?destination=/admin/famtastic">Open Drupal administration</a>
      </p>
    </section>
  );
}
