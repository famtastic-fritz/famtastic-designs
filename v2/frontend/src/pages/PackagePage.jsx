import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { listValues, matchBySlug, textValue } from '../utils/content.js';

/**
 * /packages/:slug — detail page for one package_page node.
 * Hero with prominent price, timeline, "What's Included" checklist,
 * "Best For" description, and a CTA to /contact.
 */
export default function PackagePage() {
  const { slug } = useParams();
  const [state, setState] = useState({ node: null, loading: true });

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('package_page').then(({ data }) => {
      if (!cancelled) setState({ node: matchBySlug(data, slug), loading: false });
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (state.loading) {
    return <div className="loading" role="status">Loading package…</div>;
  }

  if (!state.node) {
    return (
      <div className="status">
        <p>
          <strong>We could not find that package.</strong>
          <br />
          It may have been renamed or is still being published.{' '}
          <Link to="/packages">Browse all packages</Link>.
        </p>
      </div>
    );
  }

  const attrs = state.node.attributes ?? {};
  const price = textValue(attrs.field_price);
  const timeline = textValue(attrs.field_timeline);
  const badge = textValue(attrs.field_badge);
  const features = listValues(attrs.field_features);
  const bestFor = textValue(attrs.field_best_for);
  const ctaText = textValue(attrs.field_cta_text) || 'Book a Call';
  const body = textValue(attrs.body);

  return (
    <article className="package-page">
      <section className="hero">
        <span className="hero__eyebrow">Package</span>
        <h1 className="hero__title">
          <span className="accent">{attrs.title ?? 'Package'}</span>
        </h1>
        {badge && badge.toLowerCase() !== 'none' && (
          <p>
            <span className="badge-pill">{badge}</span>
          </p>
        )}
        {price && <p className="package-page__price">{price}</p>}
        {timeline && <p className="hero__lede">{timeline}</p>}
        <p className="hero__actions">
          <Link className="btn btn--lime" to="/contact">
            {ctaText}
          </Link>
        </p>
      </section>

      {body && (
        <section
          className="feature-section node-view__body"
          dangerouslySetInnerHTML={{ __html: body }}
        />
      )}

      {features.length > 0 && (
        <section className="feature-section" aria-labelledby="included-heading">
          <h2 id="included-heading" className="feature-section__title">
            What's Included
          </h2>
          <ul className="check-list">
            {features.map((feature, i) => (
              <li key={i}>{feature}</li>
            ))}
          </ul>
        </section>
      )}

      {bestFor && (
        <section className="feature-section" aria-labelledby="bestfor-heading">
          <h2 id="bestfor-heading" className="feature-section__title">
            Best For
          </h2>
          <p className="feature-section__text">{bestFor}</p>
        </section>
      )}

      <section className="cta-banner">
        <h2 className="cta-banner__title">Ready to get started?</h2>
        <Link className="btn btn--lime" to="/contact">
          {ctaText}
        </Link>
      </section>
    </article>
  );
}
