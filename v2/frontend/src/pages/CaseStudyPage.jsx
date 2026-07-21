import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { listValues, matchBySlug, textValue } from '../utils/content.js';

/**
 * /work/:slug — single case_study node: title, optional subtitle/summary,
 * key results list (when present) and the full body.
 */
export default function CaseStudyPage() {
  const { slug } = useParams();
  const [state, setState] = useState({ node: null, loading: true });

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('case_study').then(({ data }) => {
      if (!cancelled) setState({ node: matchBySlug(data, slug), loading: false });
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (state.loading) {
    return <div className="loading" role="status">Loading case study…</div>;
  }

  if (!state.node) {
    return (
      <div className="status">
        <p>
          <strong>We could not find that case study.</strong>
          <br />
          <Link to="/work">Browse all of our work</Link>.
        </p>
      </div>
    );
  }

  const attrs = state.node.attributes ?? {};
  const subtitle = textValue(attrs.field_subtitle) || textValue(attrs.field_summary);
  const results = listValues(attrs.field_results);
  const body = textValue(attrs.body);

  return (
    <article className="node-view">
      <Link to="/work" className="node-view__back">
        ← All work
      </Link>
      <h1 className="node-view__title">{attrs.title ?? 'Case Study'}</h1>
      {subtitle && <p className="hero__lede">{subtitle}</p>}

      {results.length > 0 && (
        <section className="feature-section" aria-labelledby="results-heading">
          <h2 id="results-heading" className="feature-section__title">
            Key Results
          </h2>
          <ul className="check-list">
            {results.map((result, i) => (
              <li key={i}>{result}</li>
            ))}
          </ul>
        </section>
      )}

      {body ? (
        <div
          className="node-view__body"
          dangerouslySetInnerHTML={{ __html: body }}
        />
      ) : (
        <div className="status">
          <p>The full write-up for this project is being published — check back soon.</p>
        </div>
      )}

      <section className="cta-banner">
        <h2 className="cta-banner__title">Want results like these?</h2>
        <Link className="btn btn--lime" to="/contact">
          Book a Call
        </Link>
      </section>
    </article>
  );
}
