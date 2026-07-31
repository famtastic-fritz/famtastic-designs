import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { getSession, submitIntake, uploadAsset } from '../api/pipeline.js';
import PipelineShell from '../components/PipelineShell.jsx';
import '../pipeline.css';

const SECTIONS = [
  {
    title: 'Who you serve',
    fields: [
      ['ideal_customer', 'Ideal customer', 'textarea'],
      ['customer_problem', 'Problem you solve', 'textarea'],
      ['desired_outcome', 'Outcome customers want', 'textarea'],
      ['primary_goal', 'Primary goal of the website', 'input'],
      ['primary_cta', 'Primary call to action', 'input'],
      ['secondary_cta', 'Secondary call to action', 'input'],
    ],
  },
  {
    title: 'Your content',
    fields: [
      ['services', 'Services (one per line)', 'textarea'],
      ['about', 'About your business', 'textarea'],
      ['differentiators', 'What makes you different (one per line)', 'textarea'],
      ['credentials', 'Credentials / licenses (one per line)', 'textarea'],
      ['testimonials', 'Testimonials (one per line)', 'textarea'],
      ['faqs', 'FAQs (one per line)', 'textarea'],
      ['required_sections', 'Website sections you need (one per line)', 'textarea'],
      ['info_to_avoid', 'Anything to avoid saying', 'textarea'],
    ],
  },
  {
    title: 'Brand & style',
    fields: [
      ['brand_colors', 'Brand colors', 'input'],
      ['style_preferences', 'Style preferences', 'textarea'],
      ['reference_sites', 'Reference websites (one per line)', 'textarea'],
    ],
  },
  {
    title: 'Domain',
    fields: [
      ['existing_domain', 'Existing domain (if any)', 'input'],
      ['domain_registrar', 'Domain registrar', 'input'],
      ['existing_website', 'Existing website URL', 'input'],
    ],
  },
];

const ALL_FIELDS = SECTIONS.flatMap((s) => s.fields.map((f) => f[0]));

export default function IntakePage() {
  const { token } = useParams();
  const navigate = useNavigate();
  const [ready, setReady] = useState(false);
  const [values, setValues] = useState(() => Object.fromEntries(ALL_FIELDS.map((f) => [f, ''])));
  const [assets, setAssets] = useState([]);
  const [ownership, setOwnership] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const s = await getSession(token);
        if (cancelled) return;
        const paidStatuses = ['paid', 'intake_started', 'intake_complete', 'submitted_to_studio', 'proof_ready', 'revision_requested', 'approved', 'launched'];
        if (!paidStatuses.includes(s.prospect.status)) {
          navigate(`/p/${token}`);
          return;
        }
        setReady(true);
      } catch {
        navigate(`/p/${token}`);
      }
    })();
    return () => { cancelled = true; };
  }, [token, navigate]);

  async function handleUpload(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    setNotice(null);
    try {
      const res = await uploadAsset(token, file);
      setAssets((prev) => [...prev, { id: res.file_id, name: res.filename }]);
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
    } finally {
      setUploading(false);
      e.target.value = '';
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSaving(true);
    setNotice(null);
    try {
      await submitIntake(token, { ...values, asset_ownership_confirmed: ownership });
      navigate(`/p/${token}/status`);
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
      setSaving(false);
    }
  }

  if (!ready) return <PipelineShell step={3}><p className="fp-muted">Loading…</p></PipelineShell>;

  return (
    <PipelineShell step={3}>
      <div className="fp-hero">
        <span className="fp-eyebrow">Website intake</span>
        <h1>Tell us about your business</h1>
        <p className="fp-muted">The more you share, the better your site. You can keep any field brief.</p>
      </div>

      {notice && <div className={`fp-notice fp-notice--${notice.type}`}>{notice.text}</div>}

      <form onSubmit={handleSubmit}>
        {SECTIONS.map((section) => (
          <div className="fp-card" key={section.title}>
            <h2>{section.title}</h2>
            <div className="fp-grid">
              {section.fields.map(([key, label, kind]) => (
                <label key={key} className={`fp-field ${kind === 'textarea' ? 'fp-field--wide' : ''}`}>
                  <span>{label}</span>
                  {kind === 'textarea'
                    ? <textarea rows={3} value={values[key]} onChange={(e) => setValues({ ...values, [key]: e.target.value })} />
                    : <input type="text" value={values[key]} onChange={(e) => setValues({ ...values, [key]: e.target.value })} />}
                </label>
              ))}
            </div>
          </div>
        ))}

        <div className="fp-card">
          <h2>Logo & photos</h2>
          <p className="fp-muted">Upload your logo and any business photos (images up to 8 MB each).</p>
          <input type="file" accept="image/*" onChange={handleUpload} disabled={uploading} />
          {uploading && <p className="fp-muted">Uploading…</p>}
          {assets.length > 0 && (
            <ul className="fp-list fp-list--files">
              {assets.map((a) => <li key={a.id}>📎 {a.name}</li>)}
            </ul>
          )}
          <label className="fp-check">
            <input type="checkbox" checked={ownership} onChange={(e) => setOwnership(e.target.checked)} />
            <span>I confirm I own or have the rights to use the assets I’ve uploaded.</span>
          </label>
        </div>

        <button className="fp-btn fp-btn--lime fp-btn--lg" type="submit" disabled={saving}>
          {saving ? 'Submitting…' : 'Submit my website details →'}
        </button>
      </form>
    </PipelineShell>
  );
}
