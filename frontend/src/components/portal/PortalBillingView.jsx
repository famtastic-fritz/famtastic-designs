import { Panel, title, money, date } from './PortalShared.jsx';

export default function PortalBillingView({ workspace, inbox, go }) {
  const orders = workspace?.orders || [];
  const requests = (workspace?.website_requests || []).filter((request) => !request.customer_archived);
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
            Hosting Inclusions &amp; Renewal Policy
          </strong>
          Web bundles include 365 days of managed cloud hosting. Month-13 renewals ($9.99/mo for Web
          Basics or $19.99/mo for Business Website) are billed only upon verified customer recurring
          authorization.
        </div>
      </Panel>
    </section>
    </>
  );
}
