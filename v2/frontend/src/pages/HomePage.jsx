import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodesRaw, resolveIncluded } from '../api/drupal.js';
import { linkHref, nodeSlug, paraField, textValue } from '../utils/content.js';

/**
 * / — marketing homepage.
 *
 * Pulls the `homepage` node (hero, stats, why, process, final CTA) plus the
 * service_page teaser grid from JSON:API. If the homepage node is missing or
 * unreachable, the HARDCODED FALLBACK below renders the same "Agentic AI
 * Business Solutions Engineering Studio" messaging so the landing page never
 * ships empty.
 */
const FALLBACK = {
  headline: 'Agentic AI Business Solutions Engineering Studio',
  subheadline:
    'We design and build intelligent systems that automate the busywork, answer your customers, and grow your revenue — engineered for your specific business, not a template.',
  aboutTitle: 'What Is an Agentic AI Solutions Studio?',
  aboutBody:
    'An agentic AI solutions studio engineers software systems that act on your behalf: answering leads in seconds, booking jobs while you sleep, and turning scattered tools into one intelligent pipeline. We scope, build, verify, and manage every system end to end.',
  finalCtaTitle: 'Start Your System — $199',
  finalCtaBody:
    'Every engagement starts with a fixed-price, $199 discovery build: a working proof of your system, verified before you commit to the full build.',
};

export default function HomePage() {
  const [home, setHome] = useState(null); // { node, included } | null
  const [services, setServices] = useState([]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      // field_stats_items may not exist on the backend yet; degrade the
      // include list until the request succeeds so the real hero still
      // renders while the backend is being seeded.
      const includeOptions = [
        'field_stats_items,field_process_steps,field_why_items',
        'field_process_steps,field_why_items',
        '',
      ];
      for (const include of includeOptions) {
        const { data, included, error } = await getNodesRaw('homepage', {
          include,
          limit: 1,
        });
        if (error) continue;
        if (!cancelled && data.length > 0) {
          setHome({ node: data[0], included });
        }
        break;
      }
    })();
    getNodesRaw('service_page', { limit: 6 })
      .then(({ data }) => {
        if (!cancelled) setServices(data.slice(0, 6));
      })
      .catch(() => {
        /* teaser grid stays empty */
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const attrs = home?.node?.attributes ?? {};

  const headline = textValue(attrs.field_hero_headline) || FALLBACK.headline;
  const subheadline = textValue(attrs.field_hero_subheadline) || FALLBACK.subheadline;
  const finalCtaTitle = textValue(attrs.field_final_cta_title) || FALLBACK.finalCtaTitle;
  const finalCtaBody = textValue(attrs.field_final_cta_body) || FALLBACK.finalCtaBody;
  const primaryCtaText = textValue(attrs.field_cta_primary_text);
  const primaryCtaHref = linkHref(attrs.field_cta_primary_link, '/contact');

  // Stats bar: metric paragraphs when present, otherwise the proof strip.
  const statItems = home
    ? resolveIncluded(home.node, home.included, 'field_stats_items')
        .map((item) => ({
          id: item.id,
          value: paraField(item, ['field_metric_value', 'field_value']),
          label: paraField(item, ['field_metric_label', 'field_label']),
        }))
        .filter((item) => item.value || item.label)
    : [];

  return (
    <>
      {/* HERO */}
      <section className="hero">
        <span className="hero__eyebrow">FAMtastic Designs</span>
        <h1 className="hero__title">
          <span className="accent">{headline}</span>
        </h1>
        <p className="hero__lede">{subheadline}</p>
        <p className="hero__actions">
          <Link className="btn btn--lime" to={primaryCtaHref}>
            {primaryCtaText || FALLBACK.finalCtaTitle}
          </Link>
        </p>
      </section>

      {/* SOCIAL PROOF BAR */}
      <section className="proof-bar" aria-label="Social proof">
        <span>2024 BEYA Leader</span>
        <span aria-hidden="true">|</span>
        <span>22+ Years</span>
        <span aria-hidden="true">|</span>
        <span>100+ Systems</span>
      </section>

      {/* WHAT IS AN AGENTIC AI SOLUTIONS STUDIO? */}
      <section className="feature-section" aria-labelledby="about-heading">
        <h2 id="about-heading" className="feature-section__title">
          {FALLBACK.aboutTitle}
        </h2>
        <p className="feature-section__text">{FALLBACK.aboutBody}</p>
      </section>

      {/* SERVICES TEASER */}
      <section aria-labelledby="services-heading">
        <div className="section-heading">
          <h2 id="services-heading">Our Solutions</h2>
          <Link className="hint" to="/services">
            View all →
          </Link>
        </div>
        {services.length > 0 ? (
          <ul className="node-list">
            {services.map((node) => (
              <li key={node.id}>
                <Link to={`/services/${nodeSlug(node)}`} className="node-card">
                  <span className="node-card__type">Service</span>
                  <h3 className="node-card__title">
                    {node.attributes?.title ?? 'Service'}
                  </h3>
                  <p className="node-card__summary">
                    {textValue(node.attributes?.field_hero_subheadline) ||
                      'Learn how this system works for your business.'}
                  </p>
                  <span className="node-card__cta">Learn More →</span>
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <div className="status">
            <p>
              Our service lineup is being published right now —{' '}
              <Link to="/contact">book a call</Link> for a walkthrough.
            </p>
          </div>
        )}
      </section>

      {/* STATS BAR (from field_stats_items when present) */}
      {statItems.length > 0 && (
        <section className="stats-bar" aria-label="Key numbers">
          {statItems.map((item) => (
            <div key={item.id} className="stats-bar__item">
              <span className="stats-bar__value">{item.value}</span>
              <span className="stats-bar__label">{item.label}</span>
            </div>
          ))}
        </section>
      )}

      {/* FINAL CTA */}
      <section className="cta-banner">
        <h2 className="cta-banner__title">{finalCtaTitle}</h2>
        <div
          className="cta-banner__body"
          dangerouslySetInnerHTML={{ __html: finalCtaBody }}
        />
        <Link className="btn btn--lime" to={primaryCtaHref}>
          {primaryCtaText || 'Start Your System — $199'}
        </Link>
      </section>
    </>
  );
}
