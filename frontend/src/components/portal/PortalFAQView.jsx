import { Panel } from './PortalShared.jsx';

export default function PortalFAQView({
  filteredFaqs,
  faqSearch,
  setFaqSearch,
  go,
}) {
  return (
    <>
      <label className="portal-search" htmlFor="faq-search">
        <span>Search questions and answers</span>
        <input
          id="faq-search"
          type="search"
          value={faqSearch}
          onChange={(event) => setFaqSearch(event.target.value)}
          placeholder="Try: domain, hosting, AI agent, billing…"
        />
      </label>

      <section className="portal-faqs">
        {filteredFaqs.length ? (
          filteredFaqs.map((faq) => (
            <details key={faq.id || faq.question}>
              <summary>{faq.question}</summary>
              <p>{faq.answer}</p>
              <small>{faq.category}</small>
            </details>
          ))
        ) : (
          <Panel eyebrow="No answer found" title="Ask FAMtastic">
            <p>Start a support request and we’ll keep the answer with your account.</p>
            <button type="button" onClick={() => go('support')}>
              Get help
            </button>
          </Panel>
        )}
      </section>
    </>
  );
}
