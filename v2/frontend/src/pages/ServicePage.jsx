import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { matchBySlug } from '../utils/content.js';
import { transformServiceNode } from '../lib/drupalAdapter.js';
import {
  Hero,
  Section,
  TestimonialCard,
  FAQAccordion,
  CTABanner,
  FadeUp,
  Stagger,
  Item,
} from '../components/v1/index.js';
import SolutionFinder, { branchForServiceSlug } from '../components/SolutionFinder.jsx';

/**
 * /services/:slug — full v1 detail layout for one service_page node:
 * Hero → PainPoints → Solution → Process → Testimonial → Features → FAQ → CTA.
 * Sections render only when their fields have content, so partially-seeded
 * nodes still produce a clean page.
 */
export default function ServicePage() {
  const { slug } = useParams();
  const [state, setState] = useState({ service: null, loading: true });
  const [finderOpen, setFinderOpen] = useState(false);
  const finderRef = useRef(null);

  useEffect(() => {
    let cancelled = false;
    setState({ service: null, loading: true });
    getNodesRaw('service_page', {
      include: 'field_faq_qa,field_process_steps',
    }).then(({ data, included }) => {
      if (!cancelled) {
        const node = matchBySlug(data, slug);
        setState({ service: transformServiceNode(node, included), loading: false });
      }
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (state.loading) {
    return <div className="v1-loading" role="status">Loading service…</div>;
  }

  const service = state.service;

  if (!service) {
    return (
      <Section>
        <div className="v1-empty">
          <strong>We could not find that service.</strong>
          <br />
          It may have been renamed or is still being published.{' '}
          <Link to="/services">Browse all solutions</Link>.
        </div>
      </Section>
    );
  }

  const cta = { label: service.ctaText, href: service.ctaHref };
  const serviceBranch = branchForServiceSlug(slug);

  function openFinder() {
    setFinderOpen(true);
    // Let the finder render, then bring it into view.
    requestAnimationFrame(() => finderRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
  }

  return (
    <article>
      {/* 1 — HERO */}
      <Hero
        eyebrow="Service"
        title={service.headline}
        lede={service.subheadline}
        primaryCta={cta}
        secondaryCta={{ label: 'All services', href: '/services' }}
      />

      {/* 2 — PAIN POINTS */}
      {(service.painPointsTitle || service.painPoints.length > 0) && (
        <Section eyebrow="The Challenge" title={service.painPointsTitle || 'The Challenge'}>
          <Stagger className="v1-grid v1-grid--2">
            {service.painPoints.map((point) => (
              <Item key={point} className="v1-panel v1-panel--soft">
                <p className="v1-card__text" style={{ margin: 0 }}>{point}</p>
              </Item>
            ))}
          </Stagger>
        </Section>
      )}

      {/* 3 — SOLUTION */}
      {(service.solutionTitle || service.solutionBullets.length > 0) && (
        <Section eyebrow="The Solution" title={service.solutionTitle || "Here's What Changes"}>
          <FadeUp className="v1-panel">
            <ul className="v1-dot-list" style={{ marginTop: 0 }}>
              {service.solutionBullets.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          </FadeUp>
        </Section>
      )}

      {/* 4 — PROCESS */}
      {(service.processTitle || service.processSteps.length > 0) && (
        <Section eyebrow="Process" title={service.processTitle || 'How It Works'}>
          <Stagger className={`v1-process v1-process--${Math.min(service.processSteps.length, 4)}`}>
            {service.processSteps.map((step) => (
              <Item key={step.id ?? step.number} className="v1-process__step">
                <span className="v1-process__number">{step.number}</span>
                <h3 className="v1-process__title">{step.title}</h3>
                {step.body && <p className="v1-process__body">{step.body}</p>}
              </Item>
            ))}
          </Stagger>
        </Section>
      )}

      {/* 5 — TESTIMONIAL */}
      {service.testimonial.quote && (
        <Section>
          <FadeUp>
            <TestimonialCard
              quote={service.testimonial.quote}
              attribution={service.testimonial.attribution}
            />
          </FadeUp>
        </Section>
      )}

      {/* 6 — FEATURES */}
      {(service.featuresTitle || service.features.length > 0) && (
        <Section eyebrow="Deliverables" title={service.featuresTitle || "What's Included"}>
          <FadeUp className="v1-panel">
            <ul className="v1-dot-list" style={{ marginTop: 0 }}>
              {service.features.map((feature) => (
                <li key={feature}>{feature}</li>
              ))}
            </ul>
          </FadeUp>
        </Section>
      )}

      {/* 7 — FAQ */}
      {service.faqs.length > 0 && (
        <Section eyebrow="FAQ" title={service.faqTitle || 'Frequently Asked Questions'}>
          <FadeUp>
            <FAQAccordion items={service.faqs} />
          </FadeUp>
        </Section>
      )}

      {/* 8 — START WITH THIS SERVICE (SolutionFinder, branch pre-selected) */}
      <Section
        eyebrow="Start"
        title="Start with this service"
        intro="Answer a few quick questions and get an instant ballpark estimate — no phone call required."
      >
        <div ref={finderRef}>
          {finderOpen ? (
            <SolutionFinder key={slug} initialBranch={serviceBranch} />
          ) : (
            <FadeUp style={{ textAlign: 'center' }}>
              <button type="button" className="v1-btn v1-btn--primary" onClick={openFinder}>
                Start with this service →
              </button>
            </FadeUp>
          )}
        </div>
      </Section>

      {/* 9 — CTA BANNER */}
      <CTABanner
        title="Ready to put this system to work?"
        primaryCta={cta}
        secondaryCta={{ label: 'Contact', href: '/contact' }}
      />
    </article>
  );
}
