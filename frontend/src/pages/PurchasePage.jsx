import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { createCommerceCheckout, customerSession, getCustomerCatalog, getCustomerWorkspace } from '../api/customer.js';

const WEBSITE_SKUS = ['FAM-FOOT-199', 'FAM-BUSINESS-499', 'FAM-CUSTOM-1999', 'FAM-GROWTH-3999', 'FAM-AI-6999', 'FAM-LANDING-1499'];

const BUNDLE_MAP = {
  'web-basics': 'FAM-FOOT-199',
  '199-quick-start': 'FAM-FOOT-199',
  '55-cents': 'FAM-FOOT-199',
  'business-website': 'FAM-BUSINESS-499',
  '499-site-upgrade': 'FAM-BUSINESS-499',
  'site-rebuild': 'FAM-BUSINESS-499',
  'custom-website': 'FAM-CUSTOM-1999',
  'custom-dev': 'FAM-CUSTOM-1999',
  'ecommerce': 'FAM-CUSTOM-1999',
  'landing-page': 'FAM-LANDING-1499',
  'business-growth': 'FAM-GROWTH-3999',
  'client-portal': 'FAM-GROWTH-3999',
  'premium-ai': 'FAM-AI-6999',
  'ai-chatbot': 'FAM-AI-6999',
};

const money = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));

export default function PurchasePage() {
  const [searchParams] = useSearchParams();
  const websiteRequest = searchParams.get('request') || '';
  const bundleParam = searchParams.get('bundle') || '';
  const [state, setState] = useState({ loading: true, session: null, workspace: null, products: [], terms: null, error: '' });
  const [baseSku, setBaseSku] = useState('');
  const [selected, setSelected] = useState([]);
  const [domainChoice, setDomainChoice] = useState('new_domain');
  const [renewal, setRenewal] = useState(false);
  const [terms, setTerms] = useState(false);
  const [marketing, setMarketing] = useState(false);
  const [grantCode, setGrantCode] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    Promise.all([customerSession().catch(() => null), getCustomerCatalog(), getCustomerWorkspace().catch(() => null)])
      .then(([session, catalog, workspace]) => {
        const request = workspace?.website_requests?.find((item) => item.public_id === websiteRequest);
        const resolvedSkuFromBundle = BUNDLE_MAP[bundleParam.toLowerCase()] || (catalog.products?.some((p) => p.sku === bundleParam) ? bundleParam : '');
        const targetSku = request?.recommended_sku || resolvedSkuFromBundle || 'FAM-FOOT-199';
        setBaseSku(targetSku);
        setState({ loading: false, session, workspace, products: catalog.products || [], terms: catalog.terms, error: '' });
      })
      .catch((error) => setState({ loading: false, session: null, products: [], terms: null, error: error.message }));
  }, [websiteRequest, bundleParam]);

  const base = state.products.find((item) => item.sku === baseSku) || state.products.find((item) => item.sku === 'FAM-FOOT-199');
  const requestRecord = state.workspace?.website_requests?.find((item) => item.public_id === websiteRequest);
  const displayedBasePrice = requestRecord?.private_offer ? requestRecord.private_offer.offered_amount_minor / 100 : Number(base?.price || 199);
  const websiteBundles = state.products.filter((item) => WEBSITE_SKUS.includes(item.sku) || item.sku === 'FAM-FOOT-199');
  const addons = state.products.filter((item) => item.type === 'add_on' && item.billing?.kind !== 'recurring');
  const total = useMemo(() => displayedBasePrice + addons.filter((item) => selected.includes(item.sku)).reduce((sum, item) => sum + Number(item.price), 0), [displayedBasePrice, addons, selected]);
  const organization = state.session?.organizations?.[0];

  async function checkout(event) {
    event.preventDefault();
    setBusy(true);
    setState((current) => ({ ...current, error: '' }));
    try {
      const result = await createCommerceCheckout({
        organization: organization.public_id,
        website_request: websiteRequest,
        skus: [baseSku || 'FAM-FOOT-199', ...selected],
        domain_choice: domainChoice,
        recurring_authorized: renewal,
        accept_terms: terms,
        terms_version: state.terms?.version || '1.0',
        marketing_opt_in: marketing,
        grant_code: grantCode.trim(),
      });
      window.location.assign(result.checkout_url);
    } catch (error) {
      setState((current) => ({ ...current, error: error.message }));
      setBusy(false);
    }
  }

  if (state.loading) return <div className="v1-loading" role="status">Preparing secure checkout…</div>;

  const currentRedirect = encodeURIComponent(`/buy${window.location.search || (bundleParam ? `?bundle=${bundleParam}` : '')}`);

  if (!state.session) {
    return (
      <section className="purchase-shell">
        <span>Account-protected checkout</span>
        <h1>{base?.title || 'Start Your Project'}</h1>
        <p>Create or sign in to your customer account to lock in your fixed price, hosting, and project workspace.</p>
        <Link className="btn btn--lime" to={`/login?redirect=${currentRedirect}`}>
          Sign in or create account →
        </Link>
      </section>
    );
  }

  const renewalSku = (state.products || []).find((item) => item.sku === (base?.billing?.renewal_sku || ''));
  const renewalPrice = renewalSku ? money(renewalSku.price) : '$9.99';

  return (
    <form className="purchase-shell" onSubmit={checkout}>
      <span>Secure Commerce checkout</span>
      <h1>{base?.title || 'Web Basics Bundle — $199'}</h1>
      <p>{base?.summary || 'One focused high-conversion business page with first-year hosting and domain included.'}</p>
      {websiteRequest && <p className="purchase-context">This purchase will activate the website request you selected in your portal.</p>}
      {requestRecord?.private_offer && (
        <p className="purchase-context">
          <strong>Your private price: {money(displayedBasePrice)}</strong>
          {requestRecord.private_offer.reason ? ` — ${requestRecord.private_offer.reason}` : ''}
          <br />
          <small>Standard package price: {money(requestRecord.private_offer.list_amount_minor / 100)}. This offer is tied to your account.</small>
        </p>
      )}
      {state.error && <div className="alert alert--error" role="alert">{state.error}</div>}

      <fieldset>
        <legend>Select Package</legend>
        {(websiteBundles.length > 0 ? websiteBundles : [{ sku: 'FAM-FOOT-199', title: 'Web Basics Bundle', price: 199, summary: '1-page site with hosting and domain' }]).map((item) => (
          <label key={item.sku}>
            <input
              type="radio"
              name="bundle"
              value={item.sku}
              checked={baseSku === item.sku}
              onChange={(event) => setBaseSku(event.target.value)}
              required
            />{' '}
            <b>{item.title}</b> — {money(item.price)}
            <small>{item.summary}</small>
          </label>
        ))}
      </fieldset>

      <fieldset>
        <legend>Domain setup</legend>
        <label>
          <input
            type="radio"
            name="domain"
            value="new_domain"
            checked={domainChoice === 'new_domain'}
            onChange={(e) => setDomainChoice(e.target.value)}
            required
          />{' '}
          Register a new customer-owned domain for the included first year
        </label>
        <label>
          <input
            type="radio"
            name="domain"
            value="existing_domain"
            checked={domainChoice === 'existing_domain'}
            onChange={(e) => setDomainChoice(e.target.value)}
          />{' '}
          Connect a domain I already own
        </label>
      </fieldset>

      {addons.length > 0 && (
        <fieldset>
          <legend>Useful add-ons</legend>
          {addons.map((item) => (
            <label key={item.sku}>
              <input
                type="checkbox"
                checked={selected.includes(item.sku)}
                onChange={(e) =>
                  setSelected((current) =>
                    e.target.checked ? [...current, item.sku] : current.filter((sku) => sku !== item.sku)
                  )
                }
              />{' '}
              <b>{item.title}</b> — {money(item.price)}
              <small>{item.summary}</small>
            </label>
          ))}
        </fieldset>
      )}

      <fieldset>
        <legend>Private grant or credit code</legend>
        <label>
          Grant code
          <input
            value={grantCode}
            onChange={(event) => setGrantCode(event.target.value.toUpperCase())}
            autoComplete="off"
            placeholder="FAM-GRANT-…"
          />
          <small>Codes are checked against this account. A fully sponsored order completes without opening Stripe.</small>
        </label>
      </fieldset>

      <label className="purchase-consent">
        <input type="checkbox" checked={renewal} onChange={(e) => setRenewal(e.target.checked)} required /> I authorize the hosting included with this package to renew at {renewalPrice}{renewalSku ? '/month' : '/month'} rate after the included first year. I can cancel anytime before renewal from my account.
      </label>
      <label className="purchase-consent">
        <input type="checkbox" checked={terms} onChange={(e) => setTerms(e.target.checked)} required /> I accept the recorded product scope, payment, cancellation, renewal, and domain terms.
      </label>
      <label className="purchase-consent">
        <input type="checkbox" checked={marketing} onChange={(e) => setMarketing(e.target.checked)} /> Send me useful system updates and relevant offers.
      </label>

      <div className="purchase-total">
        <span>{grantCode ? 'Before verified grant' : 'Due today'}</span>
        <strong>{money(total)}</strong>
      </div>
      <button className="btn btn--lime" disabled={busy || !base}>
        {busy ? 'Finalizing…' : grantCode ? 'Apply grant and continue' : 'Continue to secure payment'}
      </button>
    </form>
  );
}
