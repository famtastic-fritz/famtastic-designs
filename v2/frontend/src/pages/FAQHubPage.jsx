import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { textValue } from '../utils/content.js';

/**
 * /faq — every faq_item grouped by field_faq_category, each rendered as an
 * expand/collapse accordion. Uncategorized items fall back to "General".
 */
export default function FAQHubPage() {
  const [groups, setGroups] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('faq_item').then(({ data }) => {
      if (cancelled) return;
      const byCategory = new Map();
      for (const node of data) {
        const attrs = node.attributes ?? {};
        const category = textValue(attrs.field_faq_category) || 'General';
        if (!byCategory.has(category)) byCategory.set(category, []);
        byCategory.get(category).push({
          id: node.id,
          question: textValue(attrs.field_question) || attrs.title || 'Question',
          answer: textValue(attrs.field_answer) || textValue(attrs.body),
        });
      }
      setGroups([...byCategory.entries()]);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <section className="hero">
        <span className="hero__eyebrow">FAQ</span>
        <h1 className="hero__title">
          Questions, <span className="accent">answered</span>
        </h1>
        <p className="hero__lede">
          Everything you need to know before we start building together.
        </p>
      </section>

      {groups === null && <div className="loading" role="status">Loading FAQ…</div>}

      {groups !== null && groups.length === 0 && (
        <div className="status">
          <p>
            <strong>Answers are on the way.</strong>
            <br />
            Our FAQ is being published right now — meanwhile,{' '}
            <Link to="/contact">ask us anything</Link> directly.
          </p>
        </div>
      )}

      {groups !== null &&
        groups.map(([category, items]) => (
          <section key={category} className="feature-section" aria-labelledby={`faq-${category}`}>
            <h2 id={`faq-${category}`} className="feature-section__title">
              {category}
            </h2>
            <FaqGroup items={items} />
          </section>
        ))}
    </>
  );
}

/** One accordion group; a single open item at a time within the group. */
function FaqGroup({ items }) {
  const [openIndex, setOpenIndex] = useState(null);

  return (
    <div className="accordion">
      {items.map((item, i) => {
        const open = openIndex === i;
        return (
          <div key={item.id} className={`accordion__item${open ? ' accordion__item--open' : ''}`}>
            <button
              type="button"
              className="accordion__question"
              aria-expanded={open}
              onClick={() => setOpenIndex(open ? null : i)}
            >
              <span>{item.question}</span>
              <span className="accordion__chevron" aria-hidden="true">
                {open ? '−' : '+'}
              </span>
            </button>
            {open && item.answer && (
              <div
                className="accordion__answer"
                dangerouslySetInnerHTML={{ __html: item.answer }}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}
