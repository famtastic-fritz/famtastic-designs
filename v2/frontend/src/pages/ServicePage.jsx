import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getNodesRaw, resolveIncluded } from '../api/drupal.js';
import {
  isExternalHref,
  linkHref,
  listValues,
  matchBySlug,
  paraField,
  textValue,
} from '../utils/content.js';

/**
 * /services/:slug — full landing page for one service_page node.
 * Sections render only when their fields have content, so partially-seeded
 * nodes still produce a clean page.
 */
export default function ServicePage() {
  const { slug } = useParams();
  const [state, setState] = useState({ node: null, included: [], loading: true });

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('service_page', {
      include: 'field_faq_qa,field_process_steps',
    }).then(({ data, included }) => {
      if (!cancelled) {
        setState({ node: matchBySlug(data, slug), included, loading: false });
      }
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (state.loading) {
    return <div className="loading" role="status">Loading service…</div>;
  }

  if (!state.node) {
    return (
      <div className="status">
        <p>
          <strong>We could not find that service.</strong>
          <br />
          It may have been renamed or is still being published.{' '}
          <Link to="/services">Browse all solutions</Link>.
        </p>
      </div>
    );
  }

  const attrs = state.node.attributes ?? {};
  const faqItems = resolveIncluded(state.node, state.included, 'field_faq_qa');
  const processSteps = resolveIncluded(state.node, state.included, 'field_process_steps');

  const heroHeadline = textValue(attrs.field_hero_headline) || attrs.title || 'Service';
  const heroSub = textValue(attrs.field_hero_subheadline);
  const painTitle = textValue(attrs.field_pain_points_title);
  const painPoints = listValues(attrs.field_pain_points);
  const solutionTitle = textValue(attrs.field_solution_title);
  const solutionBullets = listValues(attrs.field_solution_bullets);
  const processTitle = textValue(attrs.field_process_title);
  const quote = textValue(attrs.field_testimonial_quote);
  const attribution = textValue(attrs.field_testimonial_attribution);
  const featuresTitle = textValue(attrs.field_features_title);
  const features = listValues(attrs.field_features);
  const faqTitle = textValue(attrs.field_faq_title);
  const ctaText = textValue(attrs.field_cta_text) || 'Book a Call';
  const ctaHref = linkHref(attrs.field_cta_link);

  return (
    <article className="service-page">
      {/* 1 — HERO */}
      <section className="hero">
        <span className="hero__eyebrow">Service</span>
        <h1 className="hero__title">
          <span className="accent">{heroHeadline}</span>
        </h1>
        {heroSub && <p className="hero__lede">{heroSub}</p>}
        <p className="hero__actions">
          <CtaButton href={ctaHref} label={ctaText} />
        </p>
      </section>

      {/* 2 — PAIN POINTS */}
      {(painTitle || painPoints.length > 0) && (
        <section className="feature-section" aria-labelledby="pain-heading">
          <h2 id="pain-heading" className="feature-section__title">
            {painTitle || 'The Challenge'}
          </h2>
          {painPoints.length > 0 && (
            <ul className="bullet-list">
              {painPoints.map((point, i) => (
                <li key={i}>{point}</li>
              ))}
            </ul>
          )}
        </section>
      )}

      {/* 3 — SOLUTION */}
      {(solutionTitle || solutionBullets.length > 0) && (
        <section className="feature-section" aria-labelledby="solution-heading">
          <h2 id="solution-heading" className="feature-section__title">
            {solutionTitle || 'The Solution'}
          </h2>
          {solutionBullets.length > 0 && (
            <ul className="bullet-list bullet-list--lime">
              {solutionBullets.map((item, i) => (
                <li key={i}>{item}</li>
              ))}
            </ul>
          )}
        </section>
      )}

      {/* 4 — PROCESS */}
      {(processTitle || processSteps.length > 0) && (
        <section className="feature-section" aria-labelledby="process-heading">
          <h2 id="process-heading" className="feature-section__title">
            {processTitle || 'How It Works'}
          </h2>
          {processSteps.length > 0 && (
            <ol className="process-list">
              {processSteps.map((step, i) => {
                const title = paraField(step, ['field_step_title', 'field_title']);
                const body = paraField(step, ['field_step_description', 'field_description', 'field_body']);
                const number = paraField(step, ['field_step_number']) || String(i + 1).padStart(2, '0');
                return (
                  <li key={step.id ?? i} className="process-list__item">
                    <span className="process-list__number">{number}</span>
                    <div>
                      {title && <h3 className="process-list__title">{title}</h3>}
                      {body && <p className="process-list__body">{body}</p>}
                    </div>
                  </li>
                );
              })}
            </ol>
          )}
        </section>
      )}

      {/* 5 — TESTIMONIAL */}
      {quote && (
        <section className="feature-section" aria-label="Testimonial">
          <blockquote className="testimonial">
            <p className="testimonial__quote">“{quote}”</p>
            {attribution && <cite className="testimonial__attribution">— {attribution}</cite>}
          </blockquote>
        </section>
      )}

      {/* 6 — FEATURES */}
      {(featuresTitle || features.length > 0) && (
        <section className="feature-section" aria-labelledby="features-heading">
          <h2 id="features-heading" className="feature-section__title">
            {featuresTitle || "What's Included"}
          </h2>
          {features.length > 0 && (
            <ul className="check-list">
              {features.map((feature, i) => (
                <li key={i}>{feature}</li>
              ))}
            </ul>
          )}
        </section>
      )}

      {/* 7 — FAQ */}
      {faqItems.length > 0 && (
        <section className="feature-section" aria-labelledby="faq-heading">
          <h2 id="faq-heading" className="feature-section__title">
            {faqTitle || 'Frequently Asked Questions'}
          </h2>
          <FaqAccordion items={faqItems} />
        </section>
      )}

      {/* 8 — CTA BANNER */}
      <section className="cta-banner">
        <h2 className="cta-banner__title">Ready to put this system to work?</h2>
        <CtaButton href={ctaHref} label={ctaText} />
      </section>
    </article>
  );
}

/** Internal links use <Link>, external ones a plain anchor. */
function CtaButton({ href, label }) {
  if (isExternalHref(href)) {
    return (
      <a className="btn btn--lime" href={href}>
        {label}
      </a>
    );
  }
  return (
    <Link className="btn btn--lime" to={href}>
      {label}
    </Link>
  );
}

/** Simple expand/collapse accordion driven by openIndex state. */
function FaqAccordion({ items }) {
  const [openIndex, setOpenIndex] = useState(null);

  return (
    <div className="accordion">
      {items.map((item, i) => {
        const question = paraField(item, ['field_question']) || `Question ${i + 1}`;
        const answer = paraField(item, ['field_answer']);
        const open = openIndex === i;
        return (
          <div key={item.id ?? i} className={`accordion__item${open ? ' accordion__item--open' : ''}`}>
            <button
              type="button"
              className="accordion__question"
              aria-expanded={open}
              onClick={() => setOpenIndex(open ? null : i)}
            >
              <span>{question}</span>
              <span className="accordion__chevron" aria-hidden="true">
                {open ? '−' : '+'}
              </span>
            </button>
            {open && answer && (
              <div
                className="accordion__answer"
                dangerouslySetInnerHTML={{ __html: answer }}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}
