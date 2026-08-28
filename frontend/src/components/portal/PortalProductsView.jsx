import { Panel, date, money, title, serviceMeta, Empty } from './PortalShared.jsx';
import PortalServicesView from './PortalServicesView.jsx';

export default function PortalProductsView({ workspace, go }) {
  const activeRequest = workspace.website_requests?.[0] || null;
  const project = workspace.projects?.[0] || null;
  const hostingEntitlement =
    workspace.entitlements?.find((e) =>
      ['hosting', 'basic_hosting', 'fast_ssd_hosting', 'hosting_included_year', 'hosting_business_included_year'].includes(
        e.entitlement_type
      )
    ) || workspace.orders?.[0];

  const chosenDomain =
    activeRequest?.existing_domain ||
    activeRequest?.intake?.desired_domains ||
    activeRequest?.intake?.chosen_domain ||
    (activeRequest?.domain_choice === 'existing_domain' ? activeRequest.existing_domain : '') ||
    '';

  return (
    <section className="portal-products-hub" aria-label="My Products and Infrastructure">
      <header style={{ marginBottom: '1.5rem' }}>
        <span
          style={{
            color: 'var(--p-lime)',
            fontSize: '0.75rem',
            fontWeight: '800',
            textTransform: 'uppercase',
            letterSpacing: '0.12em',
          }}
        >
          Active Infrastructure &amp; Entitlements
        </span>
        <h2 style={{ margin: '0.35rem 0', fontSize: 'clamp(1.8rem, 4vw, 2.4rem)' }}>My Products</h2>
        <p style={{ color: '#aeb8ae', margin: 0, fontSize: '0.92rem' }}>
          Inspect your active cloud hosting instance, custom domain DNS routing, SSL security, and linked workspace systems.
        </p>
      </header>

      <div className="portal-products-grid">
        {/* Product 1: Managed Cloud Hosting */}
        <article className="portal-product-card highlight">
          <div>
            <div className="portal-product-card__header">
              <div>
                <span className="portal-product-badge">✓ Active &amp; Provisioned</span>
                <h3>Fast SSD Cloud Hosting</h3>
              </div>
              <span style={{ fontSize: '1.6rem' }}>⚡</span>
            </div>
            <p style={{ color: '#aab4aa', fontSize: '0.86rem', margin: '0.4rem 0 1rem', lineHeight: '1.5' }}>
              Dedicated NVMe SSD server instance with automated daily snapshots, global HTTP/3 delivery, and auto-renewing SSL certificate.
            </p>
            <div className="portal-product-specs">
              <div>
                <dt>Server Status</dt>
                <dd style={{ color: 'var(--p-lime)' }}>● Online (99.9% Uptime)</dd>
              </div>
              <div>
                <dt>Dedicated IP</dt>
                <dd>
                  <code style={{ color: 'var(--p-lime)' }}>198.71.232.3</code>
                </dd>
              </div>
              <div>
                <dt>SSL Security</dt>
                <dd>TLS 1.3 / 256-bit Encrypted</dd>
              </div>
              <div>
                <dt>Included Term</dt>
                <dd>
                  1-Year ({hostingEntitlement?.included_until ? date(hostingEntitlement.included_until) : 'Active'})
                </dd>
              </div>
              <div>
                <dt>Data Center</dt>
                <dd>GoDaddy Tier 4 (US Central)</dd>
              </div>
            </div>
          </div>
          <div style={{ display: 'flex', gap: '0.65rem', flexWrap: 'wrap' }}>
            <button type="button" onClick={() => go('projects')}>
              Open Project Setup →
            </button>
            <button type="button" className="secondary" onClick={() => go('support')}>
              Server Support
            </button>
          </div>
        </article>

        {/* Product 2: Custom Business Domain */}
        <article className="portal-product-card">
          <div>
            <div className="portal-product-card__header">
              <div>
                <span className={`portal-product-badge ${chosenDomain ? '' : 'pending'}`}>
                  {chosenDomain ? '✓ Domain Configured' : '⚙ Setup Required'}
                </span>
                <h3>Custom Business Domain</h3>
              </div>
              <span style={{ fontSize: '1.6rem' }}>🌐</span>
            </div>
            <p style={{ color: '#aab4aa', fontSize: '0.86rem', margin: '0.4rem 0 0.8rem', lineHeight: '1.5' }}>
              {chosenDomain
                ? `Custom domain routed to your cloud server: ${chosenDomain}`
                : 'Connect your existing domain name or claim your included domain (.com, .org, .net).'}
            </p>
            <div className="portal-product-specs">
              <div>
                <dt>Target Domain</dt>
                <dd>
                  <strong style={{ color: chosenDomain ? 'var(--p-lime)' : '#ffc107' }}>
                    {chosenDomain || 'Pending Selection'}
                  </strong>
                </dd>
              </div>
              <div>
                <dt>DNS A-Record (@)</dt>
                <dd>
                  <code style={{ color: 'var(--p-lime)' }}>198.71.232.3</code>
                </dd>
              </div>
              <div>
                <dt>CNAME (www)</dt>
                <dd>
                  <code>@ (famtasticdesigns.com)</code>
                </dd>
              </div>
              <div>
                <dt>Nameservers</dt>
                <dd>ns01.domaincontrol.com</dd>
              </div>
            </div>
          </div>
          <div style={{ display: 'flex', gap: '0.65rem', flexWrap: 'wrap' }}>
            <button type="button" onClick={() => go('projects')}>
              {chosenDomain ? 'Manage in Project Setup →' : 'Configure Domain Now →'}
            </button>
          </div>
        </article>

        {/* Product 3: Client Command Center */}
        <article className="portal-product-card">
          <div>
            <div className="portal-product-card__header">
              <div>
                <span className="portal-product-badge">✓ Active Workspace</span>
                <h3>Client Project Command Center</h3>
              </div>
              <span style={{ fontSize: '1.6rem' }}>🎯</span>
            </div>
            <p style={{ color: '#aab4aa', fontSize: '0.86rem', margin: '0.4rem 0 1rem', lineHeight: '1.5' }}>
              Private customer hub for visual concept review, Build DNA inspection, file asset uploads, and direct team messaging.
            </p>
            <div className="portal-product-specs">
              <div>
                <dt>Organization</dt>
                <dd>{workspace.organization?.name || 'Customer Account'}</dd>
              </div>
              <div>
                <dt>Site Studio Bridge</dt>
                <dd style={{ color: 'var(--p-lime)' }}>Ready for Build</dd>
              </div>
              <div>
                <dt>Revision Quota</dt>
                <dd>{project?.revision_limit || 1} Full Round Included</dd>
              </div>
            </div>
          </div>
          <div style={{ display: 'flex', gap: '0.65rem', flexWrap: 'wrap' }}>
            <button type="button" onClick={() => go('projects')}>
              Open Project Hub →
            </button>
          </div>
        </article>
      </div>

      <div style={{ marginTop: '2rem' }}>
        <h3 style={{ margin: '0 0 1rem', fontSize: '1.3rem' }}>All Owned Entitlements</h3>
        {workspace.entitlements?.length ? (
          <ul style={{ listStyle: 'none', padding: 0, display: 'grid', gap: '0.75rem' }}>
            {workspace.entitlements.map((service) => {
              const meta = serviceMeta(service.entitlement_type);
              return (
                <li
                  key={service.public_id}
                  style={{
                    padding: '1rem',
                    borderRadius: '12px',
                    border: '1px solid var(--p-line)',
                    background: 'rgba(16,19,16,0.85)',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: '1rem',
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.85rem' }}>
                    <span style={{ fontSize: '1.4rem' }}>{meta.icon || '📦'}</span>
                    <div>
                      <strong style={{ color: '#fff', fontSize: '0.95rem' }}>{meta.title}</strong>
                      <p style={{ margin: '0.2rem 0 0', color: '#8e998e', fontSize: '0.82rem' }}>{meta.desc}</p>
                    </div>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                    <small style={{ color: 'var(--p-lime)', fontWeight: '700', textTransform: 'uppercase' }}>
                      {title(service.status)}
                    </small>
                    <button type="button" onClick={() => go(meta.target)}>
                      {meta.btn} →
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>
        ) : (
          <Empty>No active service entitlements found.</Empty>
        )}
      </div>
    </section>
  );
}
