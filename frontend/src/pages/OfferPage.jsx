import { useEffect } from 'react';
import {
  Hero,
  CtaLink,
  Section,
  StatsBar,
  CTABanner,
  FAQAccordion,
  Stagger,
  Item,
} from '../components/v1/index.js';
import { applyOfferJsonLd, removeOfferJsonLd } from '../seo.js';

/**
 * /199 — the single campaign destination for the $199 website offer.
 *
 * Every promotional channel (social, ads, Google Business Profile,
 * directories, print, referrals) points here rather than at a deep CMS
 * package URL, so the offer reads the same everywhere and one page owns the
 * conversion. Arrival tracking is handled globally by captureAttribution()
 * in App, so any ?utm_* or ?src= code on the inbound link rides through to
 * the lead record.
 *
 * OFFER is the single place to edit price, inclusions, and promises — the
 * transactional source of truth for the package itself remains
 * backend/web/modules/custom/famtastic_pipeline/config/install/famtastic_pipeline.settings.yml
 * and the two must be kept in agreement.
 */
const OFFER = {
  price: '$199',
  name: 'FAMtastic Basic Website',
  eyebrow: 'Launch offer',
  headline: 'A professional website for your business —',
  accent: '$199.',
  lede:
    'Custom built, mobile-ready, and set up to bring you customers. One fixed price, no monthly surprises, and a real person building it — not a template you have to finish yourself.',
  bullets: [
    'One fixed price — you know the cost before we start',
    'Built for your business, not dropped into a stock template',
    'You own it: your domain, your content, your site',
  ],
  includes: [
    'Custom mobile-responsive website (core pages)',
    'Your business info, services, and contact details',
    'Contact / lead capture form so enquiries reach you',
    'Basic SEO foundation so you can be found',
    'One included revision',
    'Launch support — we get it live, not just built',
  ],
  stats: [
    { id: 'price', value: '$199', label: 'Fixed price, one time' },
    { id: 'time', value: 'Days', label: 'Not months of waiting' },
    { id: 'years', value: '22+', label: 'Years engineering systems' },
    { id: 'revision', value: '1', label: 'Revision included' },
  ],
};

const STEPS = [
  {
    id: 's1',
    number: '01',
    title: 'Tell us about your business',
    body: 'A short form — what you do, who you serve, how customers should reach you. No call required to get started.',
  },
  {
    id: 's2',
    number: '02',
    title: 'We build your site',
    body: 'A custom, mobile-ready site built around your services and your customers, with your contact details wired in.',
  },
  {
    id: 's3',
    number: '03',
    title: 'Review, revise, launch',
    body: 'You review the working site, we make your included revision, and we get it live with launch support.',
  },
];

const BEST_FOR = [
  {
    id: 'b1',
    title: 'You have no website at all',
    body: 'Customers are searching for what you do and finding your competitors instead. This gets you on the map.',
  },
  {
    id: 'b2',
    title: 'You only have a social page',
    body: 'A Facebook or Instagram page is rented ground. A real site is the one thing online that is actually yours.',
  },
  {
    id: 'b3',
    title: 'Your site is old or broken on phones',
    body: 'Most of your visitors are on a phone. If it does not work there, it does not work.',
  },
];

const FAQ = [
  {
    id: 'f1',
    question: 'What is the catch at $199?',
    answer:
      'There is no catch — $199 is the one-time build price for the package above. Domain registration and hosting are billed by your provider, and larger scopes (e-commerce, booking systems, custom applications) are quoted separately. Nothing is charged beyond $199 without you agreeing to it first.',
  },
  {
    id: 'f2',
    question: 'How long does it take?',
    answer:
      'Typically a couple of days once we have your content — your business details, services, and any photos you want used. The slowest part is usually waiting on content, so having it ready speeds everything up.',
  },
  {
    id: 'f3',
    question: 'Do I own the website?',
    answer:
      'Yes. Your domain, your content, and your site belong to you. You are not locked into us to keep it running.',
  },
  {
    id: 'f4',
    question: 'What if I need more than the basic package?',
    answer:
      'Plenty of businesses start here and grow. If you need e-commerce, bookings, automation, or a custom application, we scope and quote that separately — the $199 site is a foundation, not a dead end.',
  },
  {
    id: 'f5',
    question: 'What do you need from me?',
    answer:
      'Your business name and contact details, a short description of your services, and any logo or photos you want included. If you do not have those ready, we will help you shape them.',
  },
];

export default function OfferPage() {
  useEffect(() => {
    applyOfferJsonLd();
    return removeOfferJsonLd;
  }, []);

  return (
    <>
      <Hero
        eyebrow={OFFER.eyebrow}
        title={OFFER.headline}
        accent={OFFER.accent}
        lede={OFFER.lede}
        primaryCta={{ label: `Get started — ${OFFER.price}`, href: '/contact' }}
        secondaryCta={{ label: 'See our work', href: '/work' }}
        bullets={OFFER.bullets}
        particles
      />

      <StatsBar items={OFFER.stats} />

      <Section
        eyebrow="What you get"
        title={`Everything included for ${OFFER.price}`}
        intro="One fixed price covers the whole build — designed, built, and launched."
      >
        <ul className="v1-dot-list">
          {OFFER.includes.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      </Section>

      <Section
        eyebrow="Who this is for"
        title="If any of these sound like you, this is built for you"
      >
        <Stagger className="v1-grid v1-grid--3" stagger={0.08}>
          {BEST_FOR.map((item) => (
            <Item key={item.id} className="v1-card">
              <h3 className="v1-card__title">{item.title}</h3>
              <p className="v1-card__text">{item.body}</p>
            </Item>
          ))}
        </Stagger>
      </Section>

      <Section eyebrow="How it works" title="Three steps to a site that works">
        <Stagger className="v1-grid v1-grid--3" stagger={0.08}>
          {STEPS.map((step) => (
            <Item key={step.id} className="v1-card">
              <p className="v1-card__kicker">{step.number}</p>
              <h3 className="v1-card__title">{step.title}</h3>
              <p className="v1-card__text">{step.body}</p>
            </Item>
          ))}
        </Stagger>
      </Section>

      <Section eyebrow="Questions" title="Before you decide">
        <FAQAccordion items={FAQ} />
        <p className="v1-section__intro" style={{ marginTop: '1.5rem' }}>
          Still deciding?{' '}
          <CtaLink href="/packages" kind="ghost" label="Compare all packages" />
        </p>
      </Section>

      <CTABanner
        title="Let's get your business online."
        body={`The ${OFFER.name} is ${OFFER.price}, one time, with launch support included. Tell us about your business and we'll take it from there.`}
        primaryCta={{ label: `Get started — ${OFFER.price}`, href: '/contact' }}
        secondaryCta={{ label: 'Ask a question first', href: '/contact' }}
      />
    </>
  );
}

export { OFFER };
