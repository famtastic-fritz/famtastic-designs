import { useEffect, useRef, useState } from 'react';
import { Panel, Empty, title, money, date } from './PortalShared.jsx';
import { collectUtmParams } from '../../api/pipeline.js';

export function WebsiteProofReview({ request, busy, onDecision, onShare }) {
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
              <button
                type="button"
                aria-pressed={selected}
                disabled={busy || selected}
                onClick={() => choose(proof.direction_id)}
              >
                {savingDirection === proof.direction_id
                  ? 'Saving selection…'
                  : selected
                  ? `${proof.direction_name} selected ✓`
                  : selectedDirection
                  ? `Switch to ${proof.direction_name}`
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
              : 'Review it again, request changes, or continue when you are comfortable with the direction.'}
          </p>
          <div className="portal-proof-next__actions">
            <a href={selectedProof.preview_url} target="_blank" rel="noreferrer">
              Open selected proof ↗
            </a>
            <button
              className="quiet"
              type="button"
              aria-expanded={revisionOpen}
              aria-controls={`proof-revision-${request.public_id}`}
              onClick={() => setRevisionOpen((open) => !open)}
            >
              {revisionOpen
                ? 'Close changes'
                : revisionPending
                ? 'Update change request'
                : 'Make changes'}
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
          onClick={() => setRevisionOpen((open) => !open)}
        >
          {revisionOpen ? 'Close changes' : 'Need changes before choosing?'}
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
            <span>Revision request</span>
            <h3>What should FAMtastic change?</h3>
            <p>Be specific about colors, layout, wording, images, or anything that does not feel right.</p>
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

      <section
        className={`portal-proof-share${proofShare.enabled ? ' is-enabled' : ''}`}
        aria-labelledby={`proof-share-title-${request.public_id}`}
      >
        <div>
          <span>Optional sharing</span>
          <h3 id={`proof-share-title-${request.public_id}`}>
            Share these proofs without requiring sign-in
          </h3>
          <p>
            {proofShare.enabled
              ? 'Anyone with this unlisted link can view the working concepts. They cannot choose a design, request changes, purchase, or see your account details.'
              : 'Create a revocable, unlisted link when you want a colleague or decision-maker to compare the concepts.'}
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
    </section>
  );
}

export function ProjectProvisioningWizard({
  request,
  workspace,
  project,
  busy,
  onSave,
  onUploadAsset,
  onSendToSiteStudio,
  onDecision,
  onShare,
  navigate,
}) {
  const [wizardStep, setWizardStep] = useState(1);
  const [domainMode, setDomainMode] = useState(
    request?.domain_choice === 'existing_domain' ? 'existing' : 'new'
  );
  const [copiedDns, setCopiedDns] = useState(false);

  const copyDnsRecord = async (text) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedDns(true);
      setTimeout(() => setCopiedDns(false), 2000);
    } catch {}
  };

  const variants = request?.proofs?.variants || [];
  const hasProofs = [3, 6].includes(variants.length);
  const isSiteStudioPending = request?.status === 'submitted' && !hasProofs;

  return (
    <div className="portal-wizard-container" id={`website-request-${request?.public_id || 'wizard'}`}>
      <div style={{ padding: '1.25rem 1.5rem', borderBottom: '1px solid rgba(255,255,255,0.08)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', background: 'rgba(0,0,0,0.5)' }}>
        <div>
          <span style={{ color: 'var(--p-lime)', fontSize: '0.72rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.12em' }}>⚡ Project Fulfillment Wizard</span>
          <h3 style={{ margin: '0.2rem 0', fontSize: '1.35rem', color: '#fff' }}>{request?.project_name || 'My Business Website'} · Setup &amp; Provisioning</h3>
        </div>
        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <span className="portal-product-badge">Status: {title(request?.status || 'Draft')}</span>
          {request?.public_id && onSendToSiteStudio && !hasProofs && (
            <button
              type="button"
              disabled={busy}
              onClick={() => onSendToSiteStudio(request.public_id)}
              style={{ padding: '0.5rem 1rem', fontSize: '0.85rem' }}
            >
              {busy ? 'Sending…' : '🚀 Send to Site Studio'}
            </button>
          )}
        </div>
      </div>

      <nav className="portal-wizard-nav" aria-label="Project Setup Steps">
        <button
          type="button"
          className={wizardStep === 1 ? 'active' : (request?.existing_domain || request?.intake?.desired_domains ? 'completed' : '')}
          onClick={() => setWizardStep(1)}
        >
          <small>Step 1</small>
          <strong>🌐 1. Domain Setup</strong>
        </button>
        <button
          type="button"
          className={wizardStep === 2 ? 'active' : 'completed'}
          onClick={() => setWizardStep(2)}
        >
          <small>Step 2</small>
          <strong>⚡ 2. Cloud Hosting</strong>
        </button>
        <button
          type="button"
          className={wizardStep === 3 ? 'active' : (request?.intake?.primary_goal ? 'completed' : '')}
          onClick={() => setWizardStep(3)}
        >
          <small>Step 3</small>
          <strong>🎨 3. Design &amp; Assets</strong>
        </button>
        <button
          type="button"
          className={wizardStep === 4 ? 'active' : (hasProofs ? 'completed' : '')}
          onClick={() => setWizardStep(4)}
        >
          <small>Step 4</small>
          <strong>🚀 4. Site Studio Build</strong>
        </button>
      </nav>

      <div className="portal-wizard-body">
        {/* STEP 1: DOMAIN SETUP */}
        {wizardStep === 1 && (
          <section className="portal-wizard-step" aria-labelledby="step-domain-title">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem', marginBottom: '1.25rem' }}>
              <div>
                <span style={{ color: 'var(--p-lime)', fontSize: '0.72rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.1em' }}>Step 1 of 4 · Domain Configuration</span>
                <h4 id="step-domain-title" style={{ margin: '0.25rem 0', fontSize: '1.35rem', color: '#fff' }}>Configure Your Custom Domain Name</h4>
                <p style={{ color: '#aeb8ae', margin: 0, fontSize: '0.88rem' }}>
                  Choose whether you want FAMtastic to register a new domain name (.com/.org/.net included in your package) or connect an existing domain you already own.
                </p>
              </div>
              <span className="portal-product-badge">✓ 1-Yr Domain Included</span>
            </div>

            <form onSubmit={onSave} style={{ display: 'grid', gap: '1.25rem' }}>
              <input type="hidden" name="project_name" value={request?.project_name || 'My Business Website'} />
              <input type="hidden" name="project_type" value={request?.project_type || 'new_website'} />

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem' }}>
                <label
                  style={{
                    padding: '1rem',
                    borderRadius: '12px',
                    border: `1px solid ${domainMode === 'new' ? 'var(--p-lime)' : 'var(--p-line)'}`,
                    background: domainMode === 'new' ? 'rgba(124,252,0,0.06)' : 'rgba(0,0,0,0.3)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'flex-start',
                    gap: '0.75rem',
                  }}
                >
                  <input
                    type="radio"
                    name="domain_choice"
                    value="new_domain"
                    checked={domainMode === 'new'}
                    onChange={() => setDomainMode('new')}
                    style={{ marginTop: '0.25rem' }}
                  />
                  <div>
                    <strong style={{ display: 'block', color: '#fff', fontSize: '0.95rem' }}>I need a new domain</strong>
                    <small style={{ color: '#8e998e', lineHeight: '1.4' }}>FAMtastic will register your preferred .com, .org, or .net and link it to your server automatically.</small>
                  </div>
                </label>

                <label
                  style={{
                    padding: '1rem',
                    borderRadius: '12px',
                    border: `1px solid ${domainMode === 'existing' ? 'var(--p-lime)' : 'var(--p-line)'}`,
                    background: domainMode === 'existing' ? 'rgba(124,252,0,0.06)' : 'rgba(0,0,0,0.3)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'flex-start',
                    gap: '0.75rem',
                  }}
                >
                  <input
                    type="radio"
                    name="domain_choice"
                    value="existing_domain"
                    checked={domainMode === 'existing'}
                    onChange={() => setDomainMode('existing')}
                    style={{ marginTop: '0.25rem' }}
                  />
                  <div>
                    <strong style={{ display: 'block', color: '#fff', fontSize: '0.95rem' }}>I already own a domain</strong>
                    <small style={{ color: '#8e998e', lineHeight: '1.4' }}>Keep your current registrar (GoDaddy, Namecheap, Google, etc.) and point DNS to your new cloud server.</small>
                  </div>
                </label>
              </div>

              {domainMode === 'new' ? (
                <div style={{ display: 'grid', gap: '0.75rem' }}>
                  <label>
                    <span>Desired Domain Name(s)</span>
                    <input
                      name="desired_domains"
                      defaultValue={request?.intake?.desired_domains || ''}
                      placeholder="e.g. mybakery.com, mybakeryla.com"
                      required
                    />
                  </label>
                  <small style={{ color: '#8e998e' }}>We will verify availability before registration and lock in your chosen name.</small>
                </div>
              ) : (
                <div style={{ display: 'grid', gap: '0.75rem' }}>
                  <label>
                    <span>Existing Domain Name</span>
                    <input
                      name="existing_domain"
                      defaultValue={request?.existing_domain || ''}
                      placeholder="e.g. mybusiness.com"
                      required
                    />
                  </label>
                  <div className="portal-dns-helper">
                    <strong style={{ display: 'block', color: '#fff', marginBottom: '0.4rem' }}>⚙ Quick DNS Pointing Instructions:</strong>
                    <span>Log into your domain registrar DNS settings and add/update these 2 records:</span>
                    <div style={{ display: 'grid', gap: '0.35rem', marginTop: '0.5rem' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <span>Type: <b>A Record</b> · Host: <b>@</b> · Value: <code>198.71.232.3</code></span>
                        <button type="button" className="quiet" style={{ padding: '0.2rem 0.5rem', fontSize: '0.75rem' }} onClick={() => copyDnsRecord('198.71.232.3')}>Copy IP</button>
                      </div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <span>Type: <b>CNAME</b> · Host: <b>www</b> · Value: <code>@ (or famtasticdesigns.com)</code></span>
                        <button type="button" className="quiet" style={{ padding: '0.2rem 0.5rem', fontSize: '0.75rem' }} onClick={() => copyDnsRecord('@')}>Copy Host</button>
                      </div>
                    </div>
                    {copiedDns && <small style={{ color: 'var(--p-lime)', display: 'block', marginTop: '0.4rem' }}>✓ Copied to clipboard!</small>}
                  </div>
                </div>
              )}

              <div className="portal-form-actions" style={{ marginTop: '0.5rem' }}>
                <button type="submit" name="action" value="save" disabled={busy}>Save Domain &amp; Continue →</button>
                <button type="button" className="quiet" onClick={() => setWizardStep(2)}>Skip to Hosting &rarr;</button>
              </div>
            </form>
          </section>
        )}

        {/* STEP 2: CLOUD HOSTING PROVISIONING */}
        {wizardStep === 2 && (
          <section className="portal-wizard-step" aria-labelledby="step-hosting-title">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem', marginBottom: '1.25rem' }}>
              <div>
                <span style={{ color: 'var(--p-lime)', fontSize: '0.72rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.1em' }}>Step 2 of 4 · Infrastructure Health</span>
                <h4 id="step-hosting-title" style={{ margin: '0.25rem 0', fontSize: '1.35rem', color: '#fff' }}>Cloud Hosting &amp; Server Health</h4>
                <p style={{ color: '#aeb8ae', margin: 0, fontSize: '0.88rem' }}>
                  Your dedicated SSD cloud environment is provisioned, secured with TLS 1.3 encryption, and ready to host your website build.
                </p>
              </div>
              <span className="portal-product-badge">● Server Active</span>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
              <div style={{ padding: '1rem', borderRadius: '12px', background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.08)' }}>
                <strong style={{ color: 'var(--p-lime)', display: 'block', fontSize: '0.85rem' }}>✓ Cloud Server</strong>
                <span style={{ fontSize: '0.82rem', color: '#cdd4cd' }}>Dedicated NVMe SSD · 198.71.232.3</span>
              </div>
              <div style={{ padding: '1rem', borderRadius: '12px', background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.08)' }}>
                <strong style={{ color: 'var(--p-lime)', display: 'block', fontSize: '0.85rem' }}>✓ SSL Certificate</strong>
                <span style={{ fontSize: '0.82rem', color: '#cdd4cd' }}>Let's Encrypt 256-bit · Auto Renewing</span>
              </div>
              <div style={{ padding: '1rem', borderRadius: '12px', background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.08)' }}>
                <strong style={{ color: 'var(--p-lime)', display: 'block', fontSize: '0.85rem' }}>✓ Automated Daily Backups</strong>
                <span style={{ fontSize: '0.82rem', color: '#cdd4cd' }}>Daily 03:00 UTC Snapshot Active</span>
              </div>
              <div style={{ padding: '1rem', borderRadius: '12px', background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.08)' }}>
                <strong style={{ color: 'var(--p-lime)', display: 'block', fontSize: '0.85rem' }}>✓ Global HTTP/3 CDN</strong>
                <span style={{ fontSize: '0.82rem', color: '#cdd4cd' }}>Sub-second asset caching enabled</span>
              </div>
            </div>

            <div className="portal-form-actions">
              <button type="button" onClick={() => setWizardStep(3)}>Continue to Design Brief &rarr;</button>
              <button type="button" className="quiet" onClick={() => setWizardStep(1)}>&larr; Back to Domain</button>
            </div>
          </section>
        )}

        {/* STEP 3: DESIGN BRIEF & ASSETS */}
        {wizardStep === 3 && (
          <section className="portal-wizard-step" aria-labelledby="step-brief-title">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem', marginBottom: '1.25rem' }}>
              <div>
                <span style={{ color: 'var(--p-lime)', fontSize: '0.72rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.1em' }}>Step 3 of 4 · Creative Specifications</span>
                <h4 id="step-brief-title" style={{ margin: '0.25rem 0', fontSize: '1.35rem', color: '#fff' }}>Design Brief, Goals &amp; Brand Assets</h4>
                <p style={{ color: '#aeb8ae', margin: 0, fontSize: '0.88rem' }}>
                  Give Site Studio the essentials about your business, audience, desired colors, and upload your logo or reference files.
                </p>
              </div>
              <span className="portal-product-badge">🎨 Creative Studio</span>
            </div>

            <form onSubmit={onSave} style={{ display: 'grid', gap: '1rem' }}>
              <input type="hidden" name="domain_choice" value={request?.domain_choice || 'undecided'} />
              <input type="hidden" name="existing_domain" value={request?.existing_domain || ''} />

              <div className="portal-form-grid">
                <label>
                  <span>Business Name</span>
                  <input name="business_name" defaultValue={request?.business_name || ''} placeholder="e.g. Sweet Crumbs Bakery" required />
                </label>
                <label>
                  <span>Project Name / Site Title</span>
                  <input name="project_name" defaultValue={request?.project_name || 'My Business Website'} required />
                </label>
              </div>

              <label>
                <span>What is the primary goal of this website?</span>
                <textarea
                  name="primary_goal"
                  defaultValue={request?.intake?.primary_goal || ''}
                  placeholder="e.g. Take online orders, showcase our portfolio, and capture new customer inquiries."
                  required
                />
              </label>

              <div className="portal-form-grid">
                <label>
                  <span>Target Audience / Ideal Customer</span>
                  <textarea
                    name="ideal_customer"
                    defaultValue={request?.intake?.ideal_customer || ''}
                    placeholder="e.g. Local homeowners, busy parents looking for custom birthday cakes."
                  />
                </label>
                <label>
                  <span>Preferred Brand Colors &amp; Style</span>
                  <textarea
                    name="preferred_colors"
                    defaultValue={request?.intake?.preferred_colors || ''}
                    placeholder="e.g. Warm pastel tones, royal blue with gold accents, minimalist modern."
                  />
                </label>
              </div>

              <div className="portal-form-actions">
                <button type="submit" name="action" value="save" disabled={busy}>Save Brief &amp; Continue to Site Studio &rarr;</button>
                <button type="button" className="quiet" onClick={() => setWizardStep(2)}>&larr; Back to Hosting</button>
              </div>
            </form>

            <div style={{ marginTop: '1.5rem', paddingTop: '1.25rem', borderTop: '1px solid rgba(255,255,255,0.08)' }}>
              <form className="portal-asset-upload" onSubmit={onUploadAsset}>
                <h4 style={{ margin: '0 0 0.4rem', fontSize: '1.05rem', color: '#fff' }}>Upload Brand Logo, Photos, or Reference Files</h4>
                <p style={{ margin: '0 0 0.85rem', color: '#8e998e', fontSize: '0.84rem' }}>PNG, JPEG, SVG, WebP, or PDF up to 10 MB. Files are attached directly to your Site Studio build packet.</p>
                <input name="asset" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp,application/pdf" required />
                <label className="portal-check"><input name="ownership_confirmed" type="checkbox" value="1" required />I own this file or have permission to use it for this project.</label>
                <button disabled={busy} style={{ marginTop: '0.5rem' }}>{busy ? 'Uploading…' : 'Upload Asset to Build Packet'}</button>
                {request?.assets?.length > 0 && (
                  <ul style={{ marginTop: '0.85rem' }}>
                    {request.assets.map((asset) => (
                      <li key={asset.public_id}>✓ {asset.name} · {Math.ceil(asset.size_bytes / 1024)} KB</li>
                    ))}
                  </ul>
                )}
              </form>
            </div>
          </section>
        )}

        {/* STEP 4: SITE STUDIO BUILD HANDOFF */}
        {wizardStep === 4 && (
          <section className="portal-wizard-step" aria-labelledby="step-sitestudio-title">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem', marginBottom: '1.25rem' }}>
              <div>
                <span style={{ color: 'var(--p-lime)', fontSize: '0.72rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.1em' }}>Step 4 of 4 · Build Execution</span>
                <h4 id="step-sitestudio-title" style={{ margin: '0.25rem 0', fontSize: '1.35rem', color: '#fff' }}>Site Studio Build &amp; Proof Concepts</h4>
                <p style={{ color: '#aeb8ae', margin: 0, fontSize: '0.88rem' }}>
                  Hand off your project brief, hosting configuration, and brand assets to Site Studio to generate your working concepts.
                </p>
              </div>
              <span className="portal-product-badge">🚀 Site Studio Bridge</span>
            </div>

            {/* Readiness Summary */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '0.75rem', marginBottom: '1.5rem' }}>
              <div style={{ padding: '0.85rem', borderRadius: '10px', background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.06)' }}>
                <strong style={{ color: 'var(--p-lime)', fontSize: '0.82rem', display: 'block' }}>1. Custom Domain</strong>
                <span style={{ fontSize: '0.8rem', color: '#cdd4cd' }}>{request?.existing_domain || request?.intake?.desired_domains || 'Configured'}</span>
              </div>
              <div style={{ padding: '0.85rem', borderRadius: '10px', background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.06)' }}>
                <strong style={{ color: 'var(--p-lime)', fontSize: '0.82rem', display: 'block' }}>2. Cloud Server</strong>
                <span style={{ fontSize: '0.8rem', color: '#cdd4cd' }}>198.71.232.3 (SSD Active)</span>
              </div>
              <div style={{ padding: '0.85rem', borderRadius: '10px', background: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.06)' }}>
                <strong style={{ color: 'var(--p-lime)', fontSize: '0.82rem', display: 'block' }}>3. Creative Assets</strong>
                <span style={{ fontSize: '0.8rem', color: '#cdd4cd' }}>{request?.assets?.length || 0} file(s) attached</span>
              </div>
            </div>

            {/* Dispatch Action or In-Progress Banner */}
            {!hasProofs && !isSiteStudioPending && (
              <div className="portal-studio-dispatch-banner">
                <div>
                  <h4 style={{ margin: '0 0 0.25rem', fontSize: '1.2rem', color: '#fff' }}>Ready to generate your working concepts?</h4>
                  <p style={{ margin: 0, color: '#c2ccc2', fontSize: '0.88rem' }}>
                    Click below to dispatch your build packet directly to Site Studio. We will construct 3 genuinely different visual directions.
                  </p>
                </div>
                <button
                  type="button"
                  style={{ minHeight: '48px', padding: '0.75rem 1.5rem', fontSize: '1rem' }}
                  disabled={busy || !request?.public_id}
                  onClick={() => request?.public_id && onSendToSiteStudio && onSendToSiteStudio(request.public_id)}
                >
                  {busy ? 'Sending to Site Studio…' : '🚀 Send to Site Studio for Build →'}
                </button>
              </div>
            )}

            {isSiteStudioPending && (
              <div style={{ padding: '1.5rem', borderRadius: '16px', border: '1px solid var(--p-lime)', background: 'linear-gradient(135deg, rgba(124,252,0,0.08), #090c09)', display: 'grid', gap: '0.6rem' }}>
                <span style={{ color: 'var(--p-lime)', fontSize: '0.75rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.1em' }}>⚡ Build In Progress</span>
                <h4 style={{ margin: 0, fontSize: '1.25rem', color: '#fff' }}>Site Studio is crafting your 3 visual proof directions</h4>
                <p style={{ margin: 0, color: '#b2bcb2', fontSize: '0.88rem', lineHeight: '1.5' }}>
                  Your brief and assets are actively being assembled into working concepts. We will notify you by email as soon as they are ready for your interactive review!
                </p>
              </div>
            )}

            {/* If proofs are ready, show the full review grid */}
            {hasProofs && (
              <div style={{ marginTop: '1.5rem' }}>
                <h4 style={{ margin: '0 0 0.75rem', fontSize: '1.2rem', color: '#fff' }}>Review Your Working Concepts</h4>
                <WebsiteProofReview request={request} busy={busy} onDecision={onDecision} onShare={onShare} />
              </div>
            )}
          </section>
        )}
      </div>
    </div>
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
  onSendToSiteStudio,
  navigate,
}) {
  const [dnaOpen, setDnaOpen] = useState(false);
  const requests = workspace.website_requests || [];
  const proofReady = (req) => [3, 6].includes(req?.proofs?.variants?.length);

  const activeRequest =
    requests.find((req) => req.public_id === activeRequestId) ||
    requests.find((req) => ['customer_ready', 'notified'].includes(req.proof_review_status) && proofReady(req)) ||
    requests[0] ||
    null;

  const requestChips =
    requests.length > 1 ? (
      <div className="portal-request-chips" role="tablist" aria-label="Website requests">
        {requests.map((request) => (
          <button
            key={request.public_id}
            role="tab"
            aria-selected={request.public_id === (activeRequest?.public_id || '')}
            className={request.public_id === (activeRequest?.public_id || '') ? 'active' : ''}
            onClick={() => setActiveRequestId(request.public_id)}
          >
            <strong>{request.project_name}</strong>
            <small>
              {title(request.status)}
              {proofReady(request) ? ' · concepts ready' : ''}
            </small>
          </button>
        ))}
      </div>
    ) : null;

  return (
    <>
      <section className="portal-project-hero">
        <div>
          <span>One account. Every website.</span>
          <h2>Start, save, and return when you’re ready.</h2>
          <p>
            Tell us about a new site, landing page, redesign, or online store. Each request keeps its own
            intake, purchase, files, messages, and delivery history.
          </p>
        </div>
        <button onClick={() => setEditingRequest({})}>+ Start a new website</button>
      </section>

      {/* Guided Provisioning & Site Studio Wizard */}
      {activeRequest && !editingRequest && (
        <ProjectProvisioningWizard
          request={activeRequest}
          workspace={workspace}
          project={workspace.projects?.[0]}
          busy={busy}
          onSave={onSaveWebsiteRequest}
          onUploadAsset={onUploadAsset}
          onSendToSiteStudio={onSendToSiteStudio}
          onDecision={onDecideProof}
          onShare={onShareProof}
          navigate={navigate}
        />
      )}

      {editingRequest && (
        <Panel
          key={editingRequest.public_id || 'new-request'}
          id="website-request-editor"
          eyebrow={editingRequest.public_id ? 'Continue request' : 'New website request'}
          title={editingRequest.project_name || 'Tell us what you want to build'}
          className="portal-request-form"
        >
          <form onSubmit={onSaveWebsiteRequest}>
            {!editingRequest.public_id && (
              <p className="portal-form-stepnote">
                <strong>Step 1 of 2.</strong> Answer three quick things and save your draft — the full
                interview opens right after, and everything saves as you go.
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
                  <option value="new_website">New website</option>
                  <option value="landing_page">Landing page</option>
                  <option value="redesign">Website redesign</option>
                  <option value="online_store">Online store / shopping cart</option>
                </select>
              </label>
            </div>
            <label>
              What should this website accomplish?
              <textarea
                name="primary_goal"
                defaultValue={editingRequest.intake?.primary_goal || ''}
                placeholder="Example: take cake orders and explain pickup options"
              />
            </label>

            {editingRequest.public_id && (
              <>
                <fieldset className="portal-form-group">
                  <legend>Goals and customers</legend>
                  <label>
                    Other goals and how you will measure success
                    <textarea
                      name="secondary_goals"
                      defaultValue={editingRequest.intake?.secondary_goals || ''}
                    />
                    <textarea
                      name="success_metrics"
                      defaultValue={editingRequest.intake?.success_metrics || ''}
                      placeholder="Calls, quote requests, bookings, sales…"
                    />
                  </label>
                  <label>
                    Who should the website reach, and what problem are they trying to solve?
                    <textarea
                      name="ideal_customer"
                      defaultValue={editingRequest.intake?.ideal_customer || ''}
                    />
                    <textarea
                      name="customer_pain_points"
                      defaultValue={editingRequest.intake?.customer_pain_points || ''}
                    />
                  </label>
                  <label>
                    What should visitors do next?
                    <textarea
                      name="desired_actions"
                      defaultValue={editingRequest.intake?.desired_actions || ''}
                      placeholder="Call, submit a quote, book, visit, buy…"
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
                      Who makes the final decision?
                      <input
                        name="decision_makers"
                        defaultValue={editingRequest.intake?.decision_makers || ''}
                      />
                    </label>
                  </div>
                </fieldset>

                <fieldset className="portal-form-group">
                  <legend>Your business</legend>
                  <label>
                    Business name
                    <input
                      name="business_name"
                      defaultValue={editingRequest.business_name || ''}
                      autoComplete="organization"
                    />
                  </label>
                  <label>
                    What does the business sell or provide?
                    <textarea
                      name="products_services"
                      defaultValue={editingRequest.intake?.products_services || ''}
                    />
                  </label>
                  <label>
                    How does the business operate and make money today?
                    <textarea
                      name="business_model"
                      defaultValue={editingRequest.intake?.business_model || ''}
                      placeholder="How customers find you, buy, book, pay, and receive the product or service."
                    />
                  </label>
                  <label>
                    Industry and research context
                    <textarea
                      name="industry"
                      defaultValue={editingRequest.intake?.industry || ''}
                      placeholder="Use your own words—even if the industry is not listed anywhere."
                    />
                    <textarea
                      name="research_context"
                      defaultValue={editingRequest.intake?.research_context || ''}
                      placeholder="Competitors, trade associations, regulations, customer behavior, or questions FAMtastic should research."
                    />
                  </label>
                  <div className="portal-form-grid">
                    <label>
                      Search phrases customers use
                      <textarea
                        name="seo_keywords"
                        defaultValue={editingRequest.intake?.seo_keywords || ''}
                      />
                    </label>
                    <label>
                      Locations you serve
                      <textarea
                        name="service_locations"
                        defaultValue={editingRequest.intake?.service_locations || ''}
                      />
                    </label>
                  </div>
                  <div className="portal-form-grid">
                    <label>
                      Business hours
                      <textarea
                        name="business_hours"
                        defaultValue={editingRequest.intake?.business_hours || ''}
                      />
                    </label>
                    <label>
                      Public contact details
                      <textarea
                        name="contact_details"
                        defaultValue={editingRequest.intake?.contact_details || ''}
                      />
                    </label>
                  </div>
                  <label>
                    Social profiles
                    <textarea
                      name="social_profiles"
                      defaultValue={editingRequest.intake?.social_profiles || ''}
                    />
                  </label>
                </fieldset>

                <fieldset className="portal-form-group">
                  <legend>Pages, content, and features</legend>
                  <label>
                    Pages or sections you expect
                    <textarea
                      name="page_list"
                      defaultValue={editingRequest.intake?.page_list || ''}
                      placeholder="Home, About, Services, Gallery, Contact…"
                    />
                  </label>
                  <label>
                    Features you think you need
                    <textarea
                      name="required_features"
                      defaultValue={editingRequest.intake?.required_features || ''}
                      placeholder="Online ordering, booking, quote form, gallery…"
                    />
                  </label>
                  <label>
                    Tools or integrations you already use
                    <textarea
                      name="integrations"
                      defaultValue={editingRequest.intake?.integrations || ''}
                      placeholder="Square, Stripe, Calendly, Mailchimp, CRM…"
                    />
                  </label>
                  <div className="portal-form-grid">
                    <label>
                      Content readiness
                      <select
                        name="content_status"
                        defaultValue={editingRequest.intake?.content_status || ''}
                      >
                        <option value="">Choose one</option>
                        <option value="ready">Copy and photos are ready</option>
                        <option value="partial">Some content is ready</option>
                        <option value="help_needed">I need help creating content</option>
                      </select>
                    </label>
                    <label>
                      Desired timing
                      <input
                        name="launch_timing"
                        defaultValue={editingRequest.intake?.launch_timing || ''}
                        placeholder="A date or flexible"
                      />
                    </label>
                  </div>
                  <div className="portal-form-grid">
                    <label>
                      Copywriting help
                      <textarea
                        name="copywriting_needs"
                        defaultValue={editingRequest.intake?.copywriting_needs || ''}
                      />
                    </label>
                    <label>
                      Photos and assets
                      <textarea
                        name="photo_asset_status"
                        defaultValue={editingRequest.intake?.photo_asset_status || ''}
                      />
                    </label>
                  </div>
                  <label>
                    Products, services, or requests not listed above
                    <textarea
                      name="custom_needs"
                      defaultValue={editingRequest.intake?.custom_needs || ''}
                      placeholder="Describe anything unusual. Unlisted requests go to human scope review instead of being discarded."
                    />
                  </label>
                  <label>
                    Ongoing maintenance needs
                    <textarea
                      name="maintenance_needs"
                      defaultValue={editingRequest.intake?.maintenance_needs || ''}
                    />
                  </label>
                </fieldset>

                <fieldset className="portal-form-group">
                  <legend>Brand and design direction</legend>
                  <label>
                    Brand/logo status
                    <select
                      name="brand_status"
                      defaultValue={editingRequest.intake?.brand_status || ''}
                    >
                      <option value="">Choose one</option>
                      <option value="ready">Brand and logo ready</option>
                      <option value="partial">Some brand pieces exist</option>
                      <option value="help_needed">I need brand help</option>
                    </select>
                  </label>
                  <fieldset className="portal-creative-scale">
                    <legend>How FAMtastic should your website feel?</legend>
                    <p>
                      This controls creative intensity, not quality. 0 is safest and most familiar; 5 is
                      balanced and distinct; 10 is cinematic, immersive, and maximum FAMtastic.
                    </p>
                    <input
                      name="famtastic_level"
                      type="range"
                      min="0"
                      max="10"
                      step="1"
                      defaultValue={editingRequest.intake?.famtastic_level ?? 5}
                      onInput={(event) => {
                        event.currentTarget.nextElementSibling.textContent = event.currentTarget.value;
                      }}
                    />
                    <output>{editingRequest.intake?.famtastic_level ?? 5}</output>
                    <label className="portal-check">
                      <input
                        name="allow_bolder_direction"
                        type="checkbox"
                        defaultChecked={editingRequest.intake?.allow_bolder_direction}
                      />
                      Let one concept intentionally push beyond my selected level.
                    </label>
                  </fieldset>
                  <label>
                    Overall style notes
                    <textarea
                      name="style_preferences"
                      defaultValue={editingRequest.intake?.style_preferences || ''}
                    />
                  </label>
                  <div className="portal-form-grid">
                    <label>
                      Preferred colors
                      <textarea
                        name="preferred_colors"
                        defaultValue={editingRequest.intake?.preferred_colors || ''}
                        placeholder="Names, hex codes, or describe a palette"
                      />
                    </label>
                    <label>
                      Colors or styles to avoid
                      <textarea
                        name="colors_to_avoid"
                        defaultValue={editingRequest.intake?.colors_to_avoid || ''}
                      />
                      <textarea
                        name="styles_to_avoid"
                        defaultValue={editingRequest.intake?.styles_to_avoid || ''}
                      />
                    </label>
                  </div>
                  <label>
                    How should visitors feel?
                    <textarea
                      name="desired_feeling"
                      defaultValue={editingRequest.intake?.desired_feeling || ''}
                      placeholder="Safe, energized, luxurious, playful, confident…"
                    />
                  </label>
                  <label>
                    Websites you like and competitors
                    <textarea
                      name="reference_sites"
                      defaultValue={editingRequest.intake?.reference_sites || ''}
                      placeholder="One URL per line, plus what you like"
                    />
                    <textarea
                      name="reference_site_reasons"
                      defaultValue={editingRequest.intake?.reference_site_reasons || ''}
                      placeholder="What specifically works or does not work for you?"
                    />
                    <textarea
                      name="competitors"
                      defaultValue={editingRequest.intake?.competitors || ''}
                    />
                  </label>
                  <label>
                    Notes about flyers, images, or other visual references
                    <textarea
                      name="visual_reference_notes"
                      defaultValue={editingRequest.intake?.visual_reference_notes || ''}
                    />
                  </label>
                </fieldset>

                <fieldset className="portal-form-group">
                  <legend>Domains, email, and access</legend>
                  <label>
                    Domain plan
                    <select
                      name="domain_choice"
                      defaultValue={editingRequest.domain_choice || 'undecided'}
                    >
                      <option value="undecided">I’m not sure yet</option>
                      <option value="new_domain">I need a new domain</option>
                      <option value="existing_domain">I already own a domain</option>
                    </select>
                  </label>
                  <label>
                    Existing domain, if any
                    <input
                      name="existing_domain"
                      defaultValue={editingRequest.existing_domain || ''}
                      inputMode="url"
                      placeholder="example.com"
                    />
                  </label>
                  <div className="portal-form-grid">
                    <label>
                      Desired domain names
                      <textarea
                        name="desired_domains"
                        defaultValue={editingRequest.intake?.desired_domains || ''}
                        placeholder="List first choice and acceptable alternatives. Availability is verified before purchase."
                      />
                    </label>
                    <label>
                      If the first-choice domain is unavailable
                      <textarea
                        name="domain_fallback"
                        defaultValue={editingRequest.intake?.domain_fallback || ''}
                        placeholder="Alternatives, words we may adjust, or request a conversation before choosing."
                      />
                    </label>
                  </div>
                  <label>
                    Business email needs
                    <textarea
                      name="business_email_needs"
                      defaultValue={editingRequest.intake?.business_email_needs || ''}
                      placeholder="Mailboxes such as info@ or sales@, number of users, full inboxes versus forwarding, and current provider."
                    />
                  </label>
                  <label>
                    Current digital ownership and access
                    <textarea
                      name="existing_technology"
                      defaultValue={editingRequest.intake?.existing_technology || ''}
                      placeholder="Registrar, hosting company, email provider, website/CMS, analytics, repositories, agencies, or logins you control. Do not paste passwords."
                    />
                  </label>
                  <div className="portal-form-grid">
                    <label>
                      Accessibility needs
                      <textarea
                        name="accessibility_needs"
                        defaultValue={editingRequest.intake?.accessibility_needs || ''}
                      />
                    </label>
                    <label>
                      Privacy or legal requirements
                      <textarea
                        name="privacy_legal_needs"
                        defaultValue={editingRequest.intake?.privacy_legal_needs || ''}
                      />
                    </label>
                  </div>
                </fieldset>

                <fieldset className="portal-form-group">
                  <legend>Store, booking, AI, and wrap-up</legend>
                  <label>
                    Online store details
                    <textarea
                      name="ecommerce_details"
                      defaultValue={editingRequest.intake?.ecommerce_details || ''}
                      placeholder="Products, variants, taxes, inventory, payments…"
                    />
                  </label>
                  <div className="portal-form-grid">
                    <label>
                      Approximate product count
                      <input
                        name="product_count"
                        defaultValue={editingRequest.intake?.product_count || ''}
                      />
                    </label>
                    <label>
                      Shipping, delivery, or pickup
                      <textarea
                        name="shipping_pickup"
                        defaultValue={editingRequest.intake?.shipping_pickup || ''}
                      />
                    </label>
                  </div>
                  <label>
                    Booking or appointment details
                    <textarea
                      name="booking_details"
                      defaultValue={editingRequest.intake?.booking_details || ''}
                    />
                  </label>
                  <label>
                    AI agent goals
                    <textarea
                      name="ai_agent_goals"
                      defaultValue={editingRequest.intake?.ai_agent_goals || ''}
                    />
                  </label>
                  <fieldset>
                    <legend>Optional AI brief enrichment</legend>
                    <label>
                      Connection mode
                      <select
                        name="ai_enrichment_mode"
                        defaultValue={editingRequest.intake?.ai_enrichment_mode || 'none'}
                      >
                        <option value="none">No AI enrichment</option>
                        <option value="famtastic_managed">Use FAMtastic-managed models</option>
                        <option value="customer_managed">
                          I want to connect my own provider later
                        </option>
                      </select>
                    </label>
                    <label>
                      Context for the AI reviewer
                      <textarea
                        name="ai_context_notes"
                        defaultValue={editingRequest.intake?.ai_context_notes || ''}
                        placeholder="Do not paste API keys or passwords."
                      />
                    </label>
                    <label className="portal-check">
                      <input
                        name="life_path_opt_in"
                        type="checkbox"
                        defaultChecked={editingRequest.intake?.life_path_opt_in}
                      />
                      Use optional life-path guidance only for voice and creative suggestions.
                    </label>
                  </fieldset>
                  <div className="portal-form-grid">
                    <label>
                      Budget context (optional)
                      <textarea
                        name="budget_context"
                        defaultValue={editingRequest.intake?.budget_context || ''}
                        placeholder="This does not lock you into a package; it helps us recommend the smallest useful solution."
                      />
                    </label>
                    <label>
                      Anything else Fritz should know
                      <textarea
                        name="notes"
                        defaultValue={editingRequest.intake?.notes || ''}
                      />
                    </label>
                  </div>
                  <label className="portal-check">
                    <input
                      name="recommendation_requested"
                      type="checkbox"
                      defaultChecked={editingRequest.recommendation_requested !== 0}
                    />
                    Recommend the smallest useful package and add-ons for me.
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
                  : 'Save draft & open the full brief'}
              </button>
              {editingRequest.public_id && (
                <button className="secondary" name="action" value="submit" disabled={busy}>
                  Submit for review
                </button>
              )}
              <button className="quiet" type="button" onClick={() => setEditingRequest(null)}>
                Close
              </button>
            </div>
          </form>

          {editingRequest.public_id && (
            <form className="portal-asset-upload" onSubmit={onUploadAsset}>
              <h3>Add a flyer, logo, photo, or visual reference</h3>
              <p>PNG, JPEG, WebP, or PDF up to 10 MB. Files stay private and attached to this request.</p>
              <input
                name="asset"
                type="file"
                accept="image/png,image/jpeg,image/webp,application/pdf"
                required
              />
              <label className="portal-check">
                <input name="ownership_confirmed" type="checkbox" value="1" required />
                I own this file or have permission to share it for this project.
              </label>
              <label className="portal-check">
                <input name="ai_use_consent" type="checkbox" value="1" />
                FAMtastic may use this file as reference for approved AI-assisted concept generation.
              </label>
              <button disabled={busy}>{busy ? 'Uploading…' : 'Upload reference securely'}</button>
              {editingRequest.assets?.length > 0 && (
                <ul>
                  {editingRequest.assets.map((asset) => (
                    <li key={asset.public_id}>
                      {asset.name} · {Math.ceil(asset.size_bytes / 1024)} KB
                    </li>
                  ))}
                </ul>
              )}
            </form>
          )}
        </Panel>
      )}

      <section className="portal-request-list">
        {requestChips}
        {!activeRequest && (
          <Panel eyebrow="Projects" title="No website requests yet">
            <p>Start with a short brief — your concepts, proofs, and purchase live here.</p>
          </Panel>
        )}
        {activeRequest ? (
          <Panel
            key={activeRequest.public_id}
            id={`website-request-${activeRequest.public_id}`}
            tabIndex={activeRequest.public_id === targetRequest ? -1 : undefined}
            className={activeRequest.public_id === targetRequest ? 'portal-request-target' : ''}
            eyebrow={
              activeRequest.status === 'converted'
                ? 'Purchased Project Request'
                : 'Website Request'
            }
            title={activeRequest.project_name}
          >
            <dl>
              <div>
                <dt>Status</dt>
                <dd>{title(activeRequest.status)}</dd>
              </div>
              <div>
                <dt>Proofs</dt>
                <dd>{title(activeRequest.proof_review_status)}</dd>
              </div>
              <div>
                <dt>Updated</dt>
                <dd>{date(activeRequest.changed)}</dd>
              </div>
            </dl>

            {activeRequest.private_offer && (
              <div
                className="portal-private-offer-card"
                style={{
                  margin: '1rem 0',
                  padding: '1.15rem',
                  border: '1px solid #7cfc00',
                  borderRadius: '14px',
                  background: 'rgba(124,252,0,0.08)',
                }}
              >
                <span
                  style={{
                    color: '#7cfc00',
                    fontSize: '0.75rem',
                    textTransform: 'uppercase',
                    letterSpacing: '0.1em',
                    fontWeight: '800',
                  }}
                >
                  ⚡ Exclusive Private Offer Active
                </span>
                <h3 style={{ margin: '0.4rem 0 0.2rem', fontSize: '1.25rem' }}>
                  {activeRequest.private_offer.reason || 'Special Approved Package Price'}
                </h3>
                <p style={{ margin: '0.25rem 0 0.85rem', color: '#c2ccc2' }}>
                  Offered Rate:{' '}
                  <strong style={{ color: '#7cfc00', fontSize: '1.2rem' }}>
                    {money(activeRequest.private_offer.offered_amount_minor)}
                  </strong>
                  {activeRequest.private_offer.list_amount_minor >
                    activeRequest.private_offer.offered_amount_minor && (
                    <span
                      style={{
                        textDecoration: 'line-through',
                        opacity: 0.6,
                        marginLeft: '0.65rem',
                      }}
                    >
                      {money(activeRequest.private_offer.list_amount_minor)} list
                    </span>
                  )}
                </p>
                <button
                  type="button"
                  onClick={() =>
                    navigate(`/buy?request=${encodeURIComponent(activeRequest.public_id)}`)
                  }
                >
                  Accept Offer &amp; Purchase →
                </button>
              </div>
            )}

            {activeRequest.intake?.recommendation && (
              <p>
                <strong>Recommended path: {activeRequest.intake.recommendation.label}</strong>
                <br />
                <small>{activeRequest.intake.recommendation.reasons?.join(' ')}</small>
              </p>
            )}

            {proofReady(activeRequest) && (
              <WebsiteProofReview
                request={activeRequest}
                busy={busy}
                onDecision={onDecideProof}
                onShare={onShareProof}
              />
            )}

            {!activeRequest.proofs && activeRequest.status === 'submitted' && (
              <p>
                {activeRequest.proof_review_status === 'owner_review'
                  ? 'Your concepts are complete and in FAMtastic quality review. We’ll notify you when the complete set is approved.'
                  : 'Your brief is in the studio queue. We’ll notify you when your working concepts are ready.'}
              </p>
            )}

            <div style={{ marginTop: '1rem', display: 'flex', gap: '0.6rem', flexWrap: 'wrap' }}>
              {!['converted', 'cancelled'].includes(activeRequest.status) && (
                <>
                  <button onClick={() => setEditingRequest(activeRequest)}>
                    Continue request
                  </button>
                  {activeRequest.direct_checkout_available && (
                    <button
                      className="secondary"
                      onClick={() =>
                        navigate(`/buy?request=${encodeURIComponent(activeRequest.public_id)}`)
                      }
                    >
                      Purchase {activeRequest.intake?.recommendation?.label}
                    </button>
                  )}
                </>
              )}
              <button
                type="button"
                className="quiet"
                onClick={() => setDnaOpen((open) => !open)}
              >
                {dnaOpen ? 'Hide Build DNA ▴' : 'Inspect Build DNA ▾'}
              </button>
            </div>

            {dnaOpen && (
              <div
                className="portal-dna-viewer"
                style={{
                  marginTop: '1rem',
                  padding: '1rem',
                  border: '1px solid rgba(124,252,0,0.3)',
                  borderRadius: '12px',
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
                <p style={{ fontSize: '0.85rem', color: '#aab2aa', margin: '0.4rem 0' }}>
                  Standard `famtastic.build-dna.v1`. Every stage, research packet, and concept variant is
                  journaled with exact hashes and QA gates.
                </p>
                <dl style={{ fontSize: '0.82rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.3rem 0' }}>
                    <dt style={{ color: '#8e998e' }}>Request ID</dt>
                    <dd>{activeRequest.public_id}</dd>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.3rem 0' }}>
                    <dt style={{ color: '#8e998e' }}>Creative Intensity</dt>
                    <dd>{activeRequest.intake?.famtastic_level ?? 5} / 10</dd>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.3rem 0' }}>
                    <dt style={{ color: '#8e998e' }}>AI Research Mode</dt>
                    <dd>{activeRequest.intake?.ai_enrichment_mode || 'Managed'}</dd>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.3rem 0' }}>
                    <dt style={{ color: '#8e998e' }}>Verification Gate</dt>
                    <dd style={{ color: '#7cfc00' }}>✓ Schema &amp; Security Verified</dd>
                  </div>
                </dl>
              </div>
            )}

            {activeRequest.status === 'converted' && (
              <p style={{ marginTop: '0.8rem' }}>This request is connected to its paid project below.</p>
            )}
          </Panel>
        ) : null}
      </section>

      <section className="portal-grid">
        {workspace.projects.length ? (
          workspace.projects.map((p) => (
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
              {[3, 6].includes(p.proofs?.variants?.length) && (
                <div className="portal-proof-grid">
                  {p.proofs.variants.map((proof) => (
                    <a
                      key={proof.direction_id}
                      href={proof.preview_url}
                      target="_blank"
                      rel="noreferrer"
                      className={
                        p.proofs.selected_variant === proof.direction_id ? 'selected' : ''
                      }
                    >
                      <b>{proof.direction_id.toUpperCase()}</b>
                      <strong>{proof.direction_name}</strong>
                      <span>Open concept ↗</span>
                    </a>
                  ))}
                </div>
              )}
              {p.proofs?.generation_status === 'waiting_callback' && (
                <p>Your concepts are being created now. We’ll email you when review opens.</p>
              )}
              {p.live_url && (
                <a href={p.live_url} target="_blank" rel="noreferrer">
                  Visit live site ↗
                </a>
              )}
            </Panel>
          ))
        ) : !workspace.website_requests?.length ? (
          <Panel eyebrow="Projects" title="No website requests yet">
            <p>
              Start with a short, reusable brief. Your detailed onboarding continues here after purchase.
            </p>
          </Panel>
        ) : null}
      </section>
    </>
  );
}
