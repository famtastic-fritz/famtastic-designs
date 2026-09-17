import { useEffect, useState } from 'react';
import { getNodeByAlias } from '../api/drupal.js';
import { transformPageNode } from '../lib/drupalAdapter.js';
import { ContactForm } from '../components/v1/index.js';
import SolutionFinder from '../components/SolutionFinder.jsx';
import { FAMPage, FAMPageHero, FAMSection, FAMBrush } from '../components/content-experience/index.jsx';

const CONTACT_EMAIL = 'hello@famtasticdesigns.com';

/**
 * Existing intake-first workflow, with the approved invitation recipe.
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
    <FAMPage id="contact" recipe="contact">
    <FAMPageHero id="invitation" eyebrow="Contact / Have an idea?"
      title={page?.headline || "Let's Build Something Great Together"} signature="Great Together"
      lede={page?.subheadline || 'Tell us about the website, system, or automation you need — we reply within one business day with next steps and a fixed-price scope.'}
      primaryCta={{ label: 'Find your project fit', href: '/contact#project-fit' }}
      secondaryCta={{ label: 'Send us a message', href: '/contact#contact-form' }} />
    {/* SolutionFinder leads — the intake is the primary action on /contact. */}
    <FAMSection id="project-fit" eyebrow="Find your project fit"><SolutionFinder /></FAMSection>

    <FAMSection id="contact-form" eyebrow="A direct conversation" title="Tell us what you have in mind." signature="in mind.">
      <div className="fam-ce-contact-layout">
        <aside className="fam-ce-contact-notes" aria-label="Contact details and next steps">
          <FAMBrush tone="lime" />
          <div className="fam-ce-contact-email">
              <p className="fam-ce-eyebrow">Email</p>
              <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>
              <p>
                Send a note whenever it works for you. We respond by email within 1 business day.
              </p>
          </div>
          <div>
              <h3>What happens next</h3>
              <ol className="fam-ce-contact-next">
                <li>You send the form, and your request is saved securely.</li>
                <li>We reply within one business day with a scoped, fixed-price plan.</li>
                <li>Focused one-page sites start at the $199 Web Basics foundation; defined business sites up to five pages at $499.</li>
              </ol>
          </div>
        </aside>
        <ContactForm title="Send Us a Message" brandSuccess />
      </div>
    </FAMSection>
    </FAMPage>
  );
}
