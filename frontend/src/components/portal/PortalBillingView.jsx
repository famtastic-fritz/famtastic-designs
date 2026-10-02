import { Panel, title, money, date } from './PortalShared.jsx';

export default function PortalBillingView({ workspace, inbox, go }) {
  const orders = workspace?.orders || [];
  const requests = (workspace?.website_requests || []).filter((request) => !request.customer_archived);
  const invoices = [...(workspace?.invoices || []), ...requests.map((request) => request.invoice).filter(Boolean)]
    .filter((invoice, index, all) => all.findIndex((candidate) => candidate.public_id === invoice.public_id) === index);
  const isStaff = inbox?.is_staff === true;

  if (!workspace) {
    return <section className="portal-grid two portal-client-orders">
      <Panel eyebrow="Client work" title="Orders, proofs and conversations">
        <p>Website requests begin before a purchase. Open the request to see proof progress and the order to see recorded payment.</p>
        <div className="portal-form-actions">
          {isStaff && inbox.admin_orders_url === '/web/admin/commerce/orders' && <a href={inbox.admin_orders_url}>Open client orders ↗</a>}
          {isStaff && <a href="/web/admin/famtastic/metric/website-requests">Open website requests / proofs ↗</a>}
          <button type="button" className="secondary" onClick={() => go('messages')}>Open client messages</button>
        </div>
      </Panel>
    </section>;
  }

  return (
    <>
    {isStaff && <div className="portal-billing-staff-links"><strong>Client work</strong><a href="/web/admin/famtastic/metric/website-requests">Website requests / proofs ↗</a>{inbox.admin_orders_url === '/web/admin/commerce/orders' && <a href={inbox.admin_orders_url}>Client orders ↗</a>}<button type="button" onClick={() => go('messages')}>Client messages</button></div>}
    {requests.length > 0 && <Panel eyebrow="Before and after purchase" title="Website requests & proofs" className="portal-billing-requests">
      <p>Your request and proof progress stay visible while a purchase is being prepared.</p>
      <ul>{requests.map((request) => <li key={request.public_id}><div><strong>{request.project_name || request.business_name || 'Website request'}</strong><p>{request.proof_handoff?.label || 'Open the project for its current status.'}</p></div><a href={`/portal?tab=projects&request=${encodeURIComponent(request.public_id)}`}>Open project</a></li>)}</ul>
    </Panel>}
    {invoices.length > 0 && <section className="portal-invoice-list" aria-label="Invoices">
      {invoices.map((invoice) => {
        const request = requests.find((item) => item.public_id === invoice.website_request_public_id || item.invoice?.public_id === invoice.public_id);
        const requestPublicId = request?.public_id || invoice.website_request_public_id || '';
        const open = !['paid', 'void', 'refunded'].includes(invoice.status);
        const checkoutReady = Boolean(request?.direct_checkout_available || invoice.checkout_available);
        return (
          <Panel key={invoice.public_id} eyebrow={`Invoice ${invoice.invoice_number}`} title={open ? `${money(invoice.total_amount_minor, invoice.currency)} due` : title(invoice.status)} className="portal-invoice-card">
            <div className="portal-invoice-status"><strong>{title(invoice.status)}</strong><span>{invoice.terms?.one_time ? 'One-time project contribution' : 'Recorded invoice'}</span></div>
            <ul className="portal-invoice-lines">
              {(invoice.line_items || []).map((item) => <li key={item.code || item.label}><span>{item.label}</span><strong>{item.amount_minor === 0 ? 'Included' : money(item.amount_minor, invoice.currency)}</strong></li>)}
            </ul>
            <dl className="portal-invoice-totals">
              <div><dt>Package value</dt><dd>{money(invoice.list_amount_minor, invoice.currency)}</dd></div>
              <div><dt>FAMtastic Community Sponsorship Credit</dt><dd>−{money(invoice.credit_amount_minor, invoice.currency)}</dd></div>
              <div><dt>One-time contribution due</dt><dd><strong>{money(invoice.total_amount_minor, invoice.currency)}</strong></dd></div>
            </dl>
            <p>Kofi will host the finished platform on his own hosting. There is no recurring FAMtastic charge. Hosting, domain, mailbox, processor, shipping, and fulfillment costs remain owner-paid.</p>
            {invoice.status === 'paid' ? (
              <button type="button" onClick={() => go('projects')}>Continue owner-hosted handoff →</button>
            ) : checkoutReady ? (
              <a className="portal-invoice-cta" href={`/buy?request=${encodeURIComponent(requestPublicId)}&invoice=${encodeURIComponent(invoice.public_id)}`}>Review &amp; Pay {money(invoice.total_amount_minor, invoice.currency)}</a>
            ) : (
              <p className="portal-invoice-blocker" role="status">Review and accept the exact staging release in Projects before payment opens.</p>
            )}
          </Panel>
        );
      })}
    </section>}
    <section className="portal-grid two">
      {orders.length ? (
        orders.map((purchase) => (
          <Panel
            key={purchase.uuid || purchase.id || purchase.label}
            eyebrow="Purchase"
            title={money(purchase.amount, purchase.currency)}
          >
            <dl>
              <div>
                <dt>Package</dt>
                <dd>{title(purchase.package)}</dd>
              </div>
              <div>
                <dt>Payment</dt>
                <dd>{title(purchase.payment_status)}</dd>
              </div>
              <div>
                <dt>Date</dt>
                <dd>{date(purchase.created)}</dd>
              </div>
            </dl>
          </Panel>
        ))
      ) : (
        <Panel eyebrow="Purchases" title="No purchases yet">
          <p>{requests.length ? 'Your website request is saved above. A purchase will appear here when an order is recorded.' : 'Your orders, receipts, and renewal information will appear here.'}</p>
        </Panel>
      )}

      <Panel eyebrow="Payment Security" title="Secure Billing &amp; Terms">
        <p>
          Payment methods are processed securely through Stripe and Drupal Commerce. FAMtastic never
          stores raw credit card numbers on-premises.
        </p>
        <div
          style={{
            marginTop: '1rem',
            padding: '0.85rem',
            borderRadius: '10px',
            background: 'rgba(255,255,255,0.02)',
            border: '1px solid var(--p-line)',
            fontSize: '0.82rem',
            color: '#aab2aa',
          }}
        >
          <strong style={{ color: '#fff', display: 'block', marginBottom: '0.2rem' }}>
            {invoices.some((invoice) => invoice.scope_snapshot?.delivery_model === 'owner_hosted_private') ? 'Owner-hosted project terms' : 'Hosting Inclusions &amp; Renewal Policy'}
          </strong>
          {invoices.some((invoice) => invoice.scope_snapshot?.delivery_model === 'owner_hosted_private')
            ? 'This owner-hosted invoice has no recurring FAMtastic charge. Payment opens the access checklist and hosting audit; it does not change DNS, deploy the site, or activate live payments.'
            : 'Web bundles include 365 days of managed cloud hosting. Month-13 renewals ($9.99/mo for Web Basics or $19.99/mo for Business Website) are billed only upon verified customer recurring authorization.'}
        </div>
      </Panel>
    </section>
    </>
  );
}
