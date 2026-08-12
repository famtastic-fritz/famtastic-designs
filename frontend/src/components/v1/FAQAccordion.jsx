import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';

/**
 * v1 FAQ accordion — one open item at a time, smooth height animation via
 * framer-motion (height: auto spring), rotating + / − chevron. Answers may
 * contain HTML from Drupal formatted-text fields.
 */
export default function FAQAccordion({ items = [] }) {
  const [openIndex, setOpenIndex] = useState(null);
  if (!items.length) return null;

  return (
    <div className="v1-faq">
      {items.map((item, i) => {
        const open = openIndex === i;
        const answer = item.answer ?? '';
        const isHtml = /<[a-z][\s\S]*>/i.test(answer);
        const questionId = `faq-question-${i}`;
        const answerId = `faq-answer-${i}`;
        return (
          <div key={item.id ?? item.question ?? i} className={`v1-faq__item${open ? ' v1-faq__item--open' : ''}`}>
            <button
              type="button"
              className="v1-faq__question"
              aria-expanded={open}
              aria-controls={answerId}
              id={questionId}
              onClick={() => setOpenIndex(open ? null : i)}
            >
              <span>{item.question}</span>
              <motion.span
                className="v1-faq__chevron"
                aria-hidden="true"
                animate={{ rotate: open ? 45 : 0 }}
                transition={{ duration: 0.25 }}
              >
                +
              </motion.span>
            </button>
            <AnimatePresence initial={false}>
              {open && answer && (
                <motion.div
                  id={answerId}
                  role="region"
                  aria-labelledby={questionId}
                  className="v1-faq__answer-wrap"
                  initial={{ height: 0, opacity: 0 }}
                  animate={{ height: 'auto', opacity: 1 }}
                  exit={{ height: 0, opacity: 0 }}
                  transition={{ duration: 0.32, ease: [0.22, 1, 0.36, 1] }}
                >
                  {isHtml ? (
                    <div className="v1-faq__answer" dangerouslySetInnerHTML={{ __html: answer }} />
                  ) : (
                    <p className="v1-faq__answer">{answer}</p>
                  )}
                </motion.div>
              )}
            </AnimatePresence>
          </div>
        );
      })}
    </div>
  );
}
