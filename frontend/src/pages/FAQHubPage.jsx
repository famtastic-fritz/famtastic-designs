import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { transformFaqNodes } from '../lib/drupalAdapter.js';
import { Hero, Section, FAQAccordion, CTABanner, FadeUp } from '../components/v1/index.js';

/**
 * /faq — every faq_item grouped by its taxonomy category, rendered with the
 * v1 smooth-height FAQAccordion. Uncategorized items fall back to "General".
 */
export default function FAQHubPage() {
  const [groups, setGroups] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('faq_item', { include: 'field_faq_category' }).then(({ data, included }) => {
      if (cancelled) return;
      const items = transformFaqNodes(data, included);
      const byCategory = new Map();
      for (const item of items) {
        if (!byCategory.has(item.category)) byCategory.set(item.category, []);
        byCategory.get(item.category).push(item);
      }
      setGroups([...byCategory.entries()]);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <Hero
        eyebrow="FAQ"
        title="Questions,"
        accent="answered"
        lede="Everything you need to know before we start building together."
        primaryCta={{ label: 'Ask us anything', href: '/contact' }}
      />

      {groups === null && <div className="v1-loading" role="status">Loading FAQ…</div>}

      {groups !== null && groups.length === 0 && (
        <Section>
          <div className="v1-empty">
            <strong>Answers are on the way.</strong>
            <br />
            Our FAQ is being published right now — meanwhile,{' '}
            <Link to="/contact">ask us anything</Link> directly.
          </div>
        </Section>
      )}

      {groups !== null &&
        groups.map(([category, items]) => (
          <Section key={category} eyebrow={category} title={`${category} questions`}>
            <FadeUp>
              <FAQAccordion items={items} />
            </FadeUp>
          </Section>
        ))}

      <CTABanner
        title="Still have a question?"
        primaryCta={{ label: 'Contact us', href: '/contact' }}
      />
    </>
  );
}
