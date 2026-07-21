import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { listValues, nodeSlug, textValue } from '../utils/content.js';

/**
 * /packages — pricing hub listing every package_page as a pricing card.
 * The $199 entry-point package gets a subtle lime border glow.
 */
export default function PackagesHubPage() {
  const [packages, setPackages] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('package_page').then(({ data }) => {
      if (!cancelled) setPackages(data);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <section className="hero">
        <span className="hero__eyebrow">Packages</span>
        <h1 className="hero__title">
          Simple, honest <span className="accent">pricing</span>
        </h1>
        <p className="hero__lede">
          Fixed-scope packages engineered to ship — pick a starting point and we handle the rest.
        </p>
      </section>

      {packages === null && <div className="loading" role="status">Loading packages…</div>}

      {packages !== null && packages.length === 0 && (
        <div className="status">
          <p>
            <strong>Packages are being finalized.</strong>
            <br />
            Pricing details are being published right now — meanwhile,{' '}
            <Link to="/contact">book a call</Link> for a same-day quote.
          </p>
        </div>
      )}

      {packages !== null && packages.length > 0 && (
        <ul className="node-list pricing-grid">
          {packages.map((node) => {
            const attrs = node.attributes ?? {};
            const price = textValue(attrs.field_price);
            const timeline = textValue(attrs.field_timeline);
            const badge = textValue(attrs.field_badge);
            const features = listValues(attrs.field_features);
            const featured = price.includes('199');
            return (
              <li key={node.id}>
                <Link
                  to={`/packages/${nodeSlug(node)}`}
                  className={`node-card pricing-card${featured ? ' pricing-card--featured' : ''}`}
                >
                  {badge && badge.toLowerCase() !== 'none' && (
                    <span className="badge-pill">{badge}</span>
                  )}
                  <h3 className="node-card__title">{attrs.title ?? 'Package'}</h3>
                  {price && <p className="pricing-card__price">{price}</p>}
                  {timeline && <p className="pricing-card__timeline">{timeline}</p>}
                  {features.length > 0 && (
                    <ul className="check-list check-list--compact">
                      {features.slice(0, 6).map((feature, i) => (
                        <li key={i}>{feature}</li>
                      ))}
                    </ul>
                  )}
                  <span className="node-card__cta">View Package →</span>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </>
  );
}
