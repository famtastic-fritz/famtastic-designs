import { Panel, title, money, date } from './PortalShared.jsx';

export default function PortalBillingView({ workspace }) {
  const orders = workspace.orders || [];

  return (
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
          <p>Your orders, receipts, and renewal information will appear here.</p>
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
  );
}
