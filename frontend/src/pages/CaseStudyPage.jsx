import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { matchBySlug } from '../utils/content.js';
import { transformCaseStudyNode } from '../lib/drupalAdapter.js';
import { Hero, Section, CTABanner, FadeUp } from '../components/v1/index.js';

/**
 * /work/:slug — single case_study node: hero, key results checklist, full body.
 */
export default function CaseStudyPage() {
  const { slug } = useParams();
  const [state, setState] = useState({ study: null, loading: true });

  useEffect(() => {
    let cancelled = false;
    setState({ study: null, loading: true });
    getNodesRaw('case_study').then(({ data }) => {
      if (!cancelled) {
        setState({ study: transformCaseStudyNode(matchBySlug(data, slug)), loading: false });
      }
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (state.loading) {
    return <div className="v1-loading" role="status">Loading case study…</div>;
  }

  const study = state.study;

  if (!study) {
    return (
      <Section>
        <div className="v1-empty">
          <strong>We could not find that case study.</strong>
          <br />
          <Link to="/work">Browse all of our work</Link>.
        </div>
      </Section>
    );
  }

  return (
    <article>
      <Hero
        eyebrow={study.projectType || 'Case Study'}
        title={study.title}
        lede={study.subtitle}
        primaryCta={{ label: 'Start your project', href: '/contact' }}
      />

      <Section>
        <Link to="/work" className="v1-back-link">
          ← All work
        </Link>

        {study.results.length > 0 && (
          <FadeUp className="v1-panel" style={{ marginBottom: '1.5rem' }}>
            <h2 className="v1-section__title" style={{ marginTop: 0 }}>Key Results</h2>
            <ul className="v1-dot-list">
              {study.results.map((result) => (
                <li key={result}>{result}</li>
              ))}
            </ul>
          </FadeUp>
        )}

        {study.bodyHtml ? (
          <FadeUp className="v1-panel">
            <div className="v1-prose" dangerouslySetInnerHTML={{ __html: study.bodyHtml }} />
          </FadeUp>
        ) : (
          <div className="v1-empty">
            The full write-up for this project is being published — check back soon.
          </div>
        )}
      </Section>

      <CTABanner
        title="Want results like these?"
        primaryCta={{ label: 'Book a Call', href: '/contact' }}
      />
    </article>
  );
}
