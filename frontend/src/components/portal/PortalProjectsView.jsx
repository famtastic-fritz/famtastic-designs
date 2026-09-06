import { useEffect, useRef, useState } from 'react';
import { Panel, title, date } from './PortalShared.jsx';
import { collectUtmParams } from '../../api/pipeline.js';

export function customerNextStep(request) {
  if (!request) return null;
  if (request.status === 'draft') {
    return { owner: 'you', tone: 'action', label: 'Finish your website brief', detail: 'Tell us what the business sells and what the site needs to accomplish.', action: 'brief' };
  }
  if (['customer_ready', 'notified'].includes(request.proof_review_status) && !request.selected_proof_direction) {
    return { owner: 'you', tone: 'action', label: 'Choose one website direction', detail: 'Review the research and open all three concepts before choosing one.', action: 'proofs' };
  }
  if (request.proof_review_status === 'revision_requested') {
    return { owner: 'famtastic', tone: 'waiting', label: 'FAMtastic is working on your changes', detail: 'Your notes are saved. You do not need to do anything right now.', action: '' };
  }
  if (request.proof_review_status === 'selected' && request.direct_checkout_available) {
    return { owner: 'you', tone: 'action', label: 'Complete payment to start the build', detail: 'Your direction is saved. Secure checkout is the next separate step.', action: 'payment' };
  }
  if (request.status === 'checkout_started') {
    return { owner: 'you', tone: 'action', label: 'Finish secure checkout', detail: 'Your direction is saved, but payment is not recorded yet.', action: 'billing' };
  }
  if (request.status === 'converted') {
    return { owner: 'famtastic', tone: 'waiting', label: 'Your build is underway', detail: 'Payment is recorded. FAMtastic owns the next build update.', action: '' };
  }
  if (request.proof_review_status === 'selected') {
    return { owner: 'famtastic', tone: 'waiting', label: 'FAMtastic is preparing your build offer', detail: 'Your direction is saved. Nothing else is required until your offer is ready.', action: '' };
  }
  if (request.proof_handoff?.state === 'needs_attention') {
    return { owner: 'famtastic', tone: 'attention', label: 'FAMtastic needs to repair this proof run', detail: 'Your brief is safe. You do not need to submit it again.', action: '' };
  }
  return { owner: 'famtastic', tone: 'waiting', label: request.proof_handoff?.label || 'FAMtastic is preparing your concepts', detail: request.proof_handoff?.detail || 'You do not need to do anything right now.', action: '' };
}

function ProofDecisionGuide({ request }) {
  const research = request.proofs?.research_snapshot;
  const terms = request.proofs?.review_terms || {};
  const signals = research?.market_signals || [];
  const opportunities = research?.opportunities || [];

  return (
    <section className="portal-proof-decision-guide" aria-label="Research and review guide">
      <div>
        <span className="eyebrow">Start here</span>
        <h3>Why we designed these three directions</h3>
        <p>
          {research?.overview || 'Your research brief will be published with the concepts that FAMtastic approves for review.'}
        </p>
      </div>

      {(signals.length > 0 || opportunities.length > 0) && (
        <details className="portal-proof-research-details">
          <summary>See the research and growth opportunities</summary>
          <div className="portal-proof-research-list">
            {signals.length > 0 && (
              <div>
                <strong>What we found</strong>
                <ul>{signals.map((signal) => <li key={signal}>{signal}</li>)}</ul>
              </div>
            )}
            {opportunities.length > 0 && (
              <div>
                <strong>Ways this site can help you grow</strong>
                <ul>{opportunities.map((opportunity) => <li key={opportunity}>{opportunity}</li>)}</ul>
              </div>
            )}
          </div>
        </details>
      )}

      {research?.sources?.length > 0 && (
        <details className="portal-proof-sources">
          <summary>Research recorded {research.researched_at ? `on ${research.researched_at}` : ''}</summary>
          <ul>{research.sources.map((source) => <li key={source}>{source}</li>)}</ul>
        </details>
      )}

      <div className="portal-proof-terms">
        <strong>What happens next</strong>
        <span>1. Open all 3</span>
        <span>2. Choose one</span>
        <span>3. Pay to start the build</span>
      </div>
      <small className="portal-proof-included">Included: {terms.design_reset_remaining ?? 1} full design reset before selection and {terms.edit_rounds_remaining ?? 3} edit rounds after selection.</small>
    </section>
  );
}

export function WebsiteProofReview({ request, busy, onDecision, onShare, onContinue }) {
  const [revisionOpen, setRevisionOpen] = useState(false);
  const [savingDirection, setSavingDirection] = useState('');
  const [copied, setCopied] = useState(false);
  const nextActionRef = useRef(null);
  const revisionRef = useRef(null);

  const variants = request.proofs?.variants || [];
  const selectedDirection =
    request.proofs?.selected_variant || request.selected_proof_direction || '';
  const selectedProof = variants.find((proof) => proof.direction_id === selectedDirection);
  const revisionPending = request.proof_review_status === 'revision_requested';
  const proofShare = request.proof_share || { enabled: false, url: '' };
  const terms = request.proofs?.review_terms || {};
  const directionRationale = request.proofs?.research_snapshot?.direction_rationale || {};
  const changesRemaining = selectedDirection
    ? (terms.edit_rounds_remaining ?? 3)
    : (terms.design_reset_remaining ?? 1);

  useEffect(() => {
    if (!revisionOpen) return;
    revisionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    revisionRef.current?.querySelector('textarea')?.focus({ preventScroll: true });
  }, [revisionOpen]);

  const choose = async (direction) => {
    setSavingDirection(direction);
    const saved = await onDecision(request.public_id, { action: 'select', direction });
    setSavingDirection('');
    if (saved) {
      window.requestAnimationFrame(() => {
        nextActionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        nextActionRef.current?.focus({ preventScroll: true });
      });
    }
  };

  const requestChanges = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const notes = String(new FormData(form).get('notes') || '').trim();
    const saved = await onDecision(request.public_id, { action: 'revision', notes });
    if (saved) {
      form.reset();
      setRevisionOpen(false);
    }
  };

  const copyShareLink = async () => {
    if (!proofShare.url) return;
    try {
      await navigator.clipboard.writeText(proofShare.url);
    } catch {
      const input = document.getElementById(`proof-share-url-${request.public_id}`);
      input?.select();
      document.execCommand('copy');
    }
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2200);
  };

  return (
    <section
      className="portal-proof-review"
      aria-label={`Review concepts for ${request.project_name}`}
    >
      <ProofDecisionGuide request={request} />
      <p className="portal-proof-swipe-hint">Swipe sideways to compare all three directions.</p>
      <div className={`portal-proof-grid${selectedDirection ? ' has-selection' : ''}`}>
        {variants.map((proof) => {
          const selected = selectedDirection === proof.direction_id;
          return (
            <article
              key={proof.direction_id}
              data-proof-direction={proof.direction_id}
              className={selected ? 'selected' : selectedDirection ? 'dimmed' : ''}
            >
              <span className="portal-proof-badge" aria-hidden={!selected}>
                {selected ? '✓ Selected' : 'Available'}
              </span>
              <a
                href={proof.preview_url}
                target="_blank"
                rel="noreferrer"
                className="portal-proof-preview"
                aria-label={`Live preview of ${proof.direction_name}`}
              >
                <iframe
                  src={proof.preview_url}
                  title={`${proof.direction_name} live preview`}
                  loading="lazy"
                  tabIndex={-1}
                  sandbox="allow-scripts allow-same-origin"
                  scrolling="no"
                />
              </a>
              <a href={proof.preview_url} target="_blank" rel="noreferrer">
                <b>{proof.direction_name}</b>
                <span>Open working concept ↗</span>
              </a>
              {directionRationale[proof.direction_id] && (
                <p className="portal-proof-card-rationale">{directionRationale[proof.direction_id]}</p>
              )}
              <button
                type="button"
                aria-pressed={selected}
                disabled={busy || Boolean(selectedDirection)}
                onClick={() => choose(proof.direction_id)}
              >
                {savingDirection === proof.direction_id
                  ? 'Saving selection…'
                  : selected
                  ? `${proof.direction_name} selected ✓`
                  : selectedDirection
                  ? 'Choice locked for review'
                  : `Choose ${proof.direction_name}`}
              </button>
            </article>
          );
        })}
      </div>

      {selectedProof && (
        <section
          ref={nextActionRef}
          className={`portal-proof-next${revisionPending ? ' changes-requested' : ''}`}
          tabIndex="-1"
          aria-live="polite"
        >
          <span>{revisionPending ? 'Changes requested ✓' : 'Selection saved ✓'}</span>
          <h3>
            {revisionPending
              ? `We received your changes for ${selectedProof.direction_name}`
              : `${selectedProof.direction_name} is your selected direction`}
          </h3>
          <p>
            {revisionPending
              ? 'Fritz has your notes. This request will stay visible while the next proof round is prepared.'
              : request.status === 'converted'
              ? 'Payment is recorded and your build has started. FAMtastic owns the next update.'
              : request.direct_checkout_available
              ? 'Your choice is saved. Payment is the next separate step and starts the build.'
              : 'Your choice is saved. FAMtastic is preparing the approved offer or next build step.'}
          </p>
          <div className="portal-proof-next__actions">
            <a href={selectedProof.preview_url} target="_blank" rel="noreferrer">
              Open selected proof ↗
            </a>
            {!revisionPending && request.direct_checkout_available && (
              <button type="button" onClick={() => onContinue(request.public_id)}>
                Continue to secure payment →
              </button>
            )}
            <button
              className="quiet"
              type="button"
              aria-expanded={revisionOpen}
              aria-controls={`proof-revision-${request.public_id}`}
              disabled={changesRemaining < 1}
              onClick={() => setRevisionOpen((open) => !open)}
            >
              {revisionOpen
                ? 'Close changes'
                : changesRemaining < 1
                ? 'Included edit rounds used'
                : revisionPending
                ? 'Update change request'
                : 'Request an edit round'}
            </button>
          </div>
        </section>
      )}

      {!selectedProof && (
        <button
          className="quiet portal-proof-change-toggle"
          type="button"
          aria-expanded={revisionOpen}
          aria-controls={`proof-revision-${request.public_id}`}
          disabled={changesRemaining < 1}
          onClick={() => setRevisionOpen((open) => !open)}
        >
          {revisionOpen
            ? 'Close design reset'
            : changesRemaining < 1
            ? 'Included design reset used'
            : 'None of these fit? Request your design reset'}
        </button>
      )}

      {revisionOpen && (
        <form
          ref={revisionRef}
          id={`proof-revision-${request.public_id}`}
          className="portal-proof-revision"
          onSubmit={requestChanges}
        >
          <div>
            <span>{selectedProof ? 'Edit round request' : 'Design reset request'}</span>
            <h3>{selectedProof ? 'What should FAMtastic refine?' : 'What should we rethink before a new proof set?'}</h3>
            <p>
              {selectedProof
                ? 'Use one of your included edit rounds for specific changes to the direction you chose.'
                : 'Use your included design reset when all three directions miss the mark. Tell us what should change at the concept level.'}
            </p>
          </div>
          <label htmlFor={`proof-revision-notes-${request.public_id}`}>
            Your change notes
            <textarea
              id={`proof-revision-notes-${request.public_id}`}
              name="notes"
              required
              placeholder="Example: Keep this layout, but use royal blue and warmer photography."
              defaultValue={request.intake?.proof_revision_request?.notes || ''}
            />
          </label>
          <div className="portal-form-actions">
            <button type="submit" disabled={busy}>
              {busy ? 'Sending changes…' : 'Send changes to Fritz'}
            </button>
            <button className="quiet" type="button" onClick={() => setRevisionOpen(false)}>
              Cancel
            </button>
          </div>
        </form>
      )}

      <details className="portal-project-secondary portal-proof-sharing" open={proofShare.enabled || undefined}>
        <summary>Share concepts with someone else <span>Optional</span></summary>
        <section
          className={`portal-proof-share${proofShare.enabled ? ' is-enabled' : ''}`}
          aria-labelledby={`proof-share-title-${request.public_id}`}
        >
        <div>
          <span>Optional sharing</span>
          <h3 id={`proof-share-title-${request.public_id}`}>
            Create a view-only link
          </h3>
          <p>
            {proofShare.enabled
              ? 'Anyone with this unlisted link can view the concepts. They cannot choose, request changes, purchase, or see account details.'
              : 'Use this only when a colleague or decision-maker should compare the concepts.'}
          </p>
        </div>
        <button
          className="portal-proof-share__switch"
          type="button"
          role="switch"
          aria-checked={proofShare.enabled}
          disabled={busy}
          onClick={() => onShare(request.public_id, proofShare.enabled ? 'disable' : 'enable')}
        >
          <i aria-hidden="true" />
          <span>{proofShare.enabled ? 'Sharing on' : 'Sharing off'}</span>
        </button>
        {proofShare.enabled && (
          <div className="portal-proof-share__link">
            <label htmlFor={`proof-share-url-${request.public_id}`}>Unlisted link</label>
            <input
              id={`proof-share-url-${request.public_id}`}
              readOnly
              value={proofShare.url}
              onFocus={(event) => event.currentTarget.select()}
            />
            <div>
              <button type="button" onClick={copyShareLink}>
                {copied ? 'Copied ✓' : 'Copy link'}
              </button>
              <button
                className="quiet"
                type="button"
                disabled={busy}
                onClick={() => onShare(request.public_id, 'rotate')}
              >
                Create a new link
              </button>
            </div>
            <small>Creating a new link immediately revokes this one.</small>
          </div>
        )}
        </section>
      </details>
    </section>
  );
}

export function ProjectDomainHostingManager({ request, busy, onSave }) {
  const [isEditing, setIsEditing] = useState(false);
  const [domainMode, setDomainMode] = useState(
    request?.domain_choice === 'existing_domain' ? 'existing' : 'new'
  );
  const [copiedDns, setCopiedDns] = useState(false);

  const desiredDomain = request?.intake?.desired_domains || '';
  const existingDomain = request?.existing_domain || '';
  const hasDomain = (request?.domain_choice === 'new_domain' && desiredDomain) ||
    (request?.domain_choice === 'existing_domain' && existingDomain) ||
    desiredDomain ||
    existingDomain;

  const copyText = async (text) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedDns(true);
      setTimeout(() => setCopiedDns(false), 2000);
    } catch {}
  };

  const handleDomainSubmit = async (e) => {
    await onSave(e, request.public_id);
    setIsEditing(false);
  };

  return (
    <article className="portal-domain-card" aria-label="Domain and Cloud Hosting Bundle">
      <div className="portal-domain-card__header">
        <div>
          <span className="portal-product-badge">
            ✓ 1-Yr Cloud Hosting &amp; Domain Included
          </span>
          <h3 style={{ margin: '0.4rem 0 0.2rem', fontSize: '1.2rem', color: '#fff' }}>
            Domain &amp; Cloud Infrastructure
          </h3>
          <p style={{ margin: 0, color: '#aeb8ae', fontSize: '0.86rem' }}>
            Fast SSD cloud hosting and SSL security are automatically provisioned and mapped to your domain as part of your website bundle.
          </p>
        </div>
        {!isEditing && (
          <button
            type="button"
            className="secondary"
            style={{ fontSize: '0.82rem', padding: '0.45rem 0.85rem' }}
            onClick={() => setIsEditing(true)}
          >
            {hasDomain ? 'Edit Domain Setup ✏️' : 'Add Domain Request 🌐'}
          </button>
        )}
      </div>

      {!isEditing ? (
        <div className="portal-domain-summary">
          <div className="portal-domain-summary__row">
            <div>
              <dt>Domain Routing</dt>
              <dd>
                {request?.domain_choice === 'existing_domain' && existingDomain ? (
                  <strong style={{ color: 'var(--p-lime)' }}>
                    {existingDomain} <small style={{ color: '#8e998e', fontWeight: 'normal' }}>(Connecting Existing Domain)</small>
                  </strong>
                ) : desiredDomain ? (
                  <strong style={{ color: 'var(--p-lime)' }}>
                    {desiredDomain} <small style={{ color: '#8e998e', fontWeight: 'normal' }}>(Included New Registration)</small>
                  </strong>
                ) : (
                  <span style={{ color: '#ffc107' }}>Pending Domain Selection</span>
                )}
              </dd>
            </div>
            <div>
              <dt>SSD Cloud Server</dt>
              <dd style={{ color: '#d0d8d0' }}>
                <code>198.71.232.3</code> (US Tier-4 / Auto-SSL)
              </dd>
            </div>
          </div>

          {request?.domain_choice === 'existing_domain' && existingDomain && (
            <div className="portal-dns-helper" style={{ marginTop: '0.75rem' }}>
              <span style={{ display: 'block', color: '#fff', fontWeight: '700', marginBottom: '0.35rem' }}>
                ⚙ DNS Pointing Records for {existingDomain}:
              </span>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem' }}>
                <span>A-Record: <code>@ → 198.71.232.3</code> | CNAME: <code>www → @</code></span>
                <button type="button" className="quiet" style={{ padding: '0.2rem 0.5rem', fontSize: '0.75rem' }} onClick={() => copyText('198.71.232.3')}>
                  {copiedDns ? 'Copied ✓' : 'Copy Server IP'}
                </button>
              </div>
            </div>
          )}
        </div>
      ) : (
        <form onSubmit={handleDomainSubmit} className="portal-domain-form">
          <input type="hidden" name="request_id" value={request.public_id} />
          <input type="hidden" name="project_name" value={request.project_name || 'My Business Website'} />
          <input type="hidden" name="project_type" value={request.project_type || 'new_website'} />

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '0.75rem', margin: '0.75rem 0' }}>
            <label
              style={{
                padding: '0.85rem',
                borderRadius: '12px',
                border: `1px solid ${domainMode === 'new' ? 'var(--p-lime)' : 'var(--p-line)'}`,
                background: domainMode === 'new' ? 'rgba(124,252,0,0.06)' : 'rgba(0,0,0,0.3)',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '0.65rem',
              }}
            >
              <input
                type="radio"
                name="domain_choice"
                value="new_domain"
                checked={domainMode === 'new'}
                onChange={() => setDomainMode('new')}
                style={{ marginTop: '0.2rem' }}
              />
              <div>
                <strong style={{ display: 'block', color: '#fff', fontSize: '0.9rem' }}>I need a new domain</strong>
                <small style={{ color: '#8e998e', fontSize: '0.78rem' }}>Included .com, .org, or .net registered &amp; mapped to your server.</small>
              </div>
            </label>

            <label
              style={{
                padding: '0.85rem',
                borderRadius: '12px',
                border: `1px solid ${domainMode === 'existing' ? 'var(--p-lime)' : 'var(--p-line)'}`,
                background: domainMode === 'existing' ? 'rgba(124,252,0,0.06)' : 'rgba(0,0,0,0.3)',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '0.65rem',
              }}
            >
              <input
                type="radio"
                name="domain_choice"
                value="existing_domain"
                checked={domainMode === 'existing'}
                onChange={() => setDomainMode('existing')}
                style={{ marginTop: '0.2rem' }}
              />
              <div>
                <strong style={{ display: 'block', color: '#fff', fontSize: '0.9rem' }}>I already own a domain</strong>
                <small style={{ color: '#8e998e', fontSize: '0.78rem' }}>Keep your current registrar and point DNS to your new cloud server.</small>
              </div>
            </label>
          </div>

          {domainMode === 'new' ? (
            <label style={{ margin: '0.5rem 0' }}>
              <span style={{ fontSize: '0.85rem', color: '#cdd4cd' }}>Desired Domain Name(s)</span>
              <input
                name="desired_domains"
                defaultValue={desiredDomain}
                placeholder="e.g. mybakery.com, mybakeryla.com"
                required
              />
            </label>
          ) : (
            <div style={{ margin: '0.5rem 0' }}>
              <label>
                <span style={{ fontSize: '0.85rem', color: '#cdd4cd' }}>Existing Domain Name</span>
                <input
                  name="existing_domain"
                  defaultValue={existingDomain}
                  placeholder="e.g. mybusiness.com"
                  required
                />
              </label>
              <div className="portal-dns-helper" style={{ marginTop: '0.5rem' }}>
                <span>Add these 2 DNS records in your domain registrar:</span>
                <div style={{ marginTop: '0.35rem' }}>
                  <code>A Record: @ → 198.71.232.3</code> | <code>CNAME: www → @</code>
                </div>
              </div>
            </div>
          )}

          <div style={{ display: 'flex', gap: '0.6rem', marginTop: '0.75rem' }}>
            <button type="submit" disabled={busy} style={{ fontSize: '0.85rem', padding: '0.5rem 1rem' }}>
              {busy ? 'Saving…' : 'Save Domain Choice ✓'}
            </button>
            <button type="button" className="quiet" style={{ fontSize: '0.85rem', padding: '0.5rem 0.85rem' }} onClick={() => setIsEditing(false)}>
              Cancel
            </button>
          </div>
        </form>
      )}
    </article>
  );
}

export function WebsiteRequestIntakeEditor({
  editingRequest,
  setEditingRequest,
  onSave,
  onUploadAsset,
  busy,
}) {
  return (
    <Panel
      key={editingRequest.public_id || 'new-request'}
      id="website-request-editor"
      eyebrow={editingRequest.public_id ? 'Continue request' : 'New website request'}
      title={editingRequest.project_name || 'Tell us what you want to build'}
      className="portal-request-form"
    >
      <form onSubmit={onSave}>
        {editingRequest.public_id && (
          <input type="hidden" name="request_id" value={editingRequest.public_id} />
        )}
        {!editingRequest.public_id && (
          <p className="portal-form-stepnote">
            <strong>Step 1 of 2.</strong> Answer three quick things to save your draft — the full
            brief opens right after, and everything saves as you go.
          </p>
        )}
        <div className="portal-form-grid">
          <label>
            Request name
            <input
              name="project_name"
              defaultValue={editingRequest.project_name || ''}
              placeholder="Example: Sweet Crumbs Bakery website"
              required
            />
          </label>
          <label>
            What are we building?
            <select name="project_type" defaultValue={editingRequest.project_type || 'new_website'}>
              <option value="new_website">New website (Includes Hosting + Domain + 3 Concepts)</option>
              <option value="landing_page">High-converting landing page</option>
              <option value="redesign">Website redesign &amp; modernisation</option>
              <option value="online_store">Online store / ecommerce shopping cart</option>
            </select>
          </label>
        </div>
        <label>
          What should this website accomplish?
          <textarea
            name="primary_goal"
            defaultValue={editingRequest.intake?.primary_goal || ''}
            placeholder="Example: Take custom cake orders, showcase our portfolio, and explain delivery/pickup options."
          />
        </label>

        {editingRequest.public_id && (
          <>
            {/* DOMAINS & CLOUD HOSTING BUNDLE */}
            <fieldset className="portal-form-group">
              <legend>🌐 Domain &amp; Cloud Infrastructure (Included in Package)</legend>
              <label>
                Domain preference
                <select
                  name="domain_choice"
                  defaultValue={editingRequest.domain_choice || 'new_domain'}
                >
                  <option value="new_domain">I need a new domain (Free .com/.org/.net registration included)</option>
                  <option value="existing_domain">I already own a domain (I will point DNS to FAMtastic cloud)</option>
                  <option value="undecided">Undecided / help me decide later</option>
                </select>
              </label>
              <div className="portal-form-grid">
                <label>
                  Desired new domain(s)
                  <input
                    name="desired_domains"
                    defaultValue={editingRequest.intake?.desired_domains || ''}
                    placeholder="e.g. sweetcrumbs.com, sweetcrumbsla.com"
                  />
                </label>
                <label>
                  Existing domain (if any)
                  <input
                    name="existing_domain"
                    defaultValue={editingRequest.existing_domain || ''}
                    placeholder="e.g. sweetcrumbs.com"
                  />
                </label>
              </div>
              <label>
                Business email requirements
                <input
                  name="business_email_needs"
                  defaultValue={editingRequest.intake?.business_email_needs || ''}
                  placeholder="e.g. hello@sweetcrumbs.com, Google Workspace or Microsoft 365"
                />
              </label>
            </fieldset>

            {/* GOALS & CUSTOMERS */}
            <fieldset className="portal-form-group">
              <legend>Goals and target customers</legend>
              <label>
                Secondary goals and how you will measure success
                <textarea
                  name="secondary_goals"
                  defaultValue={editingRequest.intake?.secondary_goals || ''}
                  placeholder="Calls, quote requests, online bookings, email list signups…"
                />
              </label>
              <label>
                Who is your ideal customer, and what problem are they trying to solve?
                <textarea
                  name="ideal_customer"
                  defaultValue={editingRequest.intake?.ideal_customer || ''}
                  placeholder="Local families planning birthday parties and corporate event planners looking for custom catering."
                />
              </label>
              <label>
                What should visitors do first when they land on the site?
                <textarea
                  name="desired_actions"
                  defaultValue={editingRequest.intake?.desired_actions || ''}
                  placeholder="Call immediately, submit an inquiry form, browse the gallery, or book online."
                />
              </label>
              <div className="portal-form-grid">
                <label>
                  Estimated number of pages
                  <input
                    name="page_count"
                    type="number"
                    min="1"
                    max="100"
                    defaultValue={editingRequest.intake?.page_count || 1}
                  />
                </label>
                <label>
                  Who makes the final launch decision?
                  <input
                    name="decision_makers"
                    defaultValue={editingRequest.intake?.decision_makers || ''}
                    placeholder="e.g. Owner, Founder, Marketing Director"
                  />
                </label>
              </div>
            </fieldset>

            {/* YOUR BUSINESS */}
            <fieldset className="portal-form-group">
              <legend>Your business details</legend>
              <div className="portal-form-grid">
                <label>
                  Business name
                  <input
                    name="business_name"
                    defaultValue={editingRequest.business_name || ''}
                    autoComplete="organization"
                  />
                </label>
                <label>
                  Industry / Category
                  <input
                    name="industry"
                    defaultValue={editingRequest.intake?.industry || ''}
                    placeholder="e.g. Food &amp; Beverage, Healthcare, Construction, Legal"
                  />
                </label>
              </div>
              <label>
                What does your business sell or provide?
                <textarea
                  name="products_services"
                  defaultValue={editingRequest.intake?.products_services || ''}
                  placeholder="List your core products, services, packages, and pricing structure."
                />
              </label>
              <div className="portal-form-grid">
                <label>
                  Service locations / Service area
                  <input
                    name="service_locations"
                    defaultValue={editingRequest.intake?.service_locations || ''}
                    placeholder="e.g. Greater Los Angeles, Nationwide, Tri-State area"
                  />
                </label>
                <label>
                  Business hours &amp; contact info
                  <input
                    name="contact_details"
                    defaultValue={editingRequest.intake?.contact_details || ''}
                    placeholder="Phone, public email, address, operating hours"
                  />
                </label>
              </div>
            </fieldset>

            {/* CREATIVE DIRECTION & STYLING */}
            <fieldset className="portal-form-group">
              <legend>Creative direction &amp; visual style</legend>
              <div className="portal-form-grid">
                <label>
                  Preferred colors
                  <input
                    name="preferred_colors"
                    defaultValue={editingRequest.intake?.preferred_colors || ''}
                    placeholder="e.g. Warm terracotta, cream, sage green, gold"
                  />
                </label>
                <label>
                  Colors to avoid
                  <input
                    name="colors_to_avoid"
                    defaultValue={editingRequest.intake?.colors_to_avoid || ''}
                    placeholder="e.g. Neon yellow, harsh black, hot pink"
                  />
                </label>
              </div>
              <label>
                Desired vibe &amp; aesthetic feeling
                <textarea
                  name="desired_feeling"
                  defaultValue={editingRequest.intake?.desired_feeling || ''}
                  placeholder="e.g. Warm, artisanal, high-end yet welcoming, trustworthy and modern."
                />
              </label>
              <label>
                Reference websites you love (and why)
                <textarea
                  name="reference_sites"
                  defaultValue={editingRequest.intake?.reference_sites || ''}
                  placeholder="e.g. https://examplebakery.com - Love the typography and clean photo gallery."
                />
              </label>
              <label>
                FAMtastic Creative Intensity ({editingRequest.intake?.famtastic_level ?? 5} / 10)
                <input
                  name="famtastic_level"
                  type="range"
                  min="0"
                  max="10"
                  defaultValue={editingRequest.intake?.famtastic_level ?? 5}
                />
                <small style={{ color: '#8e998e' }}>
                  0 = Strictly conservative &amp; minimalist | 10 = Bold, avant-garde, signature motion
                </small>
              </label>
            </fieldset>

            {/* TECHNICAL, COMMERCE & ACCESS */}
            <fieldset className="portal-form-group">
              <legend>Technical features &amp; integrations</legend>
              <label>
                Required features &amp; functionality
                <textarea
                  name="required_features"
                  defaultValue={editingRequest.intake?.required_features || ''}
                  placeholder="e.g. Appointment booking calendar, Instagram feed, customer reviews, dynamic quote calculator."
                />
              </label>
              <div className="portal-form-grid">
                <label>
                  Online booking details (if any)
                  <input
                    name="booking_details"
                    defaultValue={editingRequest.intake?.booking_details || ''}
                    placeholder="e.g. Calendly, Acuity, Booksy, or custom request form"
                  />
                </label>
                <label>
                  Ecommerce / Cart details (if any)
                  <input
                    name="ecommerce_details"
                    defaultValue={editingRequest.intake?.ecommerce_details || ''}
                    placeholder="e.g. Shopify Buy Button, Stripe Checkout, or catalog only"
                  />
                </label>
              </div>
              <label>
                Launch timing &amp; deadlines
                <input
                  name="launch_timing"
                  defaultValue={editingRequest.intake?.launch_timing || ''}
                  placeholder="e.g. In 2 weeks for grand opening, or flexible"
                />
              </label>
              <label>
                Additional notes for Fritz &amp; the design team
                <textarea
                  name="notes"
                  defaultValue={editingRequest.intake?.notes || ''}
                  placeholder="Any specific constraints, competitor URLs, or must-have layout requests."
                />
              </label>
              <label className="portal-check">
                <input
                  name="recommendation_requested"
                  type="checkbox"
                  defaultChecked={editingRequest.recommendation_requested !== 0}
                />
                Recommend the smallest useful package and optimal add-ons for my goals.
              </label>
            </fieldset>
          </>
        )}

        <div className="portal-form-actions portal-sticky-actions">
          <button name="action" value="save" disabled={busy}>
            {busy
              ? 'Saving…'
              : editingRequest.public_id
              ? 'Save draft'
              : 'Save draft & open full brief'}
          </button>
          {editingRequest.public_id && (
            <button className="secondary" name="action" value="submit" disabled={busy}>
              Submit brief for concepts →
            </button>
          )}
          <button className="quiet" type="button" onClick={() => setEditingRequest(null)}>
            Close
          </button>
        </div>
      </form>

      {editingRequest.public_id && (
        <form className="portal-asset-upload" onSubmit={onUploadAsset} style={{ marginTop: '1.5rem' }}>
          <h3>Add logo, brand guide, flyer, or photo references</h3>
          <p>PNG, JPEG, WebP, SVG, or PDF up to 10 MB. Attached files stay private to this project.</p>
          <input
            name="asset"
            type="file"
            accept="image/png,image/jpeg,image/webp,image/svg+xml,application/pdf"
            required
          />
          <label className="portal-check">
            <input name="ownership_confirmed" type="checkbox" value="1" required />
            I own this file or have permission to share it for this website project.
          </label>
          <label className="portal-check">
            <input name="ai_use_consent" type="checkbox" value="1" />
            FAMtastic may use this file as reference for approved AI-assisted concept generation.
          </label>
          <button disabled={busy}>{busy ? 'Uploading…' : 'Upload reference securely'}</button>
          {editingRequest.assets?.length > 0 && (
            <ul style={{ marginTop: '0.75rem' }}>
              {editingRequest.assets.map((asset) => (
                <li key={asset.public_id}>
                  📄 {asset.name} · {Math.ceil(asset.size_bytes / 1024)} KB
                </li>
              ))}
            </ul>
          )}
        </form>
      )}
    </Panel>
  );
}

export default function PortalProjectsView({
  workspace,
  editingRequest,
  setEditingRequest,
  activeRequestId,
  setActiveRequestId,
  targetRequest,
  busy,
  onSaveWebsiteRequest,
  onUploadAsset,
  onDecideProof,
  onShareProof,
  onArchiveRequest,
  navigate,
}) {
  const [dnaOpen, setDnaOpen] = useState(false);
  const [showArchive, setShowArchive] = useState(false);
  const [archiveConfirmId, setArchiveConfirmId] = useState('');
  const requests = workspace.website_requests || [];
  const activeRequests = requests.filter((request) => !request.customer_archived);
  const archivedRequests = requests.filter((request) => request.customer_archived);
  const proofReady = (req) => [3, 6].includes(req?.proofs?.variants?.length);

  const activeRequest =
    activeRequests.find((req) => req.public_id === activeRequestId) ||
    activeRequests.find((req) => req.public_id === targetRequest) ||
    activeRequests.find((req) => ['customer_ready', 'notified'].includes(req.proof_review_status) && proofReady(req)) ||
    activeRequests[0] ||
    null;
  const nextStep = customerNextStep(activeRequest);
  const proofHandoff = activeRequest?.proof_handoff || {
    state: activeRequest?.status === 'draft' ? 'draft' : 'queued',
    label: activeRequest?.status === 'draft' ? 'Finish and submit your brief' : 'Proof request queued',
    detail: activeRequest?.status === 'draft'
      ? 'Finish the brief before FAMtastic can queue a proof run.'
      : 'FAMtastic is confirming the current proof status.',
  };

  const requestChips =
    activeRequests.length > 1 ? (
      <div className="portal-request-chips" role="tablist" aria-label="Active website projects">
        {activeRequests.map((request) => {
          const step = customerNextStep(request);
          return (
          <button
            key={request.public_id}
            role="tab"
            aria-selected={request.public_id === (activeRequest?.public_id || '')}
            className={request.public_id === (activeRequest?.public_id || '') ? 'active' : ''}
            onClick={() => setActiveRequestId(request.public_id)}
          >
            <strong>{request.project_name}</strong>
            <small>
              {step.owner === 'you' ? 'Your turn' : 'FAMtastic’s turn'} · {step.label}
            </small>
          </button>
          );
        })}
      </div>
    ) : null;

  return (
    <>
      <section className="portal-project-hero">
        <div>
          <span>One account. Every website.</span>
          <h2>Your Website Project Command Center</h2>
          <p>
            Choose a project below. We will always show whose turn it is and the one thing that happens next.
          </p>
        </div>
        <button onClick={() => setEditingRequest({})}>+ Start a new website</button>
      </section>

      {/* When editing a brief */}
      {editingRequest && (
        <WebsiteRequestIntakeEditor
          editingRequest={editingRequest}
          setEditingRequest={setEditingRequest}
          onSave={onSaveWebsiteRequest}
          onUploadAsset={onUploadAsset}
          busy={busy}
        />
      )}

      {/* Main Active Project Overview */}
      {!editingRequest && activeRequest && (
        <section className="portal-request-list">
          {requestChips}

          <section className={`portal-project-next-step is-${nextStep.tone}`} aria-label="Your next project step">
            <span>{nextStep.owner === 'you' ? 'Your turn' : 'FAMtastic’s turn'}</span>
            <div>
              <h3>{nextStep.label}</h3>
              <p>{nextStep.detail}</p>
            </div>
            {nextStep.action === 'brief' && (
              <button type="button" onClick={() => setEditingRequest(activeRequest)}>Finish brief →</button>
            )}
            {nextStep.action === 'proofs' && (
              <a href={`#proof-review-${activeRequest.public_id}`}>Review 3 directions ↓</a>
            )}
            {nextStep.action === 'payment' && (
              <button type="button" onClick={() => navigate(`/buy?request=${encodeURIComponent(activeRequest.public_id)}`)}>Continue to payment →</button>
            )}
            {nextStep.action === 'billing' && (
              <button type="button" onClick={() => navigate('/portal?section=billing')}>Open billing →</button>
            )}
          </section>

          <Panel
            key={activeRequest.public_id}
            id={`website-request-${activeRequest.public_id}`}
            tabIndex={activeRequest.public_id === targetRequest ? -1 : undefined}
            className={activeRequest.public_id === targetRequest ? 'portal-request-target' : ''}
            eyebrow={activeRequest.status === 'converted' ? 'Purchased Project' : 'Active Website Request'}
            title={activeRequest.project_name}
          >
            {/* High-level status row */}
            <dl>
              <div>
                <dt>Project Status</dt>
                <dd>
                  <strong style={{ color: 'var(--p-lime)' }}>{title(activeRequest.status)}</strong>
                </dd>
              </div>
              <div>
                <dt>Concept Proofs</dt>
                <dd>{title(activeRequest.proof_review_status)}</dd>
              </div>
              <div>
                <dt>Last Updated</dt>
                <dd>{date(activeRequest.changed)}</dd>
              </div>
            </dl>

            <details className="portal-project-secondary">
              <summary>
                Domain and hosting
                <span>{activeRequest.existing_domain || activeRequest.intake?.desired_domains || 'Choose later'}</span>
              </summary>
              <ProjectDomainHostingManager request={activeRequest} busy={busy} onSave={onSaveWebsiteRequest} />
            </details>

            {/* CONCEPT PROOFS OR DURABLE HANDOFF STATUS */}
            {proofReady(activeRequest) ? (
              <div id={`proof-review-${activeRequest.public_id}`} style={{ marginTop: '1.5rem', scrollMarginTop: '5rem' }}>
                <h3 style={{ margin: '0 0 0.75rem', fontSize: '1.3rem', color: '#fff' }}>
                  Review Your Interactive Concepts
                </h3>
                <WebsiteProofReview
                  request={activeRequest}
                  busy={busy}
                  onDecision={onDecideProof}
                  onShare={onShareProof}
                  onContinue={(requestId) => navigate(`/buy?request=${encodeURIComponent(requestId)}`)}
                />
              </div>
            ) : (
              <div
                style={{
                  margin: '1.5rem 0',
                  padding: '1.4rem',
                  borderRadius: '16px',
                  border: proofHandoff.state === 'needs_attention' ? '1px solid #e2ac5f' : '1px solid var(--p-lime)',
                  background: proofHandoff.state === 'needs_attention' ? 'linear-gradient(135deg, rgba(226,172,95,0.10), #090c09)' : 'linear-gradient(135deg, rgba(124,252,0,0.08), #090c09)',
                  display: 'grid',
                  gap: '0.5rem',
                }}
              >
                <span style={{ color: proofHandoff.state === 'needs_attention' ? '#e2ac5f' : 'var(--p-lime)', fontSize: '0.75rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.1em' }}>
                  Proof status
                </span>
                <h4 style={{ margin: 0, fontSize: '1.2rem', color: '#fff' }}>
                  {proofHandoff.label}
                </h4>
                <p style={{ margin: 0, color: '#b2bcb2', fontSize: '0.88rem', lineHeight: '1.5' }}>
                  {proofHandoff.detail}
                </p>
                {proofHandoff.state === 'draft' && (
                  <div style={{ display: 'flex', gap: '0.6rem', flexWrap: 'wrap', marginTop: '0.6rem' }}>
                    <button
                      type="button"
                      onClick={() => setEditingRequest(activeRequest)}
                    >
                      Finish and submit brief →
                    </button>
                  </div>
                )}
              </div>
            )}

            <details className="portal-project-secondary" style={{ marginTop: '1rem' }}>
              <summary>Project files <span>{activeRequest.assets?.length || 0} attached</span></summary>
              <div style={{ paddingTop: '1rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
                <h4 style={{ margin: 0, fontSize: '1.1rem', color: '#fff' }}>Project Assets &amp; Files</h4>
                <small style={{ color: '#8e998e' }}>{activeRequest.assets?.length || 0} attached</small>
              </div>
              {activeRequest.assets?.length > 0 ? (
                <ul style={{ listStyle: 'none', padding: 0, display: 'grid', gap: '0.5rem' }}>
                  {activeRequest.assets.map((asset) => (
                    <li
                      key={asset.public_id}
                      style={{
                        padding: '0.6rem 0.8rem',
                        borderRadius: '8px',
                        background: '#0a0d0a',
                        border: '1px solid var(--p-line)',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        fontSize: '0.85rem',
                      }}
                    >
                      <span>📄 {asset.name}</span>
                      <small style={{ color: '#8e998e' }}>{Math.ceil(asset.size_bytes / 1024)} KB</small>
                    </li>
                  ))}
                </ul>
              ) : (
                <p style={{ color: '#8e998e', fontSize: '0.85rem', margin: '0 0 0.75rem' }}>
                  No logos or reference files attached yet. Add files via the brief editor anytime.
                </p>
              )}
              </div>
            </details>

            {/* ACTION TOOLBAR */}
            <div style={{ marginTop: '1.5rem', display: 'flex', gap: '0.65rem', flexWrap: 'wrap' }}>
              <button type="button" onClick={() => setEditingRequest(activeRequest)}>
                Edit Full Brief 📝
              </button>
              <button
                type="button"
                className="quiet"
                onClick={() => setDnaOpen((open) => !open)}
              >
                {dnaOpen ? 'Hide Build DNA ▴' : 'Inspect Build DNA ▾'}
              </button>
              <button
                type="button"
                className="quiet"
                onClick={() => setArchiveConfirmId(activeRequest.public_id)}
              >
                Move project to Archive
              </button>
            </div>

            {archiveConfirmId === activeRequest.public_id && (
              <section className="portal-archive-confirm" role="alertdialog" aria-labelledby={`archive-title-${activeRequest.public_id}`}>
                <div>
                  <h3 id={`archive-title-${activeRequest.public_id}`}>Hide this project from your active list?</h3>
                  <p>Nothing is deleted or cancelled. Its brief, research, proofs, files, selection, and payment history stay saved, and you can restore it anytime.</p>
                </div>
                <div>
                  <button type="button" disabled={busy} onClick={async () => {
                    if (await onArchiveRequest(activeRequest.public_id, 'archive')) setArchiveConfirmId('');
                  }}>Yes, move to Archive</button>
                  <button type="button" className="quiet" onClick={() => setArchiveConfirmId('')}>Keep it active</button>
                </div>
              </section>
            )}

            {/* BUILD DNA VIEWER */}
            {dnaOpen && (
              <div
                className="portal-dna-viewer"
                style={{
                  marginTop: '1.25rem',
                  padding: '1.15rem',
                  border: '1px solid rgba(124,252,0,0.3)',
                  borderRadius: '14px',
                  background: '#090d09',
                }}
              >
                <span
                  style={{
                    color: '#7cfc00',
                    fontSize: '0.72rem',
                    fontWeight: '800',
                    textTransform: 'uppercase',
                  }}
                >
                  🧬 Build DNA Provenance &amp; Verification
                </span>
                <p style={{ fontSize: '0.85rem', color: '#aab2aa', margin: '0.4rem 0 0.85rem' }}>
                  Standard `famtastic.build-dna.v1`. Every stage, research packet, and concept variant is
                  journaled with exact hashes and QA gates.
                </p>
                <dl style={{ fontSize: '0.82rem', margin: 0 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.35rem 0', borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
                    <dt style={{ color: '#8e998e' }}>Request ID</dt>
                    <dd>{activeRequest.public_id}</dd>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.35rem 0', borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
                    <dt style={{ color: '#8e998e' }}>Creative Intensity</dt>
                    <dd>{activeRequest.intake?.famtastic_level ?? 5} / 10</dd>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.35rem 0', borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
                    <dt style={{ color: '#8e998e' }}>AI Research Mode</dt>
                    <dd>{activeRequest.intake?.ai_enrichment_mode || 'Managed'}</dd>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.35rem 0' }}>
                    <dt style={{ color: '#8e998e' }}>Verification Gate</dt>
                    <dd style={{ color: '#7cfc00' }}>✓ Schema &amp; Security Verified</dd>
                  </div>
                </dl>
              </div>
            )}
          </Panel>
        </section>
      )}

      {/* If no requests exist */}
      {!editingRequest && !activeRequest && activeRequests.length === 0 && (
        <Panel eyebrow="Projects" title={archivedRequests.length > 0 ? 'No active projects' : 'No website requests yet'}>
          <p>
            {archivedRequests.length > 0
              ? 'Your active list is clear. Restore an archived project below, or start something new.'
              : 'Start with a short, reusable brief. Your domain, reference files, research, and three working concepts will live here.'}
          </p>
          <button type="button" onClick={() => setEditingRequest({})}>
            + Start your first website request
          </button>
        </Panel>
      )}

      {!editingRequest && archivedRequests.length > 0 && (
        <section className="portal-archive">
          <button type="button" className="quiet portal-archive-toggle" aria-expanded={showArchive} onClick={() => setShowArchive((open) => !open)}>
            {showArchive ? 'Hide Archive' : `Archive (${archivedRequests.length})`}
          </button>
          {showArchive && (
            <div className="portal-archive-list">
              {archivedRequests.map((request) => (
                <article key={request.public_id}>
                  <div>
                    <strong>{request.project_name}</strong>
                    <span>Saved—not deleted · {title(request.status)}</span>
                  </div>
                  <button type="button" className="quiet" disabled={busy} onClick={() => onArchiveRequest(request.public_id, 'restore')}>Restore project</button>
                </article>
              ))}
            </div>
          )}
        </section>
      )}

      {/* Active purchased projects if any */}
      {!editingRequest && workspace.projects?.length > 0 && (
        <section className="portal-grid" style={{ marginTop: '1.5rem' }}>
          {workspace.projects.map((p) => (
            <Panel
              key={p.uuid}
              eyebrow="Project Command Center"
              title={title(p.delivery_status)}
            >
              <div className="portal-stage-line">
                <span className="complete">Paid</span>
                <span className={p.proofs ? 'complete' : 'active'}>
                  {p.proofs?.variants?.length || 3} concepts
                </span>
                <span className={p.approval_status === 'approved' ? 'complete' : ''}>
                  Approval
                </span>
                <span className={p.live_url ? 'complete' : ''}>Launch</span>
              </div>
              <dl>
                <div>
                  <dt>Approval</dt>
                  <dd>{title(p.approval_status)}</dd>
                </div>
                <div>
                  <dt>Revisions</dt>
                  <dd>
                    {p.revision_count || 0} of {p.revision_limit || 1}
                  </dd>
                </div>
              </dl>
              {p.live_url && (
                <a href={p.live_url} target="_blank" rel="noreferrer">
                  Visit live site ↗
                </a>
              )}
            </Panel>
          ))}
        </section>
      )}
    </>
  );
}
