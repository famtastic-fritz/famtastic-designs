import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { customerLogout, customerSession, createCustomerThread, getCustomerWorkspace, updateCustomerProfile } from '../api/customer.js';
import '../portal.css';

const NAV = ['home', 'projects', 'files', 'messages', 'purchases', 'services', 'domains', 'support', 'team', 'account', 'grow'];
const LABELS = { home: 'Home', projects: 'Projects', files: 'Files & approvals', messages: 'Messages', purchases: 'Purchases', services: 'Services', domains: 'Domains & hosting', support: 'Support', team: 'Team', account: 'Account', grow: 'Grow my business' };
const title = (value) => String(value || 'Preparing').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const money = (amount = 0, currency = 'usd') => new Intl.NumberFormat('en-US', { style: 'currency', currency: currency.toUpperCase() }).format(amount / 100);
const date = (stamp) => stamp ? new Date(stamp * 1000).toLocaleDateString() : 'Not scheduled';

function Panel({ eyebrow, title: heading, children, className = '' }) { return <article className={`portal-panel ${className}`}><span>{eyebrow}</span>{heading && <h2>{heading}</h2>}{children}</article>; }

export default function CustomerPortalDashboard() {
  const navigate = useNavigate();
  const [section, setSection] = useState('home');
  const [session, setSession] = useState(null);
  const [workspace, setWorkspace] = useState(null);
  const [state, setState] = useState('loading');
  const [notice, setNotice] = useState('');
  useEffect(() => { Promise.all([customerSession(), getCustomerWorkspace()]).then(([s, w]) => { setSession(s); setWorkspace(w); setState('ready'); }).catch(() => navigate('/login', { replace: true })); }, [navigate]);
  const org = workspace?.organization;
  const project = workspace?.projects?.[0];
  const order = workspace?.orders?.[0];
  const hosting = workspace?.entitlements?.find((item) => item.entitlement_type === 'hosting');
  const domain = workspace?.entitlements?.find((item) => item.entitlement_type === 'domain');
  const nextAction = useMemo(() => !order ? 'Choose your first service' : order.payment_status !== 'paid' ? 'Complete your purchase' : !project ? 'Complete your project brief' : project.approval_status !== 'approved' ? 'Review your project' : 'See your growth opportunities', [order, project]);
  if (state === 'loading') return <div className="portal-state"><i />Opening your customer command center…</div>;
  const saveProfile = async (event) => { event.preventDefault(); const form = new FormData(event.currentTarget); const result = await updateCustomerProfile(Object.fromEntries(form)); setSession(result); setNotice('Account updated.'); };
  const openThread = async (event) => { event.preventDefault(); const formElement = event.currentTarget; const form = new FormData(formElement); await createCustomerThread({ ...Object.fromEntries(form), organization: org.public_id }); const refreshed = await getCustomerWorkspace(org.public_id); setWorkspace(refreshed); setNotice('Your message was sent.'); formElement.reset(); };
  return <div className="portal-app">
    <aside className="portal-nav">
      <Link className="portal-logo" to="/">FAM<span>tastic</span></Link>
      <div className="portal-workspace"><small>Workspace</small><strong>{org?.name}</strong><em>{org?.role}</em></div>
      <nav>{NAV.map((id) => <button key={id} className={section === id ? 'active' : ''} onClick={() => setSection(id)}>{LABELS[id]}</button>)}</nav>
      <button className="portal-signout" onClick={async () => { await customerLogout(); navigate('/login'); }}>Sign out</button>
    </aside>
    <main className="portal-main">
      <header><div><span>FAMtastic customer portal</span><h1>{LABELS[section]}</h1></div><div className="portal-user"><i>{session.customer.display_name.slice(0, 1).toUpperCase()}</i><span>{session.customer.display_name}<small>{session.customer.email}</small></span></div></header>
      {notice && <div className="portal-notice" role="status">{notice}<button onClick={() => setNotice('')}>×</button></div>}
      {section === 'home' && <>
        <section className="portal-welcome"><div><span>Welcome back</span><h2>{org?.name}</h2><p>Your projects, purchases, support, and next opportunities are together in one place.</p></div><div><small>Next best action</small><strong>{nextAction}</strong><button onClick={() => setSection(!order ? 'grow' : 'projects')}>Continue →</button></div></section>
        <section className="portal-grid three"><Panel eyebrow="Active project" title={project ? title(project.delivery_status) : 'Ready when you are'}><p>{project ? 'Track delivery, proofs, and approvals.' : 'Your first project will appear here after purchase.'}</p></Panel><Panel eyebrow="Services" title={`${workspace.entitlements.length} active`}><p>Hosting, domains, subscriptions, and feature access.</p></Panel><Panel eyebrow="Support" title={`${workspace.threads.length} conversations`}><p>Every conversation stays attached to your account.</p></Panel></section>
        <section className="portal-grid two"><Panel eyebrow="Recent activity" title="What’s happening">{workspace.activity.length ? <ul>{workspace.activity.map((item, i) => <li key={i}><strong>{item.summary}</strong><small>{date(item.created)}</small></li>)}</ul> : <p>Your account activity will appear here.</p>}</Panel><Panel eyebrow="Recommended" title={workspace.offers[0]?.title || 'You’re all set'} className="lime"><p>{workspace.offers[0]?.description || 'We will show relevant opportunities as your business grows.'}</p><button onClick={() => setSection('grow')}>Explore opportunities →</button></Panel></section>
      </>}
      {section === 'projects' && <section className="portal-grid">{workspace.projects.length ? workspace.projects.map((p) => <Panel key={p.uuid} eyebrow="Website project" title={title(p.delivery_status)}><dl><div><dt>Approval</dt><dd>{title(p.approval_status)}</dd></div><div><dt>Revisions</dt><dd>{p.revision_count || 0} of {p.revision_limit || 1}</dd></div></dl>{p.proof_url && <a href={p.proof_url}>Open proof ↗</a>}{p.live_url && <a href={p.live_url}>Visit live site ↗</a>}</Panel>) : <Panel eyebrow="Projects" title="No active projects"><p>Purchased projects will appear automatically.</p></Panel>}</section>}
      {section === 'files' && <Panel eyebrow="Files & approvals" title="A permanent delivery record"><p>Project uploads and delivered assets will remain organized by project. File upload activation follows the authenticated storage migration.</p>{project && <p><strong>Current approval:</strong> {title(project.approval_status)}</p>}</Panel>}
      {section === 'messages' && <section className="portal-grid two"><Panel eyebrow="Conversations" title="Project threads">{workspace.threads.length ? <ul>{workspace.threads.map((t) => <li key={t.public_id}><strong>{t.subject}</strong><small>{title(t.status)} · {date(t.changed)}</small></li>)}</ul> : <p>No conversations yet.</p>}</Panel><Panel eyebrow="New message" title="Start a conversation"><form onSubmit={openThread}><select name="kind"><option value="project">Project</option><option value="support">Support</option><option value="billing">Billing</option></select><input name="subject" placeholder="Subject" required /><textarea name="body" placeholder="How can we help?" required /><button>Send message</button></form></Panel></section>}
      {section === 'purchases' && <section className="portal-grid">{workspace.orders.length ? workspace.orders.map((o) => <Panel key={o.uuid} eyebrow="Purchase" title={money(o.amount, o.currency)}><dl><div><dt>Package</dt><dd>{title(o.package)}</dd></div><div><dt>Payment</dt><dd>{title(o.payment_status)}</dd></div><div><dt>Date</dt><dd>{date(o.created)}</dd></div></dl></Panel>) : <Panel eyebrow="Purchases" title="No purchases yet"><button onClick={() => setSection('grow')}>Explore services</button></Panel>}</section>}
      {section === 'services' && <section className="portal-grid three">{workspace.entitlements.map((e) => <Panel key={e.public_id} eyebrow="Service" title={title(e.entitlement_type)}><p>{title(e.status)}</p><small>Included through {date(e.included_until)}</small></Panel>)}</section>}
      {section === 'domains' && <section className="portal-grid two"><Panel eyebrow="Customer-owned" title="Domain registration"><p>{domain ? title(domain.status) : 'No managed domain yet.'}</p><small>Annual prepaid renewal stays separate from hosting.</small></Panel><Panel eyebrow="FAMtastic-managed" title="Hosting"><p>{hosting ? title(hosting.status) : 'No hosting service yet.'}</p><small>{hosting ? `${money(hosting.amount_minor)} / ${hosting.billing_interval} after ${date(hosting.included_until)}` : 'The $199 launch includes the first year.'}</small></Panel></section>}
      {section === 'support' && <section className="portal-grid two"><Panel eyebrow="Support" title="We keep the context"><p>Open a persistent request instead of starting another email chain.</p><button onClick={() => setSection('messages')}>Message support →</button></Panel><Panel eyebrow="Current requests" title={`${workspace.threads.filter((t) => t.status === 'open').length} open`}><p>Billing, project, and support conversations are kept with your workspace.</p></Panel></section>}
      {section === 'team' && <Panel eyebrow="Workspace access" title="Team members"><ul>{workspace.members.map((m) => <li key={m.public_id}><strong>{m.display_name}</strong><small>{m.email} · {title(m.role)}</small></li>)}</ul><p>Owner-controlled invitations and access removal use the organization membership model.</p></Panel>}
      {section === 'account' && <Panel eyebrow="Account" title="Contact and preferences"><form onSubmit={saveProfile}><label>Name<input name="display_name" defaultValue={session.customer.display_name} /></label><label>Phone<input name="phone" defaultValue={session.customer.phone} /></label><label>Marketing preferences<select name="marketing_status" defaultValue={session.customer.marketing_status}><option value="subscribed">Product news and relevant offers</option><option value="unsubscribed">Transactional messages only</option></select></label><button>Save account</button></form></Panel>}
      {section === 'grow' && <section className="portal-grid three">{workspace.offers.map((offer) => <Panel key={offer.key} eyebrow="Recommended for you" title={offer.title} className="offer"><p>{offer.description}</p><a href={`/contact?service=${offer.key}`}>Ask about this →</a></Panel>)}<Panel eyebrow="Growth analytics" title={workspace.analytics.entitled ? 'Your dashboard is active' : 'Unlock clearer decisions'} className="lime"><p>{workspace.analytics.entitled ? 'Visits, leads, and conversion insights will appear here.' : 'See which traffic becomes business and what to improve next.'}</p>{!workspace.analytics.entitled && <a href="/contact?service=analytics">Explore analytics →</a>}</Panel></section>}
    </main>
  </div>;
}
