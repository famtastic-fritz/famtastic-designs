import { Link } from 'react-router';
import { Empty, title, date, serviceMeta } from './PortalShared.jsx';

export default function PortalServicesView({ workspace, catalog, go, compact = false }) {
  const ownedTypes = new Set(
    (workspace.entitlements || [])
      .filter((item) => item.status === 'active')
      .map((item) => item.entitlement_type)
  );

  const promotedSkus = [
    'FAM-AI-AGENT',
    'FAM-LEAD-AUTOMATION',
    'FAM-ANALYTICS',
    'FAM-LOCAL-SEO',
    'FAM-MAINTENANCE',
    'FAM-SCHEDULING',
    'FAM-BRAND',
    'FAM-COPY',
  ];

  const promoted = (catalog?.products || [])
    .filter((item) => promotedSkus.includes(item.sku))
    .filter((item) => !(item.entitlements || []).some((type) => ownedTypes.has(type)))
    .slice(0, compact ? 4 : 8);

  return (
    <section
      className={`portal-service-hub${compact ? ' compact' : ''}`}
      aria-labelledby={compact ? 'portal-services-preview-title' : 'portal-services-title'}
    >
      <header>
        <div>
          <span>Service Command Center</span>
          <h2 id={compact ? 'portal-services-preview-title' : 'portal-services-title'}>
            {compact
              ? 'Manage what you own. Discover what helps next.'
              : 'Your services and growth systems'}
          </h2>
        </div>
        <p>
          Active services, work, support, billing, and relevant next steps stay connected to this account.
        </p>
      </header>

      <div className="portal-service-columns">
        <div>
          <h3>Your services</h3>
          {workspace.entitlements?.length ? (
            <ul>
              {workspace.entitlements.map((service) => {
                const meta = serviceMeta(service.entitlement_type);
                return (
                  <li
                    key={service.public_id || service.id || service.entitlement_type}
                    style={{ alignItems: 'start', padding: '0.9rem' }}
                  >
                    <i aria-hidden="true" style={{ marginTop: '0.4rem' }} />
                    <div>
                      <strong>{meta.title}</strong>
                      <p
                        style={{
                          margin: '0.2rem 0 0.3rem',
                          fontSize: '0.8rem',
                          color: '#b2bcb2',
                          lineHeight: '1.4',
                        }}
                      >
                        {meta.desc}
                      </p>
                      <small style={{ color: '#7cfc00', fontWeight: '600' }}>
                        {title(service.status)}
                        {service.included_until
                          ? ` · included through ${date(service.included_until)}`
                          : ''}
                      </small>
                    </div>
                    <button
                      onClick={() => go(meta.target)}
                      style={{ whiteSpace: 'nowrap', alignSelf: 'center' }}
                    >
                      {meta.btn} →
                    </button>
                  </li>
                );
              })}
            </ul>
          ) : (
            <Empty>
              No active services yet. Start with a website brief or ask us what would remove the
              biggest bottleneck.
            </Empty>
          )}
        </div>

        <div>
          <h3>Recommended studio modules</h3>
          <div className="portal-market-grid">
            {promoted.map((item) => (
              <article key={item.sku}>
                <span>
                  {item.billing?.kind === 'recurring'
                    ? `${item.billing.interval}ly`
                    : 'One-time setup'}
                </span>
                <h4>{item.title.replace(/\s+[—-].*$/, '')}</h4>
                <p>{item.summary}</p>
                <footer>
                  <strong>
                    ${item.price}
                    {item.billing?.kind === 'recurring' ? '/mo' : ''}
                  </strong>
                  {compact ? (
                    <button type="button" onClick={() => go('services')}>
                      Learn more →
                    </button>
                  ) : (
                    <Link
                      to={`/buy?sku=${encodeURIComponent(item.sku)}`}
                      style={{
                        padding: '0.45rem 0.85rem',
                        fontSize: '0.82rem',
                        borderRadius: '8px',
                        background: '#7cfc00',
                        color: '#000',
                        fontWeight: '700',
                        textDecoration: 'none',
                        display: 'inline-block',
                      }}
                    >
                      Order module →
                    </Link>
                  )}
                </footer>
              </article>
            ))}
          </div>
        </div>
      </div>

      {!compact && (
        <section
          className="portal-intake-share-box"
          style={{
            marginTop: '1.75rem',
            padding: '1.25rem',
            borderRadius: '14px',
            border: '1px dashed rgba(124,252,0,0.35)',
            background: 'rgba(124,252,0,0.03)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            flexWrap: 'wrap',
            gap: '1rem',
          }}
        >
          <div>
            <span
              style={{
                color: '#7cfc00',
                fontSize: '0.75rem',
                fontWeight: '800',
                textTransform: 'uppercase',
                letterSpacing: '0.1em',
              }}
            >
              ⚡ Direct Project Intakes
            </span>
            <h4 style={{ margin: '0.2rem 0', fontSize: '1.05rem', color: '#fff' }}>
              Need to share a specialized intake form with a partner or team?
            </h4>
            <p style={{ margin: 0, color: '#9da79d', fontSize: '0.84rem' }}>
              Share tailored intake forms for Hosting Setup, AI Chatbots, Custom Portals, or Ongoing Site Care.
            </p>
          </div>
          <Link
            to="/intake"
            style={{
              padding: '0.65rem 1.25rem',
              borderRadius: '9px',
              background: '#7cfc00',
              color: '#000',
              fontWeight: '800',
              textDecoration: 'none',
              fontSize: '0.85rem',
            }}
          >
            Open Intake Hub →
          </Link>
        </section>
      )}

      {compact && (
        <button className="portal-services-all" onClick={() => go('services')}>
          Open all services
        </button>
      )}
    </section>
  );
}
