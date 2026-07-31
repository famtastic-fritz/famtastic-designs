import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import {
  confirmProspect,
  formatPrice,
  getSession,
  startCheckout,
} from '../api/pipeline.js';
import PipelineShell from '../components/PipelineShell.jsx';
import '../pipeline.css';

const BUSINESS_FIELDS = [
  ['business_name', 'Business name', 'input'],
  ['business_category', 'Category', 'input'],
  ['business_description', 'Description', 'textarea'],
  ['address', 'Address', 'textarea'],
  ['service_area', 'Service area', 'input'],
  ['public_phone', 'Phone', 'input'],
  ['public_email', 'Email', 'input'],
  ['website_url', 'Existing website', 'input'],
  ['hours', 'Hours', 'textarea'],
];

const PAID_STATUSES = ['paid', 'intake_started', 'intake_complete', 'submitted_to_studio', 'proof_ready', 'revision_requested', 'approved', 'launched'];

export default function ProspectLandingPage() {
  const { token } = useParams();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [form, setForm] = useState(null);
  const [contact, setContact] = useState({ contact_name: '', contact_method: 'email', contact_value: '' });
  const [authorized, setAuthorized] = useState(false);
  const [saving, setSaving] = useState(false);
  const [paying, setPaying] = useState(false);
  const [notice, setNotice] = useState(null);
  const [termsAccepted, setTermsAccepted] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const session = await getSession(token);
      setData(session);
      const business = session.prospect.business;
      setForm((prev) => prev ?? { ...business });
      setContact((prev) => ({
        contact_name: session.prospect.contact.name || prev.contact_name,
        contact_method: session.prospect.contact.method || prev.contact_method || 'email',
        contact_value: session.prospect.contact.value || prev.contact_value,
      }));
      setAuthorized(Boolean(session.prospect.authorized));
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    load();
  }, [load]);

  const status = data?.prospect?.status;
  const isLead = useMemo(
    () => ['lead', ...PAID_STATUSES].includes(status),
    [status],
  );
  const isPaid = PAID_STATUSES.includes(status);

  async function handleConfirm(e) {
    e.preventDefault();
    if (!authorized) {
      setNotice({ type: 'error', text: 'Please confirm you are authorized to represent this business.' });
      return;
    }
    setSaving(true);
    setNotice(null);
    try {
      await confirmProspect(token, {
        corrections: form,
        contact_name: contact.contact_name,
        contact_method: contact.contact_method,
        contact_value: contact.contact_value,
        authorized: true,
      });
      setNotice({ type: 'success', text: 'Thanks — your business is confirmed.' });
      await load();
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
    } finally {
      setSaving(false);
    }
  }

  async function handlePay() {
    setPaying(true);
    setNotice(null);
    try {
      const res = await startCheckout(token, {
        package: 'essential_199',
        terms_accepted: termsAccepted,
        terms_checksum: data?.terms?.checksum,
      });
      if (res.already_paid) {
        navigate(`/p/${token}/return`);
        return;
      }
      if (res.gateway_mode === 'stub') {
        throw new Error('Secure checkout is temporarily unavailable. Please try again later.');
      }
      window.location.href = res.url;
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
      setPaying(false);
    }
  }

  if (loading) return <PipelineShell><p className="fp-muted">Loading your details…</p></PipelineShell>;
  if (error) {
    return (
      <PipelineShell>
        <div className="fp-card fp-card--error">
          <h2>This link isn’t valid</h2>
          <p className="fp-muted">{error}. The link may have expired or been used already. Please contact us for a new one.</p>
        </div>
      </PipelineShell>
    );
  }

  const offer = data.offer;

  return (
    <PipelineShell step={isPaid ? 3 : isLead ? 2 : 1}>
      <div className="fp-hero">
        <span className="fp-eyebrow">FAMtastic Designs</span>
        <h1>We built a starting point for <span className="fp-lime">{form?.business_name || 'your business'}</span></h1>
        <p className="fp-muted">
          We found your business online and put together a website offer. Review the details below, correct anything
          that’s off, and you can have a professional site live fast.
        </p>
      </div>

      {notice && <div className={`fp-notice fp-notice--${notice.type}`}>{notice.text}</div>}

      {isPaid && (
        <div className="fp-card fp-card--success">
          <h2>You’re all set — payment received</h2>
          <p className="fp-muted">Next, tell us about your business so we can build your site.</p>
          <button className="fp-btn fp-btn--lime" onClick={() => navigate(`/p/${token}/intake`)}>
            Continue to your website intake →
          </button>
        </div>
      )}

      {!isPaid && (
        <form className="fp-card" onSubmit={handleConfirm}>
          <h2>1. Confirm your business information</h2>
          <p className="fp-muted">We pulled this from public info — please correct anything that’s wrong.</p>
          <div className="fp-grid">
            {BUSINESS_FIELDS.map(([key, label, kind]) => (
              <label key={key} className={`fp-field ${kind === 'textarea' ? 'fp-field--wide' : ''}`}>
                <span>{label}</span>
                {kind === 'textarea' ? (
                  <textarea
                    value={form?.[key] ?? ''}
                    onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                    rows={2}
                  />
                ) : (
                  <input
                    type="text"
                    value={form?.[key] ?? ''}
                    onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                  />
                )}
              </label>
            ))}
          </div>

          <h2 className="fp-mt">2. Your contact details</h2>
          <div className="fp-grid">
            <label className="fp-field">
              <span>Your name</span>
              <input type="text" value={contact.contact_name}
                onChange={(e) => setContact({ ...contact, contact_name: e.target.value })} required />
            </label>
            <label className="fp-field">
              <span>Best contact method</span>
              <select value={contact.contact_method}
                onChange={(e) => setContact({ ...contact, contact_method: e.target.value })}>
                <option value="email">Email</option>
                <option value="phone">Phone</option>
                <option value="text">Text</option>
              </select>
            </label>
            <label className="fp-field">
              <span>Email / phone</span>
              <input type="text" value={contact.contact_value}
                onChange={(e) => setContact({ ...contact, contact_value: e.target.value })} required />
            </label>
          </div>

          <label className="fp-check">
            <input type="checkbox" checked={authorized} onChange={(e) => setAuthorized(e.target.checked)} />
            <span>I confirm I am authorized to represent this business.</span>
          </label>

          <button className="fp-btn" type="submit" disabled={saving}>
            {saving ? 'Saving…' : isLead ? 'Update my information' : 'Confirm my business'}
          </button>
        </form>
      )}

      {isLead && !isPaid && (
        <div className="fp-card fp-offer">
          <span className="fp-eyebrow">Your offer</span>
          <div className="fp-offer__head">
            <div>
              <h2>{offer.name}</h2>
              <p className="fp-muted">{offer.tagline}</p>
            </div>
            <div className="fp-price">{formatPrice(offer.amount, offer.currency)}</div>
          </div>
          <ul className="fp-list">
            {(offer.inclusions || []).map((item) => <li key={item}>{item}</li>)}
          </ul>
          <button className="fp-btn fp-btn--lime fp-btn--lg" onClick={handlePay} disabled={paying || !termsAccepted}>
            {paying ? 'Starting secure checkout…' : `Pay ${formatPrice(offer.amount, offer.currency)} & get started`}
          </button>
          <label className="fp-check">
            <input
              type="checkbox"
              checked={termsAccepted}
              onChange={(event) => setTermsAccepted(event.target.checked)}
            />
            <span>
              I accept Website Service Terms v{data?.terms?.version}. The first 12 months of hosting are included;
              recurring hosting requires separate authorization before month 13.
            </span>
          </label>
          <p className="fp-fineprint">
            {data.gateway_mode === 'stub'
              ? 'Secure checkout is temporarily unavailable.'
              : 'Secure payment via Stripe. You’ll be redirected to Stripe Checkout.'}
          </p>
        </div>
      )}
    </PipelineShell>
  );
}
