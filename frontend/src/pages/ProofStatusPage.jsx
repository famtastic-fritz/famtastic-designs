import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import {
  authorizeHostingRenewal,
  cancelHostingRenewal,
  formatPrice,
  getSession,
  startRevisionCheckout,
  submitApproval,
} from '../api/pipeline.js';
import PipelineShell from '../components/PipelineShell.jsx';
import '../pipeline.css';

export default function ProofStatusPage() {
  const { token } = useParams();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState(null);
  const [addonRequired, setAddonRequired] = useState(false);
  const [addonTermsAccepted, setAddonTermsAccepted] = useState(false);
  const [renewalAuthorized, setRenewalAuthorized] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setData(await getSession(token));
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => { load(); }, [load]);

  async function act(action) {
    setBusy(true);
    setNotice(null);
    try {
      await submitApproval(token, action, note);
      setAddonRequired(false);
      await load();
    } catch (err) {
      if (err.code === 'revision_addon_required') {
        setAddonRequired(true);
      }
      setNotice({ type: 'error', text: err.message });
    } finally {
      setBusy(false);
    }
  }

  async function buyRevision() {
    setBusy(true);
    setNotice(null);
    try {
      const result = await startRevisionCheckout(token, {
        terms_accepted: addonTermsAccepted,
        terms_checksum: data?.terms?.checksum,
      });
      if (result.gateway_mode === 'stub') {
        throw new Error('Secure checkout is temporarily unavailable. Please try again later.');
      }
      window.location.href = result.url;
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
      setBusy(false);
    }
  }

  async function authorizeRenewal() {
    const offer = data?.hosting_renewal_offer;
    if (!offer) return;
    setBusy(true);
    setNotice(null);
    try {
      await authorizeHostingRenewal(token, {
        recurring_authorized: renewalAuthorized,
        amount_minor: offer.amount_minor,
      });
      setNotice({ type: 'success', text: 'Monthly hosting renewal is authorized.' });
      await load();
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
    } finally {
      setBusy(false);
    }
  }

  async function cancelRenewal() {
    setBusy(true); setNotice(null);
    try {
      await cancelHostingRenewal(token);
      setNotice({ type: 'success', text: 'Future monthly hosting renewal is canceled. Included or already paid hosting remains available through its end date.' });
      await load();
    } catch (err) { setNotice({ type: 'error', text: err.message }); }
    finally { setBusy(false); }
  }

  if (loading) return <PipelineShell step={4}><p className="fp-muted">Loading…</p></PipelineShell>;

  const project = data?.project;
  const status = data?.prospect?.status;
  const proofUrl = project?.proof_url;
  const approval = project?.approval_status;

  return (
    <PipelineShell step={4}>
      <Link className="fp-portal-back" to={`/portal/${token}`}>← Back to client portal</Link>
      <div className="fp-hero">
        <span className="fp-eyebrow">Your website proof</span>
        <h1>{data?.prospect?.business?.business_name || 'Your website'}</h1>
      </div>

      {notice && <div className={`fp-notice fp-notice--${notice.type}`}>{notice.text}</div>}

      {!proofUrl && (
        <div className="fp-card">
          <h2>We’re building your site 🛠️</h2>
          <p className="fp-muted">
            Thanks for your details — our team is putting together your website proof. You’ll get a link here as soon
            as it’s ready. Current status: <strong>{status}</strong>.
          </p>
        </div>
      )}

      {proofUrl && approval === 'approved' && (
        <div className="fp-card fp-card--success">
          <h2>Approved — thank you! 🎉</h2>
          <p className="fp-muted">We’ll take it from here and get your site launched.</p>
          <a className="fp-btn" href={proofUrl} target="_blank" rel="noreferrer">View your site</a>
          {project?.live_url && <a className="fp-btn fp-btn--lime" href={project.live_url} target="_blank" rel="noreferrer">View live site</a>}
        </div>
      )}

      {proofUrl && approval !== 'approved' && (
        <div className="fp-card">
          <h2>Your proof is ready 🎉</h2>
          <p className="fp-muted">Review your website proof, then approve it or request your included revision.</p>
          <a className="fp-btn fp-btn--lime fp-btn--lg" href={proofUrl} target="_blank" rel="noreferrer">
            Open my website proof →
          </a>

          {approval === 'revision_requested' && (
            <div className="fp-notice fp-notice--success">Revision requested — we’re on it. You can still approve once it’s updated.</div>
          )}

          <div className="fp-divider" />
          <h3>Request your included revision</h3>
          <textarea
            rows={3}
            placeholder="Tell us what you’d like changed…"
            value={note}
            onChange={(e) => setNote(e.target.value)}
          />
          <div className="fp-actions">
            <button className="fp-btn" disabled={busy || !note.trim()} onClick={() => act('request_revision')}>
              Request this revision
            </button>
            <button className="fp-btn fp-btn--lime" disabled={busy} onClick={() => act('approve')}>
              Approve my site
            </button>
          </div>
          {addonRequired && (
            <div className="fp-addon">
              <h3>Need another revision?</h3>
              <p className="fp-muted">
                Your included revision allowance has been used. Purchase one additional revision for
                {' '}{formatPrice(7500, 'usd')}.
              </p>
              <label className="fp-check">
                <input
                  type="checkbox"
                  checked={addonTermsAccepted}
                  onChange={(event) => setAddonTermsAccepted(event.target.checked)}
                />
                <span>I accept the current Website Service Terms for this add-on.</span>
              </label>
              <button className="fp-btn fp-btn--lime" disabled={busy || !addonTermsAccepted} onClick={buyRevision}>
                Purchase one revision — {formatPrice(7500, 'usd')}
              </button>
            </div>
          )}
        </div>
      )}

      {(data?.deployment || data?.domain || data?.hosting || data?.subscription) && (
        <div className="fp-card">
          <span className="fp-eyebrow">Launch and hosting</span>
          <h2>Your service status</h2>
          <dl className="fp-status-grid">
            <div>
              <dt>Website</dt>
              <dd>{data?.deployment?.status || project?.delivery_status || 'Preparing'}</dd>
            </div>
            <div>
              <dt>Domain</dt>
              <dd>{data?.domain?.domain_name || 'Not connected'}</dd>
            </div>
            <div>
              <dt>DNS</dt>
              <dd>{data?.domain?.dns_status || 'Pending'}</dd>
            </div>
            <div>
              <dt>SSL</dt>
              <dd>{data?.domain?.ssl_status || 'Pending'}</dd>
            </div>
            <div>
              <dt>Hosting</dt>
              <dd>{data?.hosting?.status || 'Not started'}</dd>
            </div>
            <div>
              <dt>Included hosting ends</dt>
              <dd>
                {data?.hosting?.included_until
                  ? new Date(Number(data.hosting.included_until) * 1000).toLocaleDateString()
                  : 'Not scheduled'}
              </dd>
            </div>
            <div>
              <dt>Monthly renewal</dt>
              <dd>
                {data?.subscription
                  ? `${formatPrice(data.subscription.amount_minor, data.subscription.currency)} — ${data.subscription.status}`
                  : 'Not authorized'}
              </dd>
            </div>
          </dl>
          {data?.hosting_renewal_offer && (
            <div className="fp-addon">
              <h3>Keep hosting after the included year</h3>
              <p className="fp-muted">
                Beginning {new Date(Number(data.hosting_renewal_offer.starts_at) * 1000).toLocaleDateString()},
                hosting renews monthly at {formatPrice(data.hosting_renewal_offer.amount_minor, data.hosting_renewal_offer.currency)}.
              </p>
              <label className="fp-check">
                <input
                  type="checkbox"
                  checked={renewalAuthorized}
                  onChange={(event) => setRenewalAuthorized(event.target.checked)}
                />
                <span>I authorize this separate monthly recurring hosting charge beginning after my included year.</span>
              </label>
              <button className="fp-btn fp-btn--lime" disabled={busy || !renewalAuthorized} onClick={authorizeRenewal}>
                Authorize monthly hosting
              </button>
            </div>
          )}
          {data?.subscription && data.subscription.status !== 'canceled' && (
            <div className="fp-addon">
              <h3>Manage monthly hosting</h3>
              <p className="fp-muted">Cancellation stops future charges. Included or already paid service remains available through its current end date.</p>
              <button className="fp-btn" disabled={busy} onClick={cancelRenewal}>Cancel future monthly renewal</button>
            </div>
          )}
        </div>
      )}
    </PipelineShell>
  );
}
