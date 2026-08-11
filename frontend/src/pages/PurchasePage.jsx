import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { createCommerceCheckout, customerSession, getCustomerCatalog } from '../api/customer.js';

const BASE_SKU = 'FAM-FOOT-199';
const money = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));

export default function PurchasePage() {
  const [searchParams] = useSearchParams();
  const websiteRequest = searchParams.get('request') || '';
  const [state, setState] = useState({ loading: true, session: null, products: [], terms: null, error: '' });
  const [selected, setSelected] = useState([]);
  const [domainChoice, setDomainChoice] = useState('');
  const [renewal, setRenewal] = useState(false);
  const [terms, setTerms] = useState(false);
  const [marketing, setMarketing] = useState(false);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    Promise.all([customerSession().catch(() => null), getCustomerCatalog()])
      .then(([session, catalog]) => setState({ loading: false, session, products: catalog.products || [], terms: catalog.terms, error: '' }))
      .catch((error) => setState({ loading: false, session: null, products: [], terms: null, error: error.message }));
  }, []);
  const base = state.products.find((item) => item.sku === BASE_SKU);
  const addons = state.products.filter((item) => item.type === 'add_on' && item.billing?.kind !== 'recurring');
  const total = useMemo(() => [base, ...addons.filter((item) => selected.includes(item.sku))].filter(Boolean).reduce((sum, item) => sum + Number(item.price), 0), [base, addons, selected]);
  const organization = state.session?.organizations?.[0];
  async function checkout(event) {
    event.preventDefault(); setBusy(true); setState((current) => ({ ...current, error: '' }));
    try {
      const result = await createCommerceCheckout({ organization: organization.public_id, website_request: websiteRequest, skus: [BASE_SKU, ...selected], domain_choice: domainChoice, recurring_authorized: renewal, accept_terms: terms, terms_version: state.terms.version, marketing_opt_in: marketing });
      window.location.assign(result.checkout_url);
    } catch (error) { setState((current) => ({ ...current, error: error.message })); setBusy(false); }
  }
  if (state.loading) return <div className="v1-loading" role="status">Preparing secure checkout…</div>;
  if (!state.session) return <section className="purchase-shell"><span>Account-protected checkout</span><h1>Start with Web Basics</h1><p>Create or sign in to your customer account first. That keeps this purchase, your project, support, hosting, and future add-ons together.</p><Link className="btn btn--lime" to="/login?redirect=%2Fbuy">Sign in or create account</Link></section>;
  return <form className="purchase-shell" onSubmit={checkout}>
    <span>Secure Commerce checkout</span><h1>{base?.title || 'Web Basics Bundle'}</h1><p>{base?.summary}</p>{websiteRequest && <p className="purchase-context">This purchase will activate the website request you selected in your portal.</p>}
    {state.error && <div className="alert alert--error" role="alert">{state.error}</div>}
    <fieldset><legend>Domain setup</legend><label><input type="radio" name="domain" value="new_domain" checked={domainChoice === 'new_domain'} onChange={(e) => setDomainChoice(e.target.value)} required /> Register a new customer-owned domain for the included first year</label><label><input type="radio" name="domain" value="existing_domain" checked={domainChoice === 'existing_domain'} onChange={(e) => setDomainChoice(e.target.value)} /> Connect a domain I already own</label></fieldset>
    <fieldset><legend>Useful add-ons</legend>{addons.map((item) => <label key={item.sku}><input type="checkbox" checked={selected.includes(item.sku)} onChange={(e) => setSelected((current) => e.target.checked ? [...current, item.sku] : current.filter((sku) => sku !== item.sku))} /> <b>{item.title}</b> — {money(item.price)}<small>{item.summary}</small></label>)}</fieldset>
    <label className="purchase-consent"><input type="checkbox" checked={renewal} onChange={(e) => setRenewal(e.target.checked)} required /> I authorize basic hosting to renew at $9.99/month after the included first year. I can cancel before renewal from my account.</label>
    <label className="purchase-consent"><input type="checkbox" checked={terms} onChange={(e) => setTerms(e.target.checked)} required /> I accept the recorded product scope, payment, cancellation, renewal, and domain terms.</label>
    <label className="purchase-consent"><input type="checkbox" checked={marketing} onChange={(e) => setMarketing(e.target.checked)} /> Send me useful articles and relevant offers. I can unsubscribe without affecting service messages.</label>
    <div className="purchase-total"><span>Due today</span><strong>{money(total)}</strong></div><button className="btn btn--lime" disabled={busy || !base}>{busy ? 'Opening secure payment…' : 'Continue to secure payment'}</button>
  </form>;
}
