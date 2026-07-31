import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { transformCaseStudyNode } from '../lib/drupalAdapter.js';
import { Hero, Section, CTABanner, Stagger, Item } from '../components/v1/index.js';

/**
 * /work — hub listing every case_study as a v1 card grid (sky kicker for the
 * project type, per the v1 work page).
 */
export default function WorkHubPage() {
  const [studies, setStudies] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('case_study').then(({ data }) => {
      if (!cancelled) {
        setStudies(data.map((node) => transformCaseStudyNode(node)).filter(Boolean));
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <Hero
        eyebrow="Case Studies"
        title="Our"
        accent="work"
        lede="Real systems, real outcomes — a look at what we have engineered for our clients."
        primaryCta={{ label: 'Start your project', href: '/contact' }}
      />

      <Section>
        {studies === null && <div className="v1-loading" role="status">Loading case studies…</div>}

        {studies !== null && studies.length === 0 && (
          <div className="v1-empty">
            <strong>Case studies are being written up.</strong>
            <br />
            We are documenting recent projects right now — meanwhile,{' '}
            <Link to="/contact">ask us for examples</Link> on a call.
          </div>
        )}

        {studies !== null && studies.length > 0 && (
          <Stagger className="v1-grid v1-grid--3">
            {studies.map((study) => (
              <Item key={study.id}>
                <article className="v1-card">
                  <span className="v1-card__kicker">{study.projectType || 'Case Study'}</span>
                  <h3 className="v1-card__title">{study.title}</h3>
                  {study.summary && <p className="v1-card__text">{study.summary}</p>}
                  <Link to={`/work/${study.slug}`} className="v1-card__cta">
                    Read the Story →
                  </Link>
                </article>
              </Item>
            ))}
          </Stagger>
        )}
      </Section>

      <CTABanner
        title="Need a project direction that matches your business?"
        primaryCta={{ label: 'Book a Call', href: '/contact' }}
      />
    </>
  );
}
