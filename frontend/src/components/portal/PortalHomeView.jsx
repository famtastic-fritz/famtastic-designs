import { useEffect, useRef, useState } from 'react';
import { Panel, Empty, date } from './PortalShared.jsx';
import PortalServicesView from './PortalServicesView.jsx';
import { derivePortalFulfillmentState } from '../../lib/portalFulfillment.js';

export default function PortalHomeView({
  workspace,
  org,
  order,
  project,
  nextAction,
  go,
  catalog,
}) {
  const requests = workspace.website_requests || [];
  const openThreads = workspace.threads.filter((thread) => thread.status === 'open').length;
  const attentionRequest = requests.find((request) => request.proof_handoff?.state === 'needs_attention');
  const readyProof = requests.find(
    (request) =>
      !request.customer_archived &&
      ['customer_ready', 'notified'].includes(request.proof_review_status) &&
      [3, 6].includes(request.proofs?.variants?.length)
  );
  const fulfillment = derivePortalFulfillmentState(workspace);
  const [tutorialOpen, setTutorialOpen] = useState(false);
  const [tutorialStep, setTutorialStep] = useState(0);
  const tutorialTriggerRef = useRef(null);
  const tutorialDialogRef = useRef(null);
  const tutorialCloseRef = useRef(null);

  const tutorialSteps = [
    ['Register', 'Create your secure customer workspace.'],
    ['Click Start', 'Open the guided website brief.'],
    ['Fill out the form', 'Tell us about the business and desired outcome.'],
    ['View three proofs', 'Compare genuinely different visual directions.'],
    ['Select', 'Choose the direction that feels right.'],
    ['Build staging', 'FAMtastic turns your selected direction into a working private site.'],
    ['Review staging', 'Open the working site and confirm it before payment.'],
    ['Pay securely', 'Complete the approved package after staging is ready.'],
    ['Launch', 'Follow domain, SSL, email, and launch progress from your account.'],
  ];

  useEffect(() => {
    if (!tutorialOpen) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    window.requestAnimationFrame(() => tutorialCloseRef.current?.focus());
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setTutorialOpen(false);
        return;
      }
      if (event.key !== 'Tab' || !tutorialDialogRef.current) return;
      const focusable = Array.from(
        tutorialDialogRef.current.querySelectorAll('a[href], button:not([disabled]), video[controls], [tabindex]:not([tabindex="-1"])')
      );
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      }
      else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handleKeyDown);
      tutorialTriggerRef.current?.focus();
    };
  }, [tutorialOpen]);

  return (
    <>
      <section className="portal-next-action" aria-labelledby="portal-next-action-title">
        <div>
          <span className="portal-eyebrow">Your next decision</span>
          <h2 id="portal-next-action-title">
            {readyProof
              ? `${readyProof.proofs.variants.length} website concepts are ready to review`
              : nextAction}
          </h2>
          <p>
            {readyProof
              ? `${readyProof.project_name || 'Your website project'} has an owner-approved set ready in Projects.`
              : order
              ? 'Your workspace keeps the next decision visible until the project can move forward.'
              : 'Start with a guided brief. Your saved answers become the project record.'}
          </p>
        </div>
        <button type="button" onClick={() => go('projects')}>
          {readyProof ? 'Review concepts' : 'Open Projects'}
        </button>
      </section>

      <section className="portal-guidance-strip" aria-label="Website process help">
        <div>
          <span className="portal-eyebrow">Need the process explained?</span>
          <strong>See every step from brief to launch.</strong>
          <small>The walkthrough is optional, controlled by you, and never hides your project status.</small>
        </div>
        <button
          ref={tutorialTriggerRef}
          type="button"
          onClick={() => {
            setTutorialStep(0);
            setTutorialOpen(true);
          }}
        >
          Open walkthrough
        </button>
      </section>

      {fulfillment.show && (
        <section
          className="portal-fulfillment-banner"
          style={{
            margin: '1rem 0',
            padding: '1.25rem',
            border: '1px solid #7cfc00',
            borderRadius: '16px',
            background: 'linear-gradient(135deg, rgba(124,252,0,0.08), rgba(0,0,0,0.6))',
          }}
        >
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              flexWrap: 'wrap',
              gap: '1rem',
            }}
          >
            <div>
              <span
                style={{
                  color: '#7cfc00',
                  fontSize: '0.75rem',
                  fontWeight: '800',
                  textTransform: 'uppercase',
                  letterSpacing: '0.1em',
                }}
              >
                ⚡ Active Order Fulfillment
              </span>
              <h3 style={{ margin: '0.35rem 0', fontSize: '1.35rem' }}>
                {fulfillment.packageName} · {fulfillment.hasWebsiteService ? 'Website provisioning active' : 'Project work active'}
              </h3>
              <p style={{ margin: 0, color: '#c2ccc2', fontSize: '0.9rem' }}>
                {fulfillment.hostingLabel} · {fulfillment.domainLabel}.
              </p>
            </div>
            <button
              style={{ minHeight: '44px', padding: '0.6rem 1.25rem' }}
              onClick={() => go('projects')}
            >
              Open Project Command Center →
            </button>
          </div>
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
              gap: '0.75rem',
              marginTop: '1.25rem',
              paddingTop: '1rem',
              borderTop: '1px solid rgba(255,255,255,0.08)',
            }}
          >
            <div
              style={{
                padding: '0.75rem',
                borderRadius: '10px',
                background: 'rgba(255,255,255,0.03)',
                border: '1px solid rgba(255,255,255,0.06)',
              }}
            >
              <strong style={{ display: 'block', color: '#7cfc00', fontSize: '0.82rem' }}>
                {fulfillment.paymentConfirmed ? '✓' : '○'} 1. Payment &amp; Provisioning
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#aab2aa' }}>
                {fulfillment.paymentConfirmed ? 'Order confirmed · Entitlements unlocked' : 'Awaiting payment confirmation'}
              </span>
            </div>
            <div
              style={{
                padding: '0.75rem',
                borderRadius: '10px',
                background: 'rgba(255,255,255,0.03)',
                border: '1px solid rgba(255,255,255,0.06)',
              }}
            >
              <strong style={{ display: 'block', color: '#7cfc00', fontSize: '0.82rem' }}>
                {fulfillment.hasHosting ? '✓' : '○'} 2. Cloud Hosting &amp; Domain
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#aab2aa' }}>
                {fulfillment.hostingLabel} · {fulfillment.domainLabel}
              </span>
            </div>
            <div
              style={{
                padding: '0.75rem',
                borderRadius: '10px',
                background: 'rgba(124,252,0,0.08)',
                border: '1px solid #7cfc00',
              }}
            >
              <strong style={{ display: 'block', color: '#7cfc00', fontSize: '0.82rem' }}>
                {fulfillment.hasProofWork ? '⚙' : '○'} 3. Working Proof Concepts
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#fff' }}>
                {fulfillment.hasProofWork ? 'Open your project to review the current proof state' : 'Website proof work starts after the brief is submitted'}
              </span>
            </div>
            <div
              style={{
                padding: '0.75rem',
                borderRadius: '10px',
                background: 'rgba(255,255,255,0.03)',
                border: '1px solid rgba(255,255,255,0.06)',
              }}
            >
              <strong style={{ display: 'block', color: '#8e988e', fontSize: '0.82rem' }}>
                {fulfillment.hasLiveSite ? '✓' : '○'} 4. Approval &amp; Live Launch
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#687268' }}>
                {fulfillment.hasLiveSite ? 'Live site recorded' : 'Approval, domain connection, and SSL are not complete yet'}
              </span>
            </div>
          </div>
        </section>
      )}

      {tutorialOpen && (
        <div
          className="portal-tutorial-backdrop"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setTutorialOpen(false);
          }}
        >
          <section
            ref={tutorialDialogRef}
            className="portal-tutorial"
            role="dialog"
            aria-modal="true"
            aria-labelledby="portal-tutorial-title"
          >
            <button
              ref={tutorialCloseRef}
              className="portal-tutorial__close"
              aria-label="Close website walkthrough"
              onClick={() => setTutorialOpen(false)}
            >
              ×
            </button>
            <div
              className="portal-tutorial__visual"
              style={{ '--tutorial-position': `${tutorialStep * (100 / (tutorialSteps.length - 1))}%` }}
            >
              <img
                className="portal-tutorial__poster"
                src="/portal/website-journey-clay-v2.png"
                alt="Clay artwork showing the simple journey from registration to a finished website"
              />
              <video
                src="/portal/website-journey-clay-v2.mp4"
                poster="/portal/website-journey-clay-v2.png"
                muted
                playsInline
                controls
                aria-label="Clay animation introducing the website journey; the written steps provide the authoritative current process"
              />
              <i aria-hidden="true" />
            </div>
            <div className="portal-tutorial__copy">
              <span>Website launch in eight clear steps</span>
              <h2 id="portal-tutorial-title">{tutorialSteps[tutorialStep][0]}</h2>
              <p>{tutorialSteps[tutorialStep][1]}</p>
              <ol aria-label="Tutorial progress">
                {tutorialSteps.map(([label], index) => (
                  <li
                    key={label}
                    className={
                      index === tutorialStep
                        ? 'active'
                        : index < tutorialStep
                        ? 'complete'
                        : ''
                    }
                  >
                    <button
                      aria-label={`Show step ${index + 1}: ${label}`}
                      onClick={() => setTutorialStep(index)}
                    >
                      {index + 1}
                    </button>
                  </li>
                ))}
              </ol>
              <div className="portal-tutorial__actions">
                <button
                  onClick={() => {
                    setTutorialOpen(false);
                    go('projects');
                  }}
                >
                  Start my website
                </button>
                <button className="secondary" onClick={() => setTutorialOpen(false)}>
                  Not yet
                </button>
              </div>
            </div>
          </section>
        </div>
      )}

      <section className="portal-command-grid">
        <Panel eyebrow="Website Strategy" title="Know what to do after launch">
          <p>
            Open practical guidance for improving your site, attracting customers, and choosing the next useful service without guessing.
          </p>
          <div className="portal-form-actions">
            <button onClick={() => go('grow')}>Open growth guidance</button>
            <button className="secondary" onClick={() => go('faq')}>Read FAQs</button>
          </div>
        </Panel>
        <Panel
          eyebrow="Your Studio"
          title={`${workspace.entitlements.length} active service${
            workspace.entitlements.length === 1 ? '' : 's'
          }`}
        >
          <p>
            Manage websites, AI agents, automation, maintenance, analytics, and support from one account.
          </p>
          <button onClick={() => go('services')}>Open services</button>
        </Panel>
      </section>

      {attentionRequest && (
        <Panel
          eyebrow="Needs attention"
          title={`${attentionRequest.project_name} needs a closer look`}
          className="portal-panel"
        >
          <p>
            {attentionRequest.proof_handoff?.detail ||
              'A proof run stalled and FAMtastic is repairing it. The brief is safe and nothing was lost.'}
          </p>
          <div className="portal-form-actions">
            <button onClick={() => go('support')}>Open issue details →</button>
            <button className="secondary" onClick={() => go('projects')}>
              Jump to the project
            </button>
          </div>
        </Panel>
      )}

      <section className="portal-journey" aria-labelledby="portal-journey-title">
        <header>
          <span>How your studio works</span>
          <h2 id="portal-journey-title">From brief to business system</h2>
        </header>
        <ol>
          <li className={requests.length ? 'complete' : 'active'}>
            <b>1</b>
            <div>
              <strong>Tell us the outcome</strong>
              <small>A guided intake captures the business, audience, goals, content, features, and timing.</small>
            </div>
          </li>
          <li
            className={
              requests.some((request) =>
                ['submitted', 'checkout_started', 'converted'].includes(request.status)
              )
                ? 'active'
                : ''
            }
          >
            <b>2</b>
            <div>
              <strong>AI studio research</strong>
              <small>Specialists organize the brief, check assumptions, and recommend the smallest useful solution.</small>
            </div>
          </li>
          <li className={project?.proof_url ? 'active' : ''}>
            <b>3</b>
            <div>
              <strong>Review and select a direction</strong>
              <small>Compare distinct visual directions, ask questions, and lock in what feels right.</small>
            </div>
          </li>
          <li className={requests.some((request) => request.staging_status === 'deployed') ? 'complete' : ''}>
            <b>4</b>
            <div>
              <strong>Review the working site</strong>
              <small>Your chosen direction becomes a private staging site before checkout opens.</small>
            </div>
          </li>
          <li className={order?.payment_status === 'paid' ? 'complete' : ''}>
            <b>5</b>
            <div>
              <strong>Pay, launch, and grow</strong>
              <small>Payment starts production fulfillment; domain, SSL, email, support, and growth stay connected here.</small>
            </div>
          </li>
        </ol>
      </section>

      <PortalServicesView workspace={workspace} catalog={catalog} go={go} compact />

      <section className="portal-grid two">
        <Panel eyebrow="Recent Activity" title="What FAMtastic has handled">
          {workspace.activity.length ? (
            <ul>
              {workspace.activity.slice(0, 5).map((item, i) => (
                <li key={i}>
                  <strong>{item.summary}</strong>
                  <small>{date(item.created)}</small>
                </li>
              ))}
            </ul>
          ) : (
            <Empty>
              Start a website request and each saved brief, submission, proof, approval, and delivery milestone will appear here.
            </Empty>
          )}
        </Panel>

        <Panel
          eyebrow="Help When You Need It"
          title={openThreads ? `${openThreads} open conversation${openThreads === 1 ? '' : 's'}` : `Welcome to ${org?.name || 'your workspace'}`}
        >
          <p>
            Ask a question without repeating your business or project history. Messages stay connected to this workspace, and the support view is where issues live.
          </p>
          <div className="portal-form-actions">
            <button onClick={() => go('messages')}>
              {openThreads ? 'View messages' : 'Ask FAMtastic'}
            </button>
            <button className="secondary" onClick={() => go('support')}>
              Open support
            </button>
          </div>
        </Panel>
      </section>
    </>
  );
}
