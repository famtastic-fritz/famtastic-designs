import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { nodeSlug, textValue } from '../utils/content.js';

/**
 * /work — hub listing every case_study as a card grid.
 */
export default function WorkHubPage() {
  const [studies, setStudies] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('case_study').then(({ data }) => {
      if (!cancelled) setStudies(data);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <section className="hero">
        <span className="hero__eyebrow">Case Studies</span>
        <h1 className="hero__title">
          Our <span className="accent">Work</span>
        </h1>
        <p className="hero__lede">
          Real systems, real outcomes — a look at what we have engineered for our clients.
        </p>
      </section>

      {studies === null && <div className="loading" role="status">Loading case studies…</div>}

      {studies !== null && studies.length === 0 && (
        <div className="status">
          <p>
            <strong>Case studies are being written up.</strong>
            <br />
            We are documenting recent projects right now — meanwhile,{' '}
            <Link to="/contact">ask us for examples</Link> on a call.
          </p>
        </div>
      )}

      {studies !== null && studies.length > 0 && (
        <ul className="node-list">
          {studies.map((node) => {
            const attrs = node.attributes ?? {};
            const summary =
              textValue(attrs.field_subtitle) ||
              textValue(attrs.field_summary) ||
              textValue(attrs.body?.summary) ||
              'See the challenge, the build, and the outcome.';
            return (
              <li key={node.id}>
                <Link to={`/work/${nodeSlug(node)}`} className="node-card">
                  <span className="node-card__type">Case Study</span>
                  <h3 className="node-card__title">{attrs.title ?? 'Untitled project'}</h3>
                  <p className="node-card__summary">{summary}</p>
                  <span className="node-card__cta">Read the Story →</span>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </>
  );
}
