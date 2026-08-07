import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router';
import { formatPrice, getSession } from '../api/pipeline.js';
import '../pipeline.css';

const PHASES = [
  { id: 'purchase', label: 'Purchase', detail: 'Order confirmed' },
  { id: 'intake', label: 'Discovery', detail: 'Your project brief' },
  { id: 'build', label: 'Build', detail: 'Design and production' },
  { id: 'launch', label: 'Launch', detail: 'Approval and delivery' },
];

const STATUS_PHASE = {
  new: 0,
  confirmed: 0,
  checkout_started: 0,
  paid: 1,
  intake_started: 1,
  intake_complete: 2,
  submitted_to_studio: 2,
  proof_ready: 2,
  revision_requested: 2,
  approved: 3,
  launched: 4,
};

function titleCase(value) {
  return String(value || 'Preparing').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function portalAction(data, token) {
  const paid = data?.order?.payment_status === 'paid';
  if (!paid) return { label: 'Complete your purchase', to: `/p/${token}` };
  if (!data?.intake?.submitted) return { label: 'Complete your project brief', to: `/p/${token}/intake` };
  if (data?.project?.proof_url && data?.project?.approval_status !== 'approved') {
    return { label: 'Review your website proof', to: `/p/${token}/status` };
  }
  if (data?.project?.live_url) return { label: 'Visit your live website', href: data.project.live_url };
  return { label: 'View project details', to: `/p/${token}/status` };
}

export default function ClientPortalPage() {
  const { token } = useParams();
  const [data, setData] = useState(null);
  const [state, setState] = useState('loading');
  const [updatedAt, setUpdatedAt] = useState(null);

  const load = useCallback(async () => {
    setState((current) => current === 'ready' ? 'refreshing' : 'loading');
    try {
      setData(await getSession(token));
      setUpdatedAt(new Date());
      setState('ready');
    } catch (error) {
      setState(error.status === 404 ? 'invalid' : 'error');
    }
  }, [token]);

  useEffect(() => { load(); }, [load]);

  const phase = Math.min(STATUS_PHASE[data?.prospect?.status] ?? 0, PHASES.length);
  const action = useMemo(() => portalAction(data, token), [data, token]);

  if (state === 'loading') {
    return <div className="cp-page cp-state"><div className="cp-orbit" /><p>Opening your project command center…</p></div>;
  }

  if (state === 'invalid' || state === 'error') {
    return (
      <div className="cp-page cp-state">
        <span className="cp-kicker">Private client portal</span>
        <h1>{state === 'invalid' ? 'This portal link is no longer active.' : 'Your portal is temporarily unavailable.'}</h1>
        <p>Contact <a href="mailto:support@famtasticdesigns.com">support@famtasticdesigns.com</a> and we’ll help.</p>
      </div>
    );
  }

  const business = data?.prospect?.business?.business_name || data?.prospect?.contact?.name || 'Your project';
  const project = data?.project;
  const order = data?.order;

  return (
    <div className="cp-page">
      <header className="cp-header">
        <Link className="cp-brand" to="/">FAM<span>tastic</span> Designs</Link>
        <div className="cp-secure"><i /> Private project workspace</div>
      </header>

      <main className="cp-shell">
        <section className="cp-hero">
          <div>
            <span className="cp-kicker">Client command center</span>
            <h1>{business}</h1>
            <p>One clear view of your project, approvals, launch, hosting, and next move.</p>
          </div>
          <div className="cp-hero__action">
            <span className="cp-label">Next best action</span>
            {action.href ? (
              <a className="cp-primary" href={action.href} target="_blank" rel="noreferrer">{action.label} <b>↗</b></a>
            ) : (
              <Link className="cp-primary" to={action.to}>{action.label} <b>→</b></Link>
            )}
          </div>
        </section>

        <section className="cp-progress" aria-label="Project progress">
          <div className="cp-progress__line"><span style={{ width: `${Math.max(4, (phase / PHASES.length) * 100)}%` }} /></div>
          <ol>
            {PHASES.map((item, index) => (
              <li key={item.id} className={index < phase ? 'is-complete' : index === phase ? 'is-current' : ''}>
                <i>{index < phase ? '✓' : index + 1}</i>
                <span><strong>{item.label}</strong><small>{item.detail}</small></span>
              </li>
            ))}
          </ol>
        </section>

        <section className="cp-grid">
          <article className="cp-panel cp-panel--spotlight">
            <div className="cp-panel__head"><span className="cp-kicker">Project pulse</span><span className="cp-live"><i /> Live</span></div>
            <h2>{titleCase(project?.delivery_status || data?.prospect?.status)}</h2>
            <p>{project?.proof_url ? 'Your latest work is available from this portal.' : 'Your project workspace will update as each milestone is completed.'}</p>
            <div className="cp-actions">
              {project?.proof_url && <a href={project.proof_url} target="_blank" rel="noreferrer">Open proof ↗</a>}
              <Link to={`/p/${token}/status`}>Project details →</Link>
            </div>
          </article>

          <article className="cp-panel">
            <span className="cp-kicker">Purchase</span>
            <h2>{order ? formatPrice(order.amount, order.currency) : 'Not started'}</h2>
            <dl className="cp-facts">
              <div><dt>Package</dt><dd>{titleCase(order?.package || data?.proof?.selected_package || 'Pending')}</dd></div>
              <div><dt>Payment</dt><dd className={order?.payment_status === 'paid' ? 'is-good' : ''}>{titleCase(order?.payment_status || 'Pending')}</dd></div>
              <div><dt>Add-ons</dt><dd>{data?.add_ons?.length || 0}</dd></div>
            </dl>
          </article>

          <article className="cp-panel">
            <span className="cp-kicker">Launch systems</span>
            <dl className="cp-facts cp-facts--systems">
              <div><dt>Domain</dt><dd>{data?.domain?.domain_name || 'Preparing'}</dd></div>
              <div><dt>DNS</dt><dd>{titleCase(data?.domain?.dns_status)}</dd></div>
              <div><dt>SSL</dt><dd>{titleCase(data?.domain?.ssl_status)}</dd></div>
              <div><dt>Hosting</dt><dd>{titleCase(data?.hosting?.status)}</dd></div>
            </dl>
          </article>

          <article className="cp-panel cp-panel--support">
            <span className="cp-kicker">Human support</span>
            <h2>Need a hand?</h2>
            <p>Your project context stays attached when you contact the FAMtastic team.</p>
            <a className="cp-secondary" href={`mailto:support@famtasticdesigns.com?subject=${encodeURIComponent(`Portal support — ${business}`)}`}>Message support →</a>
          </article>
        </section>

        <footer className="cp-footer">
          <span>{updatedAt ? `Updated ${updatedAt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}` : 'Current project data'}</span>
          <button type="button" onClick={load} disabled={state === 'refreshing'}>{state === 'refreshing' ? 'Refreshing…' : 'Refresh status'}</button>
        </footer>
      </main>
    </div>
  );
}
