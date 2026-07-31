import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { matchBySlug } from '../utils/content.js';
import { transformPackageNode } from '../lib/drupalAdapter.js';
import { applySeo } from '../components/SEO.jsx';
import { packageSeo } from '../seo.js';
import { Hero, Section, CTABanner, FadeUp, Stagger, Item } from '../components/v1/index.js';

/**
 * /packages/:slug — v1 detail page for one package_page node: hero with
 * price/timeline, "What's Included" checklist, "Best For", optional add-ons,
 * and CTA to /contact.
 */
export default function PackagePage() {
  const { slug } = useParams();
  const [state, setState] = useState({ plan: null, loading: true });

  useEffect(() => {
    let cancelled = false;
    setState({ plan: null, loading: true });
    getNodesRaw('package_page', { include: 'field_addons' }).then(({ data, included }) => {
      if (!cancelled) {
        const node = matchBySlug(data, slug);
        setState({ plan: transformPackageNode(node, included), loading: false });
      }
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  const plan = state.plan;
  useEffect(() => {
    if (plan) applySeo(packageSeo(plan, slug));
  }, [plan, slug]);

  if (state.loading) {
    return <div className="v1-loading" role="status">Loading package…</div>;
  }

  if (!plan) {
    return (
      <Section>
        <div className="v1-empty">
          <strong>We could not find that package.</strong>
          <br />
          It may have been renamed or is still being published.{' '}
          <Link to="/packages">Browse all packages</Link>.
        </div>
      </Section>
    );
  }

  const included = plan.whatsIncluded.length ? plan.whatsIncluded : plan.features;
  const cta = { label: plan.ctaText, href: plan.ctaHref };

  return (
    <article>
      <Hero
        eyebrow={plan.timeline ? `Package · ${plan.timeline}` : 'Package'}
        title={plan.title}
        lede={plan.subheadline || plan.bestFor}
        primaryCta={cta}
        secondaryCta={{ label: 'All packages', href: '/packages' }}
      >
        {plan.price && (
          <FadeUp delay={0.3}>
            <p className="v1-pricing-card__price" style={{ marginTop: '1.8rem' }}>
              {plan.price}
            </p>
          </FadeUp>
        )}
      </Hero>

      {included.length > 0 && (
        <Section eyebrow="Deliverables" title="What's Included">
          <FadeUp className="v1-panel">
            <ul className="v1-dot-list" style={{ marginTop: 0 }}>
              {included.map((feature) => (
                <li key={feature}>{feature}</li>
              ))}
            </ul>
          </FadeUp>
        </Section>
      )}

      {plan.bestFor && (
        <Section eyebrow="Fit" title="Best For">
          <FadeUp className="v1-panel v1-panel--soft">
            <p className="v1-card__text" style={{ margin: 0 }}>{plan.bestFor}</p>
          </FadeUp>
        </Section>
      )}

      {plan.addons.length > 0 && (
        <Section eyebrow="Add-ons" title="Extra support when the project needs more.">
          <Stagger className="v1-grid v1-grid--3">
            {plan.addons.map((addon) => (
              <Item key={addon.id} className="v1-card">
                <h3 className="v1-card__title">{addon.name}</h3>
                {addon.description && <p className="v1-card__text">{addon.description}</p>}
                {addon.price && <p className="v1-pricing-card__price" style={{ fontSize: '1.2rem' }}>{addon.price}</p>}
              </Item>
            ))}
          </Stagger>
        </Section>
      )}

      <CTABanner
        title="Ready to get started?"
        body="Final scope is confirmed after a short consultation — the price you see is the price you pay."
        primaryCta={cta}
        secondaryCta={{ label: 'Contact', href: '/contact' }}
      />
    </article>
  );
}
