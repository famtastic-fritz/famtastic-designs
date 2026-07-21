import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { nodeSlug, textValue } from '../utils/content.js';

/**
 * /services — hub listing every service_page as a card grid.
 * Cards link to /services/<slug> (alias segment, title-slug fallback).
 */
export default function ServicesHubPage() {
  const [services, setServices] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('service_page').then(({ data }) => {
      if (!cancelled) setServices(data);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <section className="hero">
        <span className="hero__eyebrow">Services</span>
        <h1 className="hero__title">
          Our <span className="accent">Solutions</span>
        </h1>
        <p className="hero__lede">
          Agentic AI systems engineered for your specific business challenge.
        </p>
      </section>

      {services === null && <div className="loading" role="status">Loading services…</div>}

      {services !== null && services.length === 0 && (
        <div className="status">
          <p>
            <strong>Solutions are on the way.</strong>
            <br />
            We are publishing our service lineup right now — check back shortly, or{' '}
            <Link to="/contact">book a call</Link> and we will walk you through it live.
          </p>
        </div>
      )}

      {services !== null && services.length > 0 && (
        <ul className="node-list">
          {services.map((node) => {
            const attrs = node.attributes ?? {};
            return (
              <li key={node.id}>
                <Link to={`/services/${nodeSlug(node)}`} className="node-card">
                  <span className="node-card__type">Service</span>
                  <h3 className="node-card__title">{attrs.title ?? 'Untitled service'}</h3>
                  <p className="node-card__summary">
                    {textValue(attrs.field_hero_subheadline) || 'Learn how this system works for your business.'}
                  </p>
                  <span className="node-card__cta">Learn More →</span>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </>
  );
}
