import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { createCommerceCheckout, customerSession, getCustomerCatalog, getCustomerWorkspace } from '../api/customer.js';

const WEBSITE_SKUS = ['FAM-FOOT-199', 'FAM-BUSINESS-499'];
const money = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));

export default function PurchasePage() {
  const [searchParams] = useSearchParams();
  const websiteRequest = searchParams.get('request') || '';
  const [state, setState] = useState({ loading: true, session: null, workspace: null, products: [], terms: null, error: '' });
  const [baseSku, setBaseSku] = useState('');
  const [selected, setSelected] = useState([]);
  const [domainChoice, setDomainChoice] = useState('');
  const [renewal, setRenewal] = useState(false);
  const [terms, setTerms] = useState(false);
  const [marketing, setMarketing] = useState(false);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    Promise.all([customerSession().catch(() => null), getCustomerCatalog(), getCustomerWorkspace().catch(() => null)])
      .then(([session, catalog, workspace]) => {
        const request = workspace?.website_requests?.find((item) => item.public_id === websiteRequest);
        setBaseSku(request?.recommended_sku || '');
        setSelected(request?.intake?.recommendation?.suggested_addon_skus || []);
        setState({ loading: false, session, workspace, products: catalog.products || [], terms: catalog.terms, error: '' });
      })
      .catch((error) => setState({ loading: false, session: null, products: [], terms: null, error: error.message }));
  }, []);
  const base = state.products.find((item) => item.sku === baseSku);
  const requestRecord = state.workspace?.website_requests?.find((item) => item.public_id === websiteRequest);
  const displayedBasePrice = requestRecord?.private_offer ? requestRecord.private_offer.offered_amount_minor / 100 : Number(base?.price || 0);
  const websiteBundles = state.products.filter((item) => WEBSITE_SKUS.includes(item.sku));
  const addons = state.products.filter((item) => item.type === 'add_on' && item.billing?.kind !== 'recurring');
  const total = useMemo(() => displayedBasePrice + addons.filter((item) => selected.includes(item.sku)).reduce((sum, item) => sum + Number(item.price), 0), [displayedBasePrice, addons, selected]);
  const organization = state.session?.organizations?.[0];
  async function checkout(event) {
    event.preventDefault(); setBusy(true); setState((current) => ({ ...current, error: '' }));
    try {
      const result = await createCommerceCheckout({ organization: organization.public_id, website_request: websiteRequest, skus: [baseSku, ...selected], domain_choice: domainChoice, recurring_authorized: renewal, accept_terms: terms, terms_version: state.terms.version, marketing_opt_in: marketing });
      window.location.assign(result.checkout_url);
    } catch (error) { setState((current) => ({ ...current, error: error.message })); setBusy(false); }
  }
  if (state.loading) return <div className="v1-loading" role="status">Preparing secure checkout…</div>;
  if (!state.session) return <section className="purchase-shell"><span>Account-protected checkout</span><h1>Start with Web Basics</h1><p>Create or sign in to your customer account first. That keeps this purchase, your project, support, hosting, and future add-ons together.</p><Link className="btn btn--lime" to="/login?redirect=%2Fbuy">Sign in or create account</Link></section>;
  const renewalPrice = baseSku === 'FAM-BUSINESS-499' ? '$19.99' : '$9.99';
  return <form className="purchase-shell" onSubmit={checkout}>
    <span>Secure Commerce checkout</span><h1>{base?.title || 'Web Basics Bundle'}</h1><p>{base?.summary}</p>{websiteRequest && <p className="purchase-context">This purchase will activate the website request you selected in your portal.</p>}
    {requestRecord?.private_offer && <p className="purchase-context"><strong>Your private price: {money(displayedBasePrice)}</strong>{requestRecord.private_offer.reason ? ` — ${requestRecord.private_offer.reason}` : ''}<br /><small>Standard package price: {money(requestRecord.private_offer.list_amount_minor / 100)}. This offer is tied to your account and this website request.</small></p>}
    {state.error && <div className="alert alert--error" role="alert">{state.error}</div>}
    {!websiteRequest && <fieldset><legend>Choose the package that fits</legend>{websiteBundles.map((item) => <label key={item.sku}><input type="radio" name="bundle" value={item.sku} checked={baseSku === item.sku} onChange={(event) => setBaseSku(event.target.value)} required /> <b>{item.title}</b> — {money(item.price)}<small>{item.summary}</small></label>)}</fieldset>}
    <fieldset><legend>Domain setup</legend><label><input type="radio" name="domain" value="new_domain" checked={domainChoice === 'new_domain'} onChange={(e) => setDomainChoice(e.target.value)} required /> Register a new customer-owned domain for the included first year</label><label><input type="radio" name="domain" value="existing_domain" checked={domainChoice === 'existing_domain'} onChange={(e) => setDomainChoice(e.target.value)} /> Connect a domain I already own</label></fieldset>
    <fieldset><legend>Useful add-ons</legend>{requestRecord?.intake?.recommendation?.suggested_addon_skus?.length > 0 && <p className="purchase-context">Suggested from your answers and preselected for review. Every add-on is optional: uncheck anything you do not want.</p>}{addons.map((item) => <label key={item.sku}><input type="checkbox" checked={selected.includes(item.sku)} onChange={(e) => setSelected((current) => e.target.checked ? [...current, item.sku] : current.filter((sku) => sku !== item.sku))} /> <b>{item.title}</b> — {money(item.price)}<small>{item.summary}</small></label>)}</fieldset>
    <label className="purchase-consent"><input type="checkbox" checked={renewal} onChange={(e) => setRenewal(e.target.checked)} required /> I authorize the hosting included with this package to renew at {renewalPrice}/month after the included first year. I can cancel before renewal from my account.</label>
    <label className="purchase-consent"><input type="checkbox" checked={terms} onChange={(e) => setTerms(e.target.checked)} required /> I accept the recorded product scope, payment, cancellation, renewal, and domain terms.</label>
    <label className="purchase-consent"><input type="checkbox" checked={marketing} onChange={(e) => setMarketing(e.target.checked)} /> Send me useful articles and relevant offers. I can unsubscribe without affecting service messages.</label>
    <div className="purchase-total"><span>Due today</span><strong>{money(total)}</strong></div><button className="btn btn--lime" disabled={busy || !base}>{busy ? 'Opening secure payment…' : 'Continue to secure payment'}</button>
  </form>;
}
