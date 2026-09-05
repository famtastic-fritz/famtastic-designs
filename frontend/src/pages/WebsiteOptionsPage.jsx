import { useEffect } from 'react';
import { Link } from 'react-router';
import { applySeo } from '../components/SEO.jsx';
import { CTABanner, FadeUp, Hero, Item, Section, Stagger } from '../components/v1/index.js';
import { seoForPath } from '../seo.js';

const options = [
  {
    id: 'web-basics',
    eyebrow: 'Starter Mobile Business Foundation',
    title: 'Starter Mobile Business Foundation — $199',
    bestFor: 'A business that needs its first owned, mobile-first foundation and one clear customer action.',
    includes: [
      'A custom, focused mobile-first website with a universal contact path',
      'Business and market research plus a customer-safe 90-day growth plan',
      'Three research-backed proof directions, one direction reset, and three edit rounds after selection',
      'Twelve months of FAMtastic-managed hosting and SSL from launch',
      'A domain when one is needed (or connection guidance for one you already control)',
      'Baseline analytics, email forwarding where the provider/domain permits, and a mobile Owner Desk after verified purchase',
    ],
    details: 'The researched action path may be contact, request-to-book, quote/location, or inquiry. A client-owned payment link or QR handoff may be configured, but FAMtastic does not process client payments. Ecommerce, extra pages, custom applications, live calendar sync, mailboxes, and third-party subscriptions are not included.',
    researchHref: '/start?option=web-basics',
  },
  {
    id: 'business-website',
    eyebrow: 'Business website',
    title: 'Business Website — $499',
    bestFor: 'A business that needs up to five focused pages, not a single-page starting point.',
    includes: [
      'A responsive website with up to five standard content pages',
      'Lead capture with a customer acknowledgment and owner routing',
      'Foundational on-page SEO and crawlability setup',
      'Google Analytics connection and conversion-event foundation',
      'Two consolidated revision rounds',
      'Twelve months of business managed hosting from launch',
    ],
    details: 'Ecommerce, memberships, custom applications, custom integrations, regulated-industry review, paid advertising, full copywriting, branding, and third-party subscriptions are outside this defined scope.',
    researchHref: '/start?option=business-website',
  },
];

export default function WebsiteOptionsPage() {
  useEffect(() => applySeo(seoForPath('/website-options')), []);

  return (
    <article>
      <Hero
        eyebrow="Website starting points"
        title="Compare the $199 foundation and the $499 business website."
        lede="These are defined starting points, not a promise that either fits every request. Begin with research so the recommendation reflects the work your business actually needs."
        primaryCta={{ label: 'Start the research conversation', href: '/start' }}
        secondaryCta={{ label: 'Read the $199 foundation details', href: '/55-cents-a-day-website' }}
        bullets={[
          'A saved request comes before any website payment step.',
          'A submitted brief and selected website direction are required before website checkout becomes available.',
        ]}
      />

      <Section eyebrow="Compare" title="Two bounded options, side by side.">
        <Stagger className="v1-grid v1-grid--2">
          {options.map((option) => (
            <Item key={option.id} className="v1-card v1-panel--soft">
              <p className="v1-eyebrow">{option.eyebrow}</p>
              <h2 className="v1-card__title">{option.title}</h2>
              <p className="v1-card__text"><strong>Best when:</strong> {option.bestFor}</p>
              <ul className="v1-dot-list">
                {option.includes.map((item) => <li key={item}>{item}</li>)}
              </ul>
              <details className="v1-faq" style={{ marginTop: '1.25rem' }}>
                <summary>Read scope details</summary>
                <p>{option.details}</p>
              </details>
              <Link className="v1-btn v1-btn--light" to={option.researchHref} style={{ marginTop: '1.25rem' }}>
                Research this starting point
              </Link>
            </Item>
          ))}
        </Stagger>
      </Section>

      <Section eyebrow="How to choose" title="Start with the work—not the lowest number.">
        <FadeUp className="v1-panel">
          <p className="v1-card__text" style={{ margin: 0 }}>
            Choose the Starter Mobile Business Foundation when one focused, owned online action can do the job. Choose the Business Website when the business needs up to five standard pages and the defined features listed above. If you need ecommerce, accounts, custom integrations, more pages, or a workflow that does not fit either scope, use the research conversation instead of trying to force a package.
          </p>
        </FadeUp>
      </Section>

      <CTABanner
        title="Research first. Then continue in your account."
        body="The Solution Finder saves the information you share only after the server confirms it. You can then create an account to add a full brief, review available concepts, and continue only when the relevant request is ready."
        primaryCta={{ label: 'Start the Solution Finder', href: '/start' }}
        secondaryCta={{ label: 'Read the $199 offer', href: '/55-cents-a-day-website' }}
      />
    </article>
  );
}
