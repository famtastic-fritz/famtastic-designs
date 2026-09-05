import { useEffect, useState } from 'react';
import { getNodesRaw } from '../api/drupal.js';
import {
  transformHomepageNode,
  transformServiceNode,
} from '../lib/drupalAdapter.js';
import {
  Hero,
  Section,
  ServiceCard,
  CTABanner,
  Stagger,
  Item,
} from '../components/v1/index.js';
import SolutionFinder from '../components/SolutionFinder.jsx';

/**
 * / — marketing homepage, v1 visual port.
 *
 * Composition: particle Hero → guided research → website options → services → Why → Process →
 * final CTA, all from the `homepage` node + service_page teasers via the
 * drupalAdapter. When the homepage node is missing/unreachable the
 * HARDCODED FALLBACK renders the same messaging so the page never ships empty.
 */
const FALLBACK = {
  headline: 'Websites and Digital Systems Built Around the Work You Need Done',
  subheadline:
    'Start with practical research about your business, then move into a saved brief, a clear recommendation, and the next appropriate step.',
  proofBullets: [
    'Research identifies the starting point before a package is chosen.',
    'A website request is saved only after the server confirms it.',
  ],
  whyTitle: 'Why FAMtastic Designs?',
  whyItems: [
    {
      id: 'w1',
      title: 'A website should act like a business asset',
      body: 'Not a brochure that just sits there — every build starts with the customer action and operating work it needs to support.',
    },
    {
      id: 'w2',
      title: 'Engineered, not templated',
      body: 'Your system is designed for your specific business workflow, then verified end to end.',
    },
    {
      id: 'w3',
      title: 'The right scope before the sale',
      body: 'We start by understanding the job, then recommend the smallest complete scope that can accomplish it.',
    },
  ],
  processTitle: 'How We Build Your System',
  processSteps: [
    { id: 'p1', number: '01', title: 'Discover', body: 'A short call to map your workflow, goals, and the system that fits.' },
    { id: 'p2', number: '02', title: 'Scope', body: 'We match the work to a clear package or define the custom requirements before the build begins.' },
    { id: 'p3', number: '03', title: 'Build', body: 'Fixed-scope build with weekly check-ins — you always know where things stand.' },
    { id: 'p4', number: '04', title: 'Launch & Support', body: 'We launch, monitor, and keep your system sharp with care plans.' },
  ],
  finalCta: {
    title: 'Your System Should Work As Hard As You Do',
    bodyHtml:
      '<p>Start with the website assessment. We will use what your business actually needs—not one default price—to shape the right next step.</p>',
  },
};

export default function HomePage() {
  const [home, setHome] = useState(null); // transformed homepage | null
  const [services, setServices] = useState([]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      // Degrade the include list until the request succeeds so the hero
      // still renders if a paragraph type is missing on the backend.
      const includeOptions = [
        'field_process_steps,field_why_items',
        'field_process_steps',
        '',
      ];
      for (const include of includeOptions) {
        const { data, included, error } = await getNodesRaw('homepage', { include, limit: 1 });
        if (error) continue;
        if (!cancelled && data.length > 0) {
          setHome(transformHomepageNode(data[0], included));
        }
        break;
      }
    })();
    getNodesRaw('service_page', { limit: 6 })
      .then(({ data }) => {
        if (!cancelled) {
          setServices(
            data
              .map((node) => transformServiceNode(node))
              .filter(Boolean)
              .sort((a, b) => a.sortOrder - b.sortOrder)
              .slice(0, 6),
          );
        }
      })
      .catch(() => {
        /* teaser grid stays empty */
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const headline = home?.headline || FALLBACK.headline;
  const subheadline = home?.subheadline || FALLBACK.subheadline;
  const whyTitle = home?.whyTitle || FALLBACK.whyTitle;
  const whyItems = home?.whyItems?.length ? home.whyItems : FALLBACK.whyItems;
  const processTitle = home?.processTitle || FALLBACK.processTitle;
  const processSteps = home?.processSteps?.length ? home.processSteps : FALLBACK.processSteps;
  const finalCta = home?.finalCta?.title ? home.finalCta : FALLBACK.finalCta;

  return (
    <>
      <Hero
        particles
        eyebrow="FAMtastic Designs"
        title={headline}
        lede={subheadline}
        primaryCta={{ label: 'Start the research conversation', href: '/start' }}
        secondaryCta={{ label: 'Compare website starting points', href: '/website-options' }}
        bullets={FALLBACK.proofBullets}
      />

      {/* SolutionFinder — primary lead capture, directly below the hero. */}
      <Section>
        <SolutionFinder />
      </Section>

      <CTABanner
        title="Choose the website starting point that fits the work."
        body="Compare the defined $199 Web Basics and $499 Business Website scopes, then use the Solution Finder to save research for the option that fits."
        primaryCta={{ label: 'Compare $199 and $499 options', href: '/website-options' }}
        secondaryCta={{ label: 'Start the Solution Finder', href: '/start' }}
      />

      <Section
        eyebrow="Services"
        title="Systems engineered for real business outcomes."
        intro="Every service is a fixed-scope system designed to capture leads, answer customers, or run operations — not a template install."
        link={{ href: '/services', label: 'See all services' }}
      >
        {services.length > 0 ? (
          <Stagger className="v1-grid v1-grid--3">
            {services.map((service) => (
              <Item key={service.id}>
                <ServiceCard service={service} />
              </Item>
            ))}
          </Stagger>
        ) : (
          <div className="v1-empty">
            Our service lineup is being published right now — meanwhile, the packages below show
            exactly how an engagement starts.
          </div>
        )}
      </Section>

      <Section eyebrow="Why FAMtastic" title={whyTitle}>
        <Stagger className="v1-grid v1-grid--3">
          {whyItems.map((item) => (
            <Item key={item.id} className="v1-panel v1-panel--soft">
              <h3 className="v1-card__title">{item.title}</h3>
              {item.body && <p className="v1-card__text">{item.body}</p>}
            </Item>
          ))}
        </Stagger>
      </Section>

      <Section id="process" eyebrow="Process" title={processTitle}>
        <Stagger className={`v1-process v1-process--${Math.min(processSteps.length, 4)}`}>
          {processSteps.map((step) => (
            <Item key={step.id ?? step.number} className="v1-process__step">
              <span className="v1-process__number">{step.number}</span>
              <h3 className="v1-process__title">{step.title}</h3>
              {step.body && <p className="v1-process__body">{step.body}</p>}
            </Item>
          ))}
        </Stagger>
      </Section>

      <CTABanner
        title={finalCta.title}
        bodyHtml={finalCta.bodyHtml}
        primaryCta={{ label: 'Start the research conversation', href: '/start' }}
        secondaryCta={{ label: 'Compare website starting points', href: '/website-options' }}
      />
    </>
  );
}
