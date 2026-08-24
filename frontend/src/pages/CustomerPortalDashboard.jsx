import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { createCustomerReferral, createCustomerThread, createWebsiteRequest, customerLogout, customerSession, decideWebsiteRequestProof, getCustomerCatalog, getCustomerThread, getCustomerWorkspace, replyCustomerThread, updateCustomerPreferences, updateCustomerProfile, updateWebsiteRequest, updateWebsiteRequestProofShare, uploadWebsiteRequestAsset } from '../api/customer.js';
import '../portal.css';

const GROUPS = [
  ['Workspace', [['home', 'Home'], ['services', 'Services'], ['projects', 'Projects'], ['messages', 'Messages'], ['billing', 'Billing'], ['account', 'Account']]],
];
const LABELS = Object.fromEntries(GROUPS.flatMap(([, items]) => items));
const title = (value) => String(value || 'Preparing').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const money = (amount = 0, currency = 'usd') => new Intl.NumberFormat('en-US', { style: 'currency', currency: currency.toUpperCase() }).format(amount / 100);
const date = (stamp) => stamp ? new Date(Number(stamp) * 1000).toLocaleDateString() : 'Not scheduled';

function Panel({ eyebrow, title: heading, children, className = '', id, tabIndex }) { return <article id={id} tabIndex={tabIndex} className={`portal-panel ${className}`}><span>{eyebrow}</span>{heading && <h2>{heading}</h2>}{children}</article>; }
function Empty({ children }) { return <p className="portal-empty">{children}</p>; }

function WebsiteProofReview({ request, busy, onDecision, onShare }) {
  const [revisionOpen, setRevisionOpen] = useState(false);
  const [savingDirection, setSavingDirection] = useState('');
  const [copied, setCopied] = useState(false);
  const nextActionRef = useRef(null);
  const revisionRef = useRef(null);
  const variants = request.proofs?.variants || [];
  const selectedDirection = request.proofs?.selected_variant || request.selected_proof_direction || '';
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
    if (saved) window.requestAnimationFrame(() => {
      nextActionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      nextActionRef.current?.focus({ preventScroll: true });
    });
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

  return <section className="portal-proof-review" aria-label={`Review concepts for ${request.project_name}`}>
    <div className={`portal-proof-grid${selectedDirection ? ' has-selection' : ''}`}>
      {variants.map((proof) => {
        const selected = selectedDirection === proof.direction_id;
        return <article key={proof.direction_id} data-proof-direction={proof.direction_id} className={selected ? 'selected' : selectedDirection ? 'dimmed' : ''}>
          <span className="portal-proof-badge" aria-hidden={!selected}>{selected ? '✓ Selected' : 'Available'}</span>
          <a href={proof.preview_url} target="_blank" rel="noreferrer"><b>{proof.direction_name}</b><span>Open working concept ↗</span></a>
          <button type="button" aria-pressed={selected} disabled={busy || selected} onClick={() => choose(proof.direction_id)}>{savingDirection === proof.direction_id ? 'Saving selection…' : selected ? `${proof.direction_name} selected ✓` : selectedDirection ? `Switch to ${proof.direction_name}` : `Choose ${proof.direction_name}`}</button>
        </article>;
      })}
    </div>
    {selectedProof && <section ref={nextActionRef} className={`portal-proof-next${revisionPending ? ' changes-requested' : ''}`} tabIndex="-1" aria-live="polite">
      <span>{revisionPending ? 'Changes requested ✓' : 'Selection saved ✓'}</span>
      <h3>{revisionPending ? `We received your changes for ${selectedProof.direction_name}` : `${selectedProof.direction_name} is your selected direction`}</h3>
      <p>{revisionPending ? 'Fritz has your notes. This request will stay visible while the next proof round is prepared.' : 'Review it again, request changes, or continue when you are comfortable with the direction.'}</p>
      <div className="portal-proof-next__actions">
        <a href={selectedProof.preview_url} target="_blank" rel="noreferrer">Open selected proof ↗</a>
        <button className="quiet" type="button" aria-expanded={revisionOpen} aria-controls={`proof-revision-${request.public_id}`} onClick={() => setRevisionOpen((open) => !open)}>{revisionOpen ? 'Close changes' : revisionPending ? 'Update change request' : 'Make changes'}</button>
      </div>
    </section>}
    {!selectedProof && <button className="quiet portal-proof-change-toggle" type="button" aria-expanded={revisionOpen} aria-controls={`proof-revision-${request.public_id}`} onClick={() => setRevisionOpen((open) => !open)}>{revisionOpen ? 'Close changes' : 'Need changes before choosing?'}</button>}
    {revisionOpen && <form ref={revisionRef} id={`proof-revision-${request.public_id}`} className="portal-proof-revision" onSubmit={requestChanges}>
      <div><span>Revision request</span><h3>What should FAMtastic change?</h3><p>Be specific about colors, layout, wording, images, or anything that does not feel right.</p></div>
      <label htmlFor={`proof-revision-notes-${request.public_id}`}>Your change notes<textarea id={`proof-revision-notes-${request.public_id}`} name="notes" required placeholder="Example: Keep this layout, but use royal blue and warmer photography." defaultValue={request.intake?.proof_revision_request?.notes || ''} /></label>
      <div className="portal-form-actions"><button type="submit" disabled={busy}>{busy ? 'Sending changes…' : 'Send changes to Fritz'}</button><button className="quiet" type="button" onClick={() => setRevisionOpen(false)}>Cancel</button></div>
    </form>}
    <section className={`portal-proof-share${proofShare.enabled ? ' is-enabled' : ''}`} aria-labelledby={`proof-share-title-${request.public_id}`}>
      <div><span>Optional sharing</span><h3 id={`proof-share-title-${request.public_id}`}>Share these proofs without requiring sign-in</h3><p>{proofShare.enabled ? 'Anyone with this unlisted link can view the working concepts. They cannot choose a design, request changes, purchase, or see your account details.' : 'Create a revocable, unlisted link when you want a colleague or decision-maker to compare the concepts.'}</p></div>
      <button className="portal-proof-share__switch" type="button" role="switch" aria-checked={proofShare.enabled} disabled={busy} onClick={() => onShare(request.public_id, proofShare.enabled ? 'disable' : 'enable')}><i aria-hidden="true" /><span>{proofShare.enabled ? 'Sharing on' : 'Sharing off'}</span></button>
      {proofShare.enabled && <div className="portal-proof-share__link"><label htmlFor={`proof-share-url-${request.public_id}`}>Unlisted link</label><input id={`proof-share-url-${request.public_id}`} readOnly value={proofShare.url} onFocus={(event) => event.currentTarget.select()} /><div><button type="button" onClick={copyShareLink}>{copied ? 'Copied ✓' : 'Copy link'}</button><button className="quiet" type="button" disabled={busy} onClick={() => onShare(request.public_id, 'rotate')}>Create a new link</button></div><small>Creating a new link immediately revokes this one.</small></div>}
    </section>
  </section>;
}

function PortalServices({ workspace, catalog, go, compact = false }) {
  const ownedTypes = new Set(workspace.entitlements.filter((item) => item.status === 'active').map((item) => item.entitlement_type));
  const promotedSkus = ['FAM-AI-AGENT', 'FAM-LEAD-AUTOMATION', 'FAM-ANALYTICS', 'FAM-LOCAL-SEO', 'FAM-MAINTENANCE', 'FAM-SCHEDULING', 'FAM-BRAND', 'FAM-COPY'];
  const promoted = (catalog?.products || []).filter((item) => promotedSkus.includes(item.sku)).filter((item) => !(item.entitlements || []).some((type) => ownedTypes.has(type))).slice(0, compact ? 4 : 8);
  return <section className={`portal-service-hub${compact ? ' compact' : ''}`} aria-labelledby={compact ? 'portal-services-preview-title' : 'portal-services-title'}>
    <header><div><span>Service command center</span><h2 id={compact ? 'portal-services-preview-title' : 'portal-services-title'}>{compact ? 'Manage what you own. Discover what helps next.' : 'Your services and growth systems'}</h2></div><p>Active services, work, support, billing, and relevant next steps stay connected to this account.</p></header>
    <div className="portal-service-columns">
      <div><h3>Your services</h3>{workspace.entitlements.length ? <ul>{workspace.entitlements.map((service) => <li key={service.public_id}><i aria-hidden="true" /><div><strong>{title(service.entitlement_type)}</strong><small>{title(service.status)}{service.included_until ? ` · included through ${date(service.included_until)}` : ''}</small></div><button onClick={() => go(service.entitlement_type.includes('website') ? 'projects' : 'messages')}>Manage</button></li>)}</ul> : <Empty>No active services yet. Start with a website brief or ask us what would remove the biggest bottleneck.</Empty>}</div>
      <div><h3>Recommended studio modules</h3><div className="portal-market-grid">{promoted.map((item) => <article key={item.sku}><span>{item.billing?.kind === 'recurring' ? `${item.billing.interval}ly` : 'One-time setup'}</span><h4>{item.title.replace(/\s+[—-].*$/, '')}</h4><p>{item.summary}</p><footer><strong>${item.price}{item.billing?.kind === 'recurring' ? '/mo' : ''}</strong><a href={`/contact?service=${encodeURIComponent(item.sku)}`}>Explore →</a></footer></article>)}</div></div>
    </div>
    {compact && <button className="portal-services-all" onClick={() => go('services')}>Open all services</button>}
  </section>;
}

function PortalHome({ workspace, org, order, project, nextAction, go, catalog }) {
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
    const timer = window.setInterval(() => setTutorialStep((step) => (step + 1) % tutorialSteps.length), 1700);
    return () => window.clearInterval(timer);
  }, [tutorialOpen, tutorialSteps.length]);
  return <>
    <section className="portal-home-intro">
      <section className="portal-ai-hero">
        <div className="portal-ai-hero__content">
          <span>FAMtastic AI solutions studio</span>
          <h2>Your business systems, all in one place.</h2>
          <p>Start a website, manage every active service, and discover the next useful AI or automation module.</p>
          <div className="portal-ai-hero__actions">
            <button onClick={() => go('projects')}>{requests.length ? 'Continue my website' : 'Start my website & proofs'}</button>
            <div className="portal-tutorial-trigger"><button className="secondary" onClick={() => { setTutorialStep(0); setTutorialOpen(true); }}>Play tutorial</button><span role="tooltip">New here? Watch the 20-second website walkthrough.</span></div>
          </div>
          <small>No technical language required. Save progress and return anytime.</small>
        </div>
        <div className="portal-ai-hero__signal" aria-hidden="true"><i /><i /><i /></div>
      </section>
      <article className="portal-inline-tutorial" aria-label="Start-to-launch website tutorial"><video src="/portal/website-journey-clay-v2.mp4" poster="/portal/website-journey-clay-v2.png" autoPlay muted loop playsInline controls aria-label="Animated website process tutorial with readable step-by-step instructions" /></article>
    </section>

    {tutorialOpen && <div className="portal-tutorial-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setTutorialOpen(false); }}><section className="portal-tutorial" role="dialog" aria-modal="true" aria-labelledby="portal-tutorial-title"><button className="portal-tutorial__close" aria-label="Close website walkthrough" onClick={() => setTutorialOpen(false)}>×</button><div className="portal-tutorial__visual" style={{ '--tutorial-position': `${tutorialStep * 16.666}%` }}><img className="portal-tutorial__poster" src="/portal/website-journey-clay-v2.png" alt="Clay artwork showing the simple journey from registration to a finished website" /><video src="/portal/website-journey-clay-v2.mp4" poster="/portal/website-journey-clay-v2.png" autoPlay muted loop playsInline aria-label="Clay animation with text showing how to register, complete a website brief, review proofs, select a design, pay, and launch" /><i aria-hidden="true" /></div><div className="portal-tutorial__copy"><span>Website launch in seven easy steps</span><h2 id="portal-tutorial-title">{tutorialSteps[tutorialStep][0]}</h2><p>{tutorialSteps[tutorialStep][1]}</p><ol aria-label="Tutorial progress">{tutorialSteps.map(([label], index) => <li key={label} className={index === tutorialStep ? 'active' : index < tutorialStep ? 'complete' : ''}><button aria-label={`Show step ${index + 1}: ${label}`} onClick={() => setTutorialStep(index)}>{index + 1}</button></li>)}</ol><div className="portal-tutorial__actions"><button onClick={() => { setTutorialOpen(false); go('projects'); }}>Start my website</button><button className="secondary" onClick={() => setTutorialOpen(false)}>Not yet</button></div></div></section></div>}

    <section className="portal-command-grid">
      <Panel eyebrow="Next action" title={nextAction} className="lime"><p>{!order ? 'Answer the guided questions once. Your responses become the project brief, recommendation, and delivery record.' : 'Your account keeps the next decision visible until the project can move forward.'}</p><button onClick={() => go('projects')}>Continue</button></Panel>
      <Panel eyebrow="Your studio" title={`${workspace.entitlements.length} active service${workspace.entitlements.length === 1 ? '' : 's'}`}><p>Manage websites, AI agents, automation, maintenance, analytics, and support from one account.</p><button onClick={() => go('services')}>Open services</button></Panel>
    </section>

    <section className="portal-journey" aria-labelledby="portal-journey-title">
      <header><span>How your studio works</span><h2 id="portal-journey-title">From brief to business system</h2></header>
      <ol>
        <li className={requests.length ? 'complete' : 'active'}><b>1</b><div><strong>Tell us the outcome</strong><small>A guided intake captures the business, audience, goals, content, features, and timing.</small></div></li>
        <li className={requests.some((request) => ['submitted', 'checkout_started', 'converted'].includes(request.status)) ? 'active' : ''}><b>2</b><div><strong>AI studio research</strong><small>Specialists organize the brief, check assumptions, and recommend the smallest useful solution.</small></div></li>
        <li className={project?.proof_url ? 'active' : ''}><b>3</b><div><strong>Review visual proofs</strong><small>Compare distinct design directions, ask questions, and choose what feels right.</small></div></li>
        <li className={project?.approval_status === 'approved' ? 'complete' : ''}><b>4</b><div><strong>Approve, build, and grow</strong><small>Your decision, delivery, support, and future AI solutions stay connected to this workspace.</small></div></li>
      </ol>
    </section>

    <PortalServices workspace={workspace} catalog={catalog} go={go} compact />

    <section className="portal-grid two">
      <Panel eyebrow="Recent activity" title="What FAMtastic has handled">{workspace.activity.length ? <ul>{workspace.activity.slice(0, 5).map((item, i) => <li key={i}><strong>{item.summary}</strong><small>{date(item.created)}</small></li>)}</ul> : <Empty>Start a website request and each saved brief, submission, proof, approval, and delivery milestone will appear here.</Empty>}</Panel>
      <Panel eyebrow="Help when you need it" title={openThreads ? `${openThreads} open conversation${openThreads === 1 ? '' : 's'}` : `Welcome to ${org?.name}`}><p>Ask a question without repeating your business or project history. Messages stay connected to this workspace.</p><button onClick={() => go('messages')}>{openThreads ? 'View messages' : 'Ask FAMtastic'}</button></Panel>
    </section>
  </>;
}

export default function CustomerPortalDashboard() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const continuingWebsiteLead = searchParams.get('start') === 'website';
  const [section, setSection] = useState(continuingWebsiteLead ? 'projects' : 'home');
  const [session, setSession] = useState(null);
  const [workspace, setWorkspace] = useState(null);
  const [catalog, setCatalog] = useState(null);
  const [state, setState] = useState('loading');
  const [menu, setMenu] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const [activeThread, setActiveThread] = useState(null);
  const [faqSearch, setFaqSearch] = useState('');
  const [busy, setBusy] = useState(false);
  const [editingRequest, setEditingRequest] = useState(continuingWebsiteLead ? {} : null);
  const [targetRequest, setTargetRequest] = useState('');
  const proofIntentHandled = useRef(false);
  useEffect(() => { Promise.all([customerSession(), getCustomerWorkspace(), getCustomerCatalog()]).then(([s, w, c]) => { setSession(s); setWorkspace(w); setCatalog(c); setState('ready'); }).catch(() => navigate('/login', { replace: true })); }, [navigate]);
  useEffect(() => {
    if (!workspace || !session || proofIntentHandled.current) return;
    proofIntentHandled.current = true;
    const params = new URLSearchParams(window.location.search);
    const requestedSection = params.get('section');
    const requestId = params.get('request') || '';
    const startWebsite = params.get('start') === 'website';
    const requestedProof = requestId ? workspace.website_requests?.find((request) => request.public_id === requestId) : null;
    const requestedProofReady = requestedProof && [3, 6].includes(requestedProof.proofs?.variants?.length);
    const readyProof = workspace.website_requests?.find((request) => ['customer_ready', 'notified'].includes(request.proof_review_status) && [3, 6].includes(request.proofs?.variants?.length));
    if (requestedSection && Object.hasOwn(LABELS, requestedSection)) setSection(requestedSection);
    if (startWebsite) {
      setSection('projects');
      setEditingRequest((current) => current || {});
    }
    if (requestId || readyProof) setSection('projects');
    if (requestId) {
      setTargetRequest(requestId);
      if (requestedProofReady) {
        const count = requestedProof.proofs?.variants?.length || 0;
        setNotice(`Your ${count} website concepts are ready below. Compare each direction and choose when you are ready.`);
      } else if (requestedProof) {
        setError('This website request belongs to your account, but its concepts are not available for customer review yet. FAMtastic will email you when the complete set is approved.');
      } else {
        setError(`This proof link is not connected to the account signed in as ${session.customer.email}. Sign out, then sign in with the email address that received the proof-ready message.`);
      }
    } else if (readyProof) {
      setTargetRequest(readyProof.public_id);
      setNotice(`Your ${readyProof.proofs.variants.length} website concepts are ready below.`);
    }
  }, [workspace, session]);
  useEffect(() => {
    if (section !== 'projects' || !targetRequest) return;
    const target = document.getElementById(`website-request-${targetRequest}`);
    if (!target) return;
    window.requestAnimationFrame(() => {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      target.focus({ preventScroll: true });
    });
  }, [section, targetRequest]);
  const org = workspace?.organization;
  const project = workspace?.projects?.[0];
  const order = workspace?.orders?.[0];
  const owned = new Set(workspace?.entitlements?.map((item) => item.entitlement_type) || []);
  const nextAction = useMemo(() => !order ? 'Tell us what your business needs next' : order.payment_status !== 'paid' ? 'Complete your purchase' : !project ? 'Complete your project brief' : project.approval_status !== 'approved' ? 'Review and approve your project' : 'See your next growth opportunity', [order, project]);
  const filteredFaqs = useMemo(() => (workspace?.faqs || []).filter((item) => `${item.question} ${item.answer} ${item.category}`.toLowerCase().includes(faqSearch.toLowerCase())), [workspace, faqSearch]);
  if (state === 'loading') return <div className="portal-state"><i />Opening your customer command center…</div>;
  const go = (id) => { setSection(id); setNotice(''); setMenu(false); window.scrollTo({ top: 0, behavior: 'smooth' }); };
  const refresh = async () => setWorkspace(await getCustomerWorkspace(org.public_id));
  const act = async (work, success) => { setError(''); setBusy(true); try { const value = await work(); if (success) setNotice(success); return { ok: true, value }; } catch (exception) { setError(exception.message); return { ok: false, value: null }; } finally { setBusy(false); } };
  const saveProfile = (event) => { event.preventDefault(); const form = event.currentTarget; act(async () => setSession(await updateCustomerProfile(Object.fromEntries(new FormData(form)))), 'Profile updated.'); };
  const saveSettings = (event) => { event.preventDefault(); const form = event.currentTarget; const data = Object.fromEntries(new FormData(form)); data.topics = new FormData(form).getAll('topics'); act(async () => { const result = await updateCustomerPreferences(data); setWorkspace((current) => ({ ...current, preferences: result.preferences })); setSession((current) => ({ ...current, customer: { ...current.customer, marketing_status: result.preferences.deals_promotions ? 'subscribed' : 'unsubscribed' } })); }, 'Communication preferences saved.'); };
  const openThread = (event) => { event.preventDefault(); const form = event.currentTarget; act(async () => { await createCustomerThread({ ...Object.fromEntries(new FormData(form)), organization: org.public_id }); await refresh(); form.reset(); }, 'Your request was sent.'); };
  const viewThread = (id) => act(async () => setActiveThread(await getCustomerThread(id)));
  const replyThread = (event) => { event.preventDefault(); const form = event.currentTarget; act(async () => { await replyCustomerThread(activeThread.thread.public_id, new FormData(form).get('body')); setActiveThread(await getCustomerThread(activeThread.thread.public_id)); form.reset(); }, 'Reply sent.'); };
  const refer = (event) => { event.preventDefault(); const form = event.currentTarget; act(async () => { await createCustomerReferral({ ...Object.fromEntries(new FormData(form)), organization: org.public_id }); await refresh(); form.reset(); }, 'Referral recorded. Thank you for sharing FAMtastic.'); };
  const saveWebsiteRequest = (event) => { event.preventDefault(); const form = event.currentTarget; const data = Object.fromEntries(new FormData(form)); data.organization = org.public_id; data.recommendation_requested = new FormData(form).has('recommendation_requested'); data.action = event.nativeEvent.submitter?.value || 'save'; act(async () => { const result = editingRequest?.public_id ? await updateWebsiteRequest(editingRequest.public_id, data) : await createWebsiteRequest(data); await refresh(); setEditingRequest(result.website_request); }, data.action === 'submit' ? 'Website request submitted. Your receipt, owner alert, and proof routine are queued.' : 'Draft saved. You can return anytime.'); };
  const uploadReference = (event) => { event.preventDefault(); const form = event.currentTarget; act(async () => { const result = await uploadWebsiteRequestAsset(editingRequest.public_id, new FormData(form)); await refresh(); setEditingRequest((current) => ({ ...current, assets: result.duplicate ? current.assets : [...(current.assets || []), result.asset] })); form.reset(); }, 'Reference file saved securely with this website request.'); };
  const decideProof = async (requestId, payload) => {
    const result = await act(async () => { const decision = await decideWebsiteRequestProof(requestId, payload); await refresh(); return decision; }, payload.action === 'revision' ? 'Changes requested. Fritz has your notes.' : 'Selection saved. Your chosen direction is highlighted below.');
    return result.ok;
  };
  const shareProof = async (requestId, action) => {
    const message = action === 'disable' ? 'Unlisted sharing is off. The previous link no longer works.' : action === 'rotate' ? 'A new unlisted link is ready. The previous link no longer works.' : 'Unlisted sharing is on. Copy the link below when you are ready.';
    const result = await act(async () => { await updateWebsiteRequestProofShare(requestId, action); await refresh(); }, message);
    return result.ok;
  };

  return <div className={`portal-app ${menu ? 'menu-open' : ''}`}>
    <button className="portal-menu-toggle" type="button" aria-expanded={menu} aria-controls="portal-drawer" onClick={() => setMenu(!menu)}><span aria-hidden="true">☰</span><span>Menu</span></button>
    {menu && <button className="portal-scrim" aria-label="Close menu" onClick={() => setMenu(false)} />}
    <aside id="portal-drawer" className="portal-nav">
      <div className="portal-nav-head"><Link className="portal-logo" to="/">FAM<span>tastic</span></Link><button type="button" aria-label="Close menu" onClick={() => setMenu(false)}>×</button></div>
      <div className="portal-workspace"><small>Customer workspace</small><strong>{org?.name}</strong><span>{session.customer.email}</span><em>{org?.role}</em></div>
      <nav aria-label="Customer portal">{GROUPS.map(([group, items]) => <section key={group}><h2>{group}</h2>{items.map(([id, label]) => <button key={id} aria-current={section === id ? 'page' : undefined} className={section === id ? 'active' : ''} onClick={() => go(id)}>{label}{id === 'messages' && workspace.threads.filter((t) => t.status === 'open').length > 0 && <b>{workspace.threads.filter((t) => t.status === 'open').length}</b>}</button>)}</section>)}</nav>
      <button className="portal-signout" onClick={() => act(async () => { await customerLogout(); navigate('/login'); })}>Sign out</button>
    </aside>
    <main className="portal-main">
      <header><div><span>FAMtastic customer portal</span><h1>{LABELS[section]}</h1></div><div className="portal-user"><i>{session.customer.display_name.slice(0, 1).toUpperCase()}</i><span>{session.customer.display_name}<small>{session.customer.email}</small></span></div></header>
      {notice && <div className="portal-notice" role="status">{notice}<button aria-label="Dismiss" onClick={() => setNotice('')}>×</button></div>}
      {error && <div className="portal-notice portal-notice--error" role="alert">{error}<button aria-label="Dismiss" onClick={() => setError('')}>×</button></div>}

      {section === 'home' && <PortalHome workspace={workspace} org={org} order={order} project={project} nextAction={nextAction} go={go} catalog={catalog} />}

      {section === 'activity' && <Panel eyebrow="Your history" title="Work, milestones, and account events">{workspace.activity.length ? <ul>{workspace.activity.map((item, i) => <li key={i}><strong>{item.summary}</strong><small>{date(item.created)}</small></li>)}</ul> : <Empty>No activity yet.</Empty>}</Panel>}
      {section === 'services' && <PortalServices workspace={workspace} catalog={catalog} go={go} />}
      {section === 'performance' && <section className="portal-grid two"><Panel eyebrow="Growth analytics" title={workspace.analytics.entitled ? 'Your performance dashboard' : 'Performance preview'} className="lime"><p>{workspace.analytics.entitled ? 'Visits, leads, and conversion insights are active for this workspace.' : 'Connect visits to leads and receive plain-language next actions.'}</p><a href="https://analytics.google.com/" target="_blank" rel="noreferrer">Open Google Analytics ↗</a></Panel><Panel eyebrow="Coming into focus" title="Business outcomes"><p>Lead capture, search visibility, website health, reviews, and AI-agent outcomes will consolidate here as each capability is connected.</p></Panel></section>}
      {section === 'projects' && <>
        <section className="portal-project-hero"><div><span>One account. Every website.</span><h2>Start, save, and return when you’re ready.</h2><p>Tell us about a new site, landing page, redesign, or online store. Each request keeps its own intake, purchase, files, messages, and delivery history.</p></div><button onClick={() => setEditingRequest({})}>+ Start a new website</button></section>
        {editingRequest && <Panel key={editingRequest.public_id || 'new-request'} eyebrow={editingRequest.public_id ? 'Continue request' : 'New website request'} title={editingRequest.project_name || 'Tell us what you want to build'} className="portal-request-form"><form onSubmit={saveWebsiteRequest}>
          <div className="portal-form-grid"><label>Request name<input name="project_name" defaultValue={editingRequest.project_name || ''} placeholder="Example: Sweet Crumbs Bakery website" required /></label><label>Business name<input name="business_name" defaultValue={editingRequest.business_name || ''} autoComplete="organization" /></label></div>
          <div className="portal-form-grid"><label>What are we building?<select name="project_type" defaultValue={editingRequest.project_type || 'new_website'}><option value="new_website">New website</option><option value="landing_page">Landing page</option><option value="redesign">Website redesign</option><option value="online_store">Online store / shopping cart</option></select></label><label>Domain plan<select name="domain_choice" defaultValue={editingRequest.domain_choice || 'undecided'}><option value="undecided">I’m not sure yet</option><option value="new_domain">I need a new domain</option><option value="existing_domain">I already own a domain</option></select></label></div>
          <label>Existing domain, if any<input name="existing_domain" defaultValue={editingRequest.existing_domain || ''} inputMode="url" placeholder="example.com" /></label>
          <div className="portal-form-grid"><label>Estimated number of pages<input name="page_count" type="number" min="1" max="100" defaultValue={editingRequest.intake?.page_count || 1} /></label><label>Who makes the final decision?<input name="decision_makers" defaultValue={editingRequest.intake?.decision_makers || ''} /></label></div>
          <label>What should this website accomplish?<textarea name="primary_goal" defaultValue={editingRequest.intake?.primary_goal || ''} placeholder="Example: take cake orders and explain pickup options" /></label>
          <label>Other goals and how you will measure success<textarea name="secondary_goals" defaultValue={editingRequest.intake?.secondary_goals || ''} /><textarea name="success_metrics" defaultValue={editingRequest.intake?.success_metrics || ''} placeholder="Calls, quote requests, bookings, sales…" /></label>
          <label>What does the business sell or provide?<textarea name="products_services" defaultValue={editingRequest.intake?.products_services || ''} /></label>
          <label>Who should the website reach, and what problem are they trying to solve?<textarea name="ideal_customer" defaultValue={editingRequest.intake?.ideal_customer || ''} /><textarea name="customer_pain_points" defaultValue={editingRequest.intake?.customer_pain_points || ''} /></label>
          <label>What should visitors do next?<textarea name="desired_actions" defaultValue={editingRequest.intake?.desired_actions || ''} placeholder="Call, submit a quote, book, visit, buy…" /></label>
          <label>Pages or sections you expect<textarea name="page_list" defaultValue={editingRequest.intake?.page_list || ''} placeholder="Home, About, Services, Gallery, Contact…" /></label>
          <label>Features you think you need<textarea name="required_features" defaultValue={editingRequest.intake?.required_features || ''} placeholder="Online ordering, booking, quote form, gallery…" /></label>
          <label>Tools or integrations you already use<textarea name="integrations" defaultValue={editingRequest.intake?.integrations || ''} placeholder="Square, Stripe, Calendly, Mailchimp, CRM…" /></label>
          <div className="portal-form-grid"><label>Content readiness<select name="content_status" defaultValue={editingRequest.intake?.content_status || ''}><option value="">Choose one</option><option value="ready">Copy and photos are ready</option><option value="partial">Some content is ready</option><option value="help_needed">I need help creating content</option></select></label><label>Desired timing<input name="launch_timing" defaultValue={editingRequest.intake?.launch_timing || ''} placeholder="A date or flexible" /></label></div>
          <div className="portal-form-grid"><label>Copywriting help<textarea name="copywriting_needs" defaultValue={editingRequest.intake?.copywriting_needs || ''} /></label><label>Photos and assets<textarea name="photo_asset_status" defaultValue={editingRequest.intake?.photo_asset_status || ''} /></label></div>
          <label>Brand/logo status<select name="brand_status" defaultValue={editingRequest.intake?.brand_status || ''}><option value="">Choose one</option><option value="ready">Brand and logo ready</option><option value="partial">Some brand pieces exist</option><option value="help_needed">I need brand help</option></select></label>
          <fieldset className="portal-creative-scale"><legend>How FAMtastic should your website feel?</legend><p>This controls creative intensity, not quality. 0 is safest and most familiar; 5 is balanced and distinct; 10 is cinematic, immersive, and maximum FAMtastic.</p><input name="famtastic_level" type="range" min="0" max="10" step="1" defaultValue={editingRequest.intake?.famtastic_level ?? 5} onInput={(event) => { event.currentTarget.nextElementSibling.textContent = event.currentTarget.value; }} /><output>{editingRequest.intake?.famtastic_level ?? 5}</output><label className="portal-check"><input name="allow_bolder_direction" type="checkbox" defaultChecked={editingRequest.intake?.allow_bolder_direction} />Let one concept intentionally push beyond my selected level.</label></fieldset>
          <label>Overall style notes<textarea name="style_preferences" defaultValue={editingRequest.intake?.style_preferences || ''} /></label>
          <div className="portal-form-grid"><label>Preferred colors<textarea name="preferred_colors" defaultValue={editingRequest.intake?.preferred_colors || ''} placeholder="Names, hex codes, or describe a palette" /></label><label>Colors or styles to avoid<textarea name="colors_to_avoid" defaultValue={editingRequest.intake?.colors_to_avoid || ''} /><textarea name="styles_to_avoid" defaultValue={editingRequest.intake?.styles_to_avoid || ''} /></label></div>
          <label>How should visitors feel?<textarea name="desired_feeling" defaultValue={editingRequest.intake?.desired_feeling || ''} placeholder="Safe, energized, luxurious, playful, confident…" /></label>
          <label>Websites you like and competitors<textarea name="reference_sites" defaultValue={editingRequest.intake?.reference_sites || ''} placeholder="One URL per line, plus what you like" /><textarea name="reference_site_reasons" defaultValue={editingRequest.intake?.reference_site_reasons || ''} placeholder="What specifically works or does not work for you?" /><textarea name="competitors" defaultValue={editingRequest.intake?.competitors || ''} /></label><label>Notes about flyers, images, or other visual references<textarea name="visual_reference_notes" defaultValue={editingRequest.intake?.visual_reference_notes || ''} /></label>
          <div className="portal-form-grid"><label>Search phrases customers use<textarea name="seo_keywords" defaultValue={editingRequest.intake?.seo_keywords || ''} /></label><label>Locations you serve<textarea name="service_locations" defaultValue={editingRequest.intake?.service_locations || ''} /></label></div>
          <div className="portal-form-grid"><label>Business hours<textarea name="business_hours" defaultValue={editingRequest.intake?.business_hours || ''} /></label><label>Public contact details<textarea name="contact_details" defaultValue={editingRequest.intake?.contact_details || ''} /></label></div>
          <label>Social profiles<textarea name="social_profiles" defaultValue={editingRequest.intake?.social_profiles || ''} /></label>
          <div className="portal-form-grid"><label>Accessibility needs<textarea name="accessibility_needs" defaultValue={editingRequest.intake?.accessibility_needs || ''} /></label><label>Privacy or legal requirements<textarea name="privacy_legal_needs" defaultValue={editingRequest.intake?.privacy_legal_needs || ''} /></label></div>
          <label>Online store details<textarea name="ecommerce_details" defaultValue={editingRequest.intake?.ecommerce_details || ''} placeholder="Products, variants, taxes, inventory, payments…" /></label>
          <div className="portal-form-grid"><label>Approximate product count<input name="product_count" defaultValue={editingRequest.intake?.product_count || ''} /></label><label>Shipping, delivery, or pickup<textarea name="shipping_pickup" defaultValue={editingRequest.intake?.shipping_pickup || ''} /></label></div>
          <label>Booking or appointment details<textarea name="booking_details" defaultValue={editingRequest.intake?.booking_details || ''} /></label><label>AI agent goals<textarea name="ai_agent_goals" defaultValue={editingRequest.intake?.ai_agent_goals || ''} /></label><fieldset><legend>Optional AI brief enrichment</legend><label>Connection mode<select name="ai_enrichment_mode" defaultValue={editingRequest.intake?.ai_enrichment_mode || 'none'}><option value="none">No AI enrichment</option><option value="famtastic_managed">Use FAMtastic-managed models</option><option value="customer_managed">I want to connect my own provider later</option></select></label><label>Context for the AI reviewer<textarea name="ai_context_notes" defaultValue={editingRequest.intake?.ai_context_notes || ''} placeholder="Do not paste API keys or passwords." /></label><label className="portal-check"><input name="life_path_opt_in" type="checkbox" defaultChecked={editingRequest.intake?.life_path_opt_in} />Use optional life-path guidance only for voice and creative suggestions.</label></fieldset><label>Ongoing maintenance needs<textarea name="maintenance_needs" defaultValue={editingRequest.intake?.maintenance_needs || ''} /></label>
          <label>How does the business operate and make money today?<textarea name="business_model" defaultValue={editingRequest.intake?.business_model || ''} placeholder="How customers find you, buy, book, pay, and receive the product or service." /></label>
          <label>Industry and research context<textarea name="industry" defaultValue={editingRequest.intake?.industry || ''} placeholder="Use your own words—even if the industry is not listed anywhere." /><textarea name="research_context" defaultValue={editingRequest.intake?.research_context || ''} placeholder="Competitors, trade associations, regulations, customer behavior, or questions FAMtastic should research." /></label>
          <label>Current digital ownership and access<textarea name="existing_technology" defaultValue={editingRequest.intake?.existing_technology || ''} placeholder="Registrar, hosting company, email provider, website/CMS, analytics, repositories, agencies, or logins you control. Do not paste passwords." /></label>
          <div className="portal-form-grid"><label>Desired domain names<textarea name="desired_domains" defaultValue={editingRequest.intake?.desired_domains || ''} placeholder="List first choice and acceptable alternatives. Availability is verified before purchase." /></label><label>If the first-choice domain is unavailable<textarea name="domain_fallback" defaultValue={editingRequest.intake?.domain_fallback || ''} placeholder="Alternatives, words we may adjust, or request a conversation before choosing." /></label></div>
          <label>Business email needs<textarea name="business_email_needs" defaultValue={editingRequest.intake?.business_email_needs || ''} placeholder="Mailboxes such as info@ or sales@, number of users, full inboxes versus forwarding, and current provider." /></label>
          <label>Products, services, or requests not listed above<textarea name="custom_needs" defaultValue={editingRequest.intake?.custom_needs || ''} placeholder="Describe anything unusual. Unlisted requests go to human scope review instead of being discarded." /></label>
          <label>Budget context (optional)<textarea name="budget_context" defaultValue={editingRequest.intake?.budget_context || ''} placeholder="This does not lock you into a package; it helps us recommend the smallest useful solution." /></label><label>Anything else Fritz should know<textarea name="notes" defaultValue={editingRequest.intake?.notes || ''} /></label>
          <label className="portal-check"><input name="recommendation_requested" type="checkbox" defaultChecked={editingRequest.recommendation_requested !== 0} />Recommend the smallest useful package and add-ons for me.</label>
          <div className="portal-form-actions"><button name="action" value="save" disabled={busy}>{busy ? 'Saving…' : 'Save draft'}</button><button className="secondary" name="action" value="submit" disabled={busy}>Submit for review</button><button className="quiet" type="button" onClick={() => setEditingRequest(null)}>Close</button></div>
        </form>{editingRequest.public_id && <form className="portal-asset-upload" onSubmit={uploadReference}><h3>Add a flyer, logo, photo, or visual reference</h3><p>PNG, JPEG, WebP, or PDF up to 10 MB. Files stay private and attached to this request.</p><input name="asset" type="file" accept="image/png,image/jpeg,image/webp,application/pdf" required /><label className="portal-check"><input name="ownership_confirmed" type="checkbox" value="1" required />I own this file or have permission to share it for this project.</label><label className="portal-check"><input name="ai_use_consent" type="checkbox" value="1" />FAMtastic may use this file as reference for approved AI-assisted concept generation.</label><button disabled={busy}>{busy ? 'Uploading…' : 'Upload reference securely'}</button>{editingRequest.assets?.length > 0 && <ul>{editingRequest.assets.map((asset) => <li key={asset.public_id}>{asset.name} · {Math.ceil(asset.size_bytes / 1024)} KB</li>)}</ul>}</form>}</Panel>}
        <section className="portal-grid two portal-request-list">{workspace.website_requests?.map((request) => <Panel key={request.public_id} id={`website-request-${request.public_id}`} tabIndex={request.public_id === targetRequest ? -1 : undefined} className={request.public_id === targetRequest ? 'portal-request-target' : ''} eyebrow={request.status === 'converted' ? 'Purchased project request' : 'Website request'} title={request.project_name}><dl><div><dt>Status</dt><dd>{title(request.status)}</dd></div><div><dt>Proofs</dt><dd>{title(request.proof_review_status)}</dd></div><div><dt>Updated</dt><dd>{date(request.changed)}</dd></div></dl>{request.intake?.recommendation && <p><strong>Recommended path: {request.intake.recommendation.label}</strong><br /><small>{request.intake.recommendation.reasons?.join(' ')}</small></p>}{[3, 6].includes(request.proofs?.variants?.length) && <WebsiteProofReview request={request} busy={busy} onDecision={decideProof} onShare={shareProof} />}{!request.proofs && request.status === 'submitted' && <p>{request.proof_review_status === 'owner_review' ? 'Your concepts are complete and in FAMtastic quality review. We’ll notify you when the complete set is approved.' : 'Your brief is in the studio queue. We’ll notify you when your working concepts are ready.'}</p>}{!['converted', 'cancelled'].includes(request.status) && <><button onClick={() => setEditingRequest(request)}>Continue request</button>{request.direct_checkout_available && <button className="secondary" onClick={() => navigate(`/buy?request=${encodeURIComponent(request.public_id)}`)}>Purchase {request.intake?.recommendation?.label}</button>}</>}{request.status === 'converted' && <p>This request is connected to its paid project below.</p>}</Panel>)}</section>
        <section className="portal-grid">{workspace.projects.length ? workspace.projects.map((p) => <Panel key={p.uuid} eyebrow="Project command center" title={title(p.delivery_status)}><div className="portal-stage-line"><span className="complete">Paid</span><span className={p.proofs ? 'complete' : 'active'}>{p.proofs?.variants?.length || 3} concepts</span><span className={p.approval_status === 'approved' ? 'complete' : ''}>Approval</span><span className={p.live_url ? 'complete' : ''}>Launch</span></div><dl><div><dt>Approval</dt><dd>{title(p.approval_status)}</dd></div><div><dt>Revisions</dt><dd>{p.revision_count || 0} of {p.revision_limit || 1}</dd></div></dl>{[3, 6].includes(p.proofs?.variants?.length) && <div className="portal-proof-grid">{p.proofs.variants.map((proof) => <a key={proof.direction_id} href={proof.preview_url} target="_blank" rel="noreferrer" className={p.proofs.selected_variant === proof.direction_id ? 'selected' : ''}><b>{proof.direction_id.toUpperCase()}</b><strong>{proof.direction_name}</strong><span>Open concept ↗</span></a>)}</div>}{p.proofs?.generation_status === 'waiting_callback' && <p>Your concepts are being created now. We’ll email you when review opens.</p>}{p.live_url && <a href={p.live_url}>Visit live site ↗</a>}</Panel>) : !workspace.website_requests?.length && <Panel eyebrow="Projects" title="No website requests yet"><p>Start with a short, reusable brief. Your detailed onboarding continues here after purchase.</p></Panel>}</section>
      </>}
      {section === 'messages' && <section className="portal-grid two"><Panel eyebrow="Conversations" title="Your history">{workspace.threads.length ? <ul className="portal-thread-list">{workspace.threads.map((thread) => <li key={thread.public_id}><button type="button" onClick={() => viewThread(thread.public_id)}><strong>{thread.subject}</strong><small>{title(thread.status)} · {date(thread.changed)}</small></button></li>)}</ul> : <Empty>No conversations yet.</Empty>}</Panel>{activeThread ? <Panel eyebrow={title(activeThread.thread.kind)} title={activeThread.thread.subject} className="portal-conversation"><button className="portal-back" onClick={() => setActiveThread(null)}>← All conversations</button><ol>{activeThread.messages.map((message, i) => <li key={i} className={`is-${message.author_type}`}><span>{message.author_type === 'customer' ? 'You' : 'FAMtastic team'}</span><p>{message.body}</p><small>{date(message.created)}</small></li>)}</ol><form onSubmit={replyThread}><label htmlFor="thread-reply">Reply</label><textarea id="thread-reply" name="body" required /><button disabled={busy}>{busy ? 'Sending…' : 'Send reply'}</button></form></Panel> : <Panel eyebrow="New conversation" title="Ask FAMtastic"><p>Choose the affected area so your request reaches us with the right context.</p><form onSubmit={openThread}><label>Area<select name="kind"><option value="support">Website or service issue</option><option value="project">Project or approval</option><option value="billing">Billing or renewal</option></select></label><label>Subject<input name="subject" required /></label><label>What happened?<textarea name="body" required /></label><button disabled={busy}>{busy ? 'Sending…' : 'Send request'}</button></form></Panel>}</section>}
      {section === 'support' && <><section className="portal-support-choices"><button onClick={() => go('messages')}><b>Website or service issue</b><span>Report something incorrect, unavailable, or broken.</span></button><button onClick={() => go('messages')}><b>Request a change</b><span>Update content, hours, services, or business information.</span></button><button onClick={() => go('faq')}><b>Find an answer</b><span>Search FAQs before waiting for a response.</span></button><a href="mailto:hello@famtasticdesigns.com?subject=Urgent%20customer%20support"><b>Urgent business impact</b><span>Email the team when your website, checkout, domain, or leads are unavailable.</span></a></section><Panel eyebrow="Support record" title={`${workspace.threads.filter((t) => t.status === 'open').length} open requests`}><p>Your requests and replies stay attached to your customer workspace.</p><button onClick={() => go('messages')}>View conversations</button></Panel></>}
      {section === 'learn' && <><Panel eyebrow="Personalize your feed" title="Follow topics that help your business"><p>Choose topics in Settings and control whether you receive educational email or only see them here.</p><button onClick={() => go('settings')}>Manage topic subscriptions</button></Panel><section className="portal-grid three portal-articles">{workspace.articles.length ? workspace.articles.map((article) => <Panel key={article.id} eyebrow={article.topic} title={article.title}><p>{article.excerpt || 'Read the latest practical guidance from FAMtastic Designs.'}</p><a href={article.url}>Read article →</a></Panel>) : <Panel eyebrow="Latest articles" title="New guidance is coming"><p>Published articles from the FAMtastic library will appear here automatically.</p></Panel>}</section></>}
      {section === 'faq' && <><label className="portal-search" htmlFor="faq-search"><span>Search questions and answers</span><input id="faq-search" type="search" value={faqSearch} onChange={(event) => setFaqSearch(event.target.value)} placeholder="Try: domain, hosting, AI agent, billing…" /></label><section className="portal-faqs">{filteredFaqs.length ? filteredFaqs.map((faq) => <details key={faq.id}><summary>{faq.question}</summary><p>{faq.answer}</p><small>{faq.category}</small></details>) : <Panel eyebrow="No answer found" title="Ask FAMtastic"><p>Start a support request and we’ll keep the answer with your account.</p><button onClick={() => go('support')}>Get help</button></Panel>}</section></>}
      {section === 'grow' && <section className="portal-grid three">{workspace.offers.map((offer) => <Panel key={offer.key} eyebrow="Recommended next step" title={offer.title} className="offer"><p>{offer.description}</p><small>Recommended from your current services and project stage.</small><a href={`/contact?service=${offer.key}`}>Ask how this helps →</a></Panel>)}<Panel eyebrow="Not sure what you need?" title="Have FAMtastic handle it" className="lime"><p>Tell us the outcome you want. We’ll recommend the smallest useful next step.</p><button onClick={() => go('support')}>Describe my goal</button></Panel></section>}
      {section === 'referrals' && <section className="portal-grid two"><Panel eyebrow="Refer a friend" title="Help another business get online"><p>Share FAMtastic only with someone who has agreed to hear from us. We’ll track the introduction without displaying their private activity.</p><form onSubmit={refer}><label>Friend’s name<input name="friend_name" required /></label><label>Friend’s email<input name="friend_email" type="email" inputMode="email" required /></label><label className="portal-check"><input name="permission_confirmed" type="checkbox" value="1" required />They gave me permission to share their information.</label><button disabled={busy}>{busy ? 'Recording…' : 'Record referral'}</button></form><div className="portal-share"><a href="sms:?&body=FAMtastic%20Designs%20can%20help%20you%20get%20online:%20https://famtasticdesigns.com">Text a friend</a><a href="mailto:?subject=FAMtastic%20Designs&body=Take%20a%20look:%20https://famtasticdesigns.com">Email a friend</a></div></Panel><Panel eyebrow="Your referrals" title={`${workspace.referrals.length} shared`}>{workspace.referrals.length ? <ul>{workspace.referrals.map((referral) => <li key={referral.public_id}><strong>{referral.friend_name}</strong><small>{title(referral.status)} · {title(referral.reward_status)}</small></li>)}</ul> : <Empty>Your referral history and earned rewards will appear here.</Empty>}</Panel></section>}
      {section === 'billing' && <section className="portal-grid two">{workspace.orders.length ? workspace.orders.map((purchase) => <Panel key={purchase.uuid} eyebrow="Purchase" title={money(purchase.amount, purchase.currency)}><dl><div><dt>Package</dt><dd>{title(purchase.package)}</dd></div><div><dt>Payment</dt><dd>{title(purchase.payment_status)}</dd></div><div><dt>Date</dt><dd>{date(purchase.created)}</dd></div></dl></Panel>) : <Panel eyebrow="Purchases" title="No purchases yet"><p>Your orders, receipts, and renewal information will appear here.</p></Panel>}<Panel eyebrow="Payment security" title="Secure billing"><p>Payment methods are managed through Stripe. FAMtastic never stores card details in the portal.</p></Panel></section>}
      {section === 'account' && <section className="portal-grid two"><Panel eyebrow="Profile" title="Contact information"><form onSubmit={saveProfile}><label>Name<input name="display_name" defaultValue={session.customer.display_name} /></label><label>Phone<input name="phone" defaultValue={session.customer.phone} inputMode="tel" /></label><button disabled={busy}>{busy ? 'Saving…' : 'Save profile'}</button></form></Panel><Panel eyebrow="Workspace access" title="Team members"><ul>{workspace.members.map((member) => <li key={member.public_id}><strong>{member.display_name}</strong><small>{member.email} · {title(member.role)}</small></li>)}</ul></Panel></section>}
      {section === 'settings' && <Panel eyebrow="Settings" title="Notifications, education, and promotions"><form onSubmit={saveSettings} className="portal-settings"><fieldset><legend>Essential account messages</legend><label className="portal-check"><input type="checkbox" name="project_email" value="1" defaultChecked={workspace.preferences.project_email} />Project updates and approvals by email</label><label className="portal-check"><input type="checkbox" name="support_email" value="1" defaultChecked={workspace.preferences.support_email} />Support replies by email</label><label className="portal-check"><input type="checkbox" name="billing_email" value="1" defaultChecked={workspace.preferences.billing_email} />Billing and renewal notices by email</label><small>Critical security and account notices may still be sent when required to operate your services.</small></fieldset><fieldset><legend>Education and offers</legend><label className="portal-check"><input type="checkbox" name="product_education" value="1" defaultChecked={workspace.preferences.product_education} />Helpful product education</label><label className="portal-check"><input type="checkbox" name="deals_promotions" value="1" defaultChecked={workspace.preferences.deals_promotions} />Deals, promotions, and relevant service offers</label><label>Analytics summary<select name="analytics_digest" defaultValue={workspace.preferences.analytics_digest}><option value="off">Off</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label></fieldset><fieldset><legend>Topics I follow</legend><div className="portal-topic-grid">{Object.entries(workspace.topics).map(([key, label]) => <label className="portal-check" key={key}><input type="checkbox" name="topics" value={key} defaultChecked={workspace.preferences.topics.includes(key)} />{label}</label>)}</div></fieldset><button disabled={busy}>{busy ? 'Saving…' : 'Save settings'}</button></form></Panel>}
    </main>
  </div>;
}
