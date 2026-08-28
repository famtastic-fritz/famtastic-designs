import { Panel, Empty } from './PortalShared.jsx';

export default function PortalAnalyticsView({ workspace, go }) {
  const isEntitled = Boolean(
    workspace.analytics?.entitled ||
      (workspace.entitlements || []).some(
        (e) =>
          ['customer_analytics', 'analytics_connection'].includes(e.entitlement_type) &&
          e.status === 'active'
      )
  );

  return (
    <section className="portal-grid two">
      <Panel
        eyebrow="Growth Telemetry"
        title="Business Results &amp; Traffic"
        className={isEntitled ? 'lime' : ''}
      >
        {isEntitled ? (
          <>
            <p>
              Verified search visibility, conversion signals, and monthly performance digest connected to
              your live property.
            </p>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(3, 1fr)',
                gap: '0.75rem',
                margin: '1.25rem 0',
              }}
            >
              <div
                style={{
                  padding: '0.85rem',
                  borderRadius: '10px',
                  background: 'rgba(0,0,0,0.4)',
                  border: '1px solid var(--p-line)',
                }}
              >
                <small style={{ color: '#8e998e', display: 'block', fontSize: '0.72rem' }}>
                  SEARCH VISIBILITY
                </small>
                <strong style={{ fontSize: '1.3rem', color: '#7cfc00' }}>Active</strong>
                <span style={{ fontSize: '0.72rem', color: '#aab2aa', display: 'block' }}>
                  Indexed in Google
                </span>
              </div>
              <div
                style={{
                  padding: '0.85rem',
                  borderRadius: '10px',
                  background: 'rgba(0,0,0,0.4)',
                  border: '1px solid var(--p-line)',
                }}
              >
                <small style={{ color: '#8e998e', display: 'block', fontSize: '0.72rem' }}>
                  CONVERSIONS
                </small>
                <strong style={{ fontSize: '1.3rem', color: '#fff' }}>Verified</strong>
                <span style={{ fontSize: '0.72rem', color: '#aab2aa', display: 'block' }}>
                  GA4 Telemetry
                </span>
              </div>
              <div
                style={{
                  padding: '0.85rem',
                  borderRadius: '10px',
                  background: 'rgba(0,0,0,0.4)',
                  border: '1px solid var(--p-line)',
                }}
              >
                <small style={{ color: '#8e998e', display: 'block', fontSize: '0.72rem' }}>
                  UPTIME &amp; SPEED
                </small>
                <strong style={{ fontSize: '1.3rem', color: '#7cfc00' }}>99.9%</strong>
                <span style={{ fontSize: '0.72rem', color: '#aab2aa', display: 'block' }}>
                  NVMe Cloud Edge
                </span>
              </div>
            </div>
            <p style={{ fontSize: '0.88rem', color: '#c2ccc2' }}>
              Your monthly observations report is generated on the 1st of each month with practical
              recommendations to expand revenue.
            </p>
          </>
        ) : (
          <>
            <p>
              Connect Google Analytics 4, Google Search Console, and local citation tracking to measure
              real customer inquiries.
            </p>
            <div
              style={{
                margin: '1.25rem 0',
                padding: '1rem',
                borderRadius: '12px',
                border: '1px dashed rgba(124,252,0,0.3)',
                background: 'rgba(124,252,0,0.03)',
              }}
            >
              <strong style={{ display: 'block', color: '#7cfc00', fontSize: '0.9rem' }}>
                📊 Monthly Growth Analytics Module
              </strong>
              <p style={{ margin: '0.3rem 0 0.8rem', fontSize: '0.82rem', color: '#b2bcb2' }}>
                Unlock conversion tracking, monthly traffic audits, search keyword indexing reports, and
                revenue growth observations.
              </p>
              <button
                type="button"
                onClick={() => go('services')}
                style={{ minHeight: '38px', padding: '0.5rem 1rem' }}
              >
                Explore Growth Analytics →
              </button>
            </div>
          </>
        )}
      </Panel>

      <Panel
        eyebrow="Actionable Insights"
        title="What Drives Real Results"
      >
        <p>
          FAMtastic focuses on outcomes instead of vanity metrics. Here are the core pillars tracked for
          your digital systems:
        </p>

        <ul style={{ marginTop: '1rem' }}>
          <li>
            <div>
              <strong style={{ color: '#fff' }}>1. Lead Capture Velocity</strong>
              <p style={{ margin: '0.2rem 0', fontSize: '0.8rem', color: '#aab2aa' }}>
                How quickly prospective customers can call, book, or submit inquiries.
              </p>
            </div>
          </li>
          <li>
            <div>
              <strong style={{ color: '#fff' }}>2. Search Intent Alignment</strong>
              <p style={{ margin: '0.2rem 0', fontSize: '0.8rem', color: '#aab2aa' }}>
                Ensuring your pages directly answer what high-intent searchers ask.
              </p>
            </div>
          </li>
          <li>
            <div>
              <strong style={{ color: '#fff' }}>3. Conversion Friction Audit</strong>
              <p style={{ margin: '0.2rem 0', fontSize: '0.8rem', color: '#aab2aa' }}>
                Removing form drop-offs, slow load barriers, and mobile viewport obstacles.
              </p>
            </div>
          </li>
        </ul>
      </Panel>
    </section>
  );
}
