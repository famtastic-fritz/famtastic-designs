import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'react-router';
import { getSession, submitApproval } from '../api/pipeline.js';
import PipelineShell from '../components/PipelineShell.jsx';
import '../pipeline.css';

export default function ProofStatusPage() {
  const { token } = useParams();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState(null);

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
      await load();
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <PipelineShell step={4}><p className="fp-muted">Loading…</p></PipelineShell>;

  const project = data?.project;
  const status = data?.prospect?.status;
  const proofUrl = project?.proof_url;
  const approval = project?.approval_status;

  return (
    <PipelineShell step={4}>
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
        </div>
      )}
    </PipelineShell>
  );
}
