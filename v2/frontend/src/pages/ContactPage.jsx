import { useEffect, useState } from 'react';
import { getNodeByAlias } from '../api/drupal.js';
import { transformPageNode } from '../lib/drupalAdapter.js';
import { Section, ContactForm, FadeUp } from '../components/v1/index.js';
import SolutionFinder from '../components/SolutionFinder.jsx';

const CONTACT_EMAIL = 'hello@famtasticdesigns.com';

/**
 * /contact — NEW page. Pulls the `page` node whose path alias is /contact
 * for the hero copy, then renders the v1 ContactForm next to contact-info
 * cards (v1 contact page layout: info column + form column).
 */
export default function ContactPage() {
  const [page, setPage] = useState(null); // transformed page node | null

  useEffect(() => {
    let cancelled = false;
    getNodeByAlias('page', '/contact').then(({ node }) => {
      if (!cancelled) setPage(transformPageNode(node));
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
    {/* SolutionFinder leads — the intake is the primary action on /contact. */}
    <Section className="v1-section--flush-top">
      <div style={{ paddingTop: '3rem' }}>
        <SolutionFinder />
      </div>
    </Section>

    <Section>
      <div className="v1-split" style={{ paddingTop: '3rem' }}>
        <FadeUp>
          <p className="v1-eyebrow">Contact</p>
          <h1 className="v1-hero__title" style={{ fontSize: 'clamp(1.9rem, 4vw, 3rem)' }}>
            {page?.headline || "Let's Build Something Great Together"}
          </h1>
          <p className="v1-hero__lede">
            {page?.subheadline ||
              'Tell us about the website, system, or automation you need — we reply within one business day with next steps and a fixed-price scope.'}
          </p>

          {page?.bodyHtml && (
            <div className="v1-prose" style={{ marginTop: '1.4rem' }} dangerouslySetInnerHTML={{ __html: page.bodyHtml }} />
          )}

          <div className="v1-grid" style={{ marginTop: '2rem' }}>
            <div className="v1-card">
              <p className="v1-pricing-card__label">Email</p>
              <a href={`mailto:${CONTACT_EMAIL}`} className="v1-card__title" style={{ display: 'block' }}>
                {CONTACT_EMAIL}
              </a>
            </div>
            <div className="v1-card">
              <p className="v1-pricing-card__label">What happens next</p>
              <ul className="v1-dot-list">
                <li>You send the form, and your request is saved securely.</li>
                <li>We reply within one business day with a scoped, fixed-price plan.</li>
                <li>Every engagement starts with a $199 verified discovery build.</li>
              </ul>
            </div>
          </div>
        </FadeUp>

        <FadeUp delay={0.12}>
          <ContactForm title="Prefer to write it out?" />
        </FadeUp>
      </div>
    </Section>
    </>
  );
}
