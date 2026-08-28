import { useEffect, useState } from 'react';
import { Panel, Empty, date } from './PortalShared.jsx';
import PortalServicesView from './PortalServicesView.jsx';

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
  const [tutorialOpen, setTutorialOpen] = useState(false);
  const [tutorialStep, setTutorialStep] = useState(0);

  const tutorialSteps = [
    ['Register', 'Create your secure customer workspace.'],
    ['Click Start', 'Open the guided website brief.'],
    ['Fill out the form', 'Tell us about the business and desired outcome.'],
    ['View three proofs', 'Compare genuinely different visual directions.'],
    ['Select', 'Choose the direction that feels right.'],
    ['Pay securely', 'Complete the approved package through Commerce.'],
    ['That’s it', 'Follow the build, approval, and launch from your account.'],
  ];

  useEffect(() => {
    if (!tutorialOpen) return undefined;
    const timer = window.setInterval(
      () => setTutorialStep((step) => (step + 1) % tutorialSteps.length),
      1700
    );
    return () => window.clearInterval(timer);
  }, [tutorialOpen, tutorialSteps.length]);

  return (
    <>
      <section className="portal-home-intro">
        <section className="portal-ai-hero">
          <div className="portal-ai-hero__content">
            <span>FAMtastic AI Solutions Studio</span>
            <h2>Your business systems, all in one place.</h2>
            <p>
              Start a website, manage every active service, and discover the next useful AI or
              automation module.
            </p>
            <div className="portal-ai-hero__actions">
              <button onClick={() => go('projects')}>
                {requests.length ? 'Continue my website' : 'Start my website & proofs'}
              </button>
              <div className="portal-tutorial-trigger">
                <button
                  className="secondary"
                  onClick={() => {
                    setTutorialStep(0);
                    setTutorialOpen(true);
                  }}
                >
                  Play tutorial
                </button>
                <span role="tooltip">New here? Watch the 20-second website walkthrough.</span>
              </div>
            </div>
            <small>No technical language required. Save progress and return anytime.</small>
          </div>
          <div className="portal-ai-hero__signal" aria-hidden="true">
            <i />
            <i />
            <i />
          </div>
        </section>

        <article className="portal-inline-tutorial" aria-label="Start-to-launch website tutorial">
          <video
            src="/portal/website-journey-clay-v2.mp4"
            poster="/portal/website-journey-clay-v2.png"
            autoPlay
            muted
            loop
            playsInline
            controls
            aria-label="Animated website process tutorial with readable step-by-step instructions"
          />
        </article>
      </section>

      {workspace.orders.length > 0 && (
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
                {workspace.orders[0]?.package || 'Website & Hosting Package'} · Provisioned
              </h3>
              <p style={{ margin: 0, color: '#c2ccc2', fontSize: '0.9rem' }}>
                Hosting &amp; Domain entitlements active · Website architecture &amp; interactive concept design in progress.
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
                ✓ 1. Payment &amp; Provisioning
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#aab2aa' }}>
                Order confirmed · Entitlements unlocked
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
                ✓ 2. Cloud Hosting &amp; Domain
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#aab2aa' }}>
                1-Yr SSD Hosting &amp; Domain included
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
                ⚙ 3. Working Proof Concepts
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#fff' }}>
                Interactive preview designs in build
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
                ○ 4. Approval &amp; Live Launch
              </strong>
              <span style={{ fontSize: '0.8rem', color: '#687268' }}>
                Domain connected · SSL live
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
            className="portal-tutorial"
            role="dialog"
            aria-modal="true"
            aria-labelledby="portal-tutorial-title"
          >
            <button
              className="portal-tutorial__close"
              aria-label="Close website walkthrough"
              onClick={() => setTutorialOpen(false)}
            >
              ×
            </button>
            <div
              className="portal-tutorial__visual"
              style={{ '--tutorial-position': `${tutorialStep * 16.666}%` }}
            >
              <img
                className="portal-tutorial__poster"
                src="/portal/website-journey-clay-v2.png"
                alt="Clay artwork showing the simple journey from registration to a finished website"
              />
              <video
                src="/portal/website-journey-clay-v2.mp4"
                poster="/portal/website-journey-clay-v2.png"
                autoPlay
                muted
                loop
                playsInline
                aria-label="Clay animation with text showing how to register, complete a website brief, review proofs, select a design, pay, and launch"
              />
              <i aria-hidden="true" />
            </div>
            <div className="portal-tutorial__copy">
              <span>Website launch in seven easy steps</span>
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
        <Panel eyebrow="Next Action" title={nextAction} className="lime">
          <p>
            {!order
              ? 'Answer the guided questions once. Your responses become the project brief, recommendation, and delivery record.'
              : 'Your account keeps the next decision visible until the project can move forward.'}
          </p>
          <button onClick={() => go('projects')}>Continue</button>
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
              <strong>Review visual proofs</strong>
              <small>Compare distinct design directions, ask questions, and choose what feels right.</small>
            </div>
          </li>
          <li className={project?.approval_status === 'approved' ? 'complete' : ''}>
            <b>4</b>
            <div>
              <strong>Approve, build, and grow</strong>
              <small>Your decision, delivery, support, and future AI solutions stay connected to this workspace.</small>
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
          title={
            openThreads
              ? `${openThreads} open conversation${openThreads === 1 ? '' : 's'}`
              : `Welcome to ${org?.name || 'your workspace'}`
          }
        >
          <p>
            Ask a question without repeating your business or project history. Messages stay connected to this workspace.
          </p>
          <button onClick={() => go('messages')}>
            {openThreads ? 'View messages' : 'Ask FAMtastic'}
          </button>
        </Panel>
      </section>
    </>
  );
}
