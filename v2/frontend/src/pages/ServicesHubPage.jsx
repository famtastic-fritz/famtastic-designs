import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { transformServiceNode } from '../lib/drupalAdapter.js';
import { Hero, Section, ServiceCard, CTABanner, Stagger, Item } from '../components/v1/index.js';

/**
 * /services — hub listing every service_page as v1 ServiceCards.
 */
export default function ServicesHubPage() {
  const [services, setServices] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('service_page').then(({ data }) => {
      if (!cancelled) {
        setServices(
          data
            .map((node) => transformServiceNode(node))
            .filter(Boolean)
            .sort((a, b) => a.sortOrder - b.sortOrder),
        );
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <Hero
        eyebrow="Services"
        title="Systems that capture, answer, and"
        accent="grow"
        lede="Agentic AI systems engineered for your specific business challenge — websites, chatbots, lead capture, and client systems built to support growth."
        primaryCta={{ label: 'Start Your Project', href: '/contact' }}
        secondaryCta={{ label: 'See packages', href: '/packages' }}
      />

      <Section>
        {services === null && <div className="v1-loading" role="status">Loading services…</div>}

        {services !== null && services.length === 0 && (
          <div className="v1-empty">
            <strong>Solutions are on the way.</strong>
            <br />
            We are publishing our service lineup right now — check back shortly, or{' '}
            <Link to="/contact">book a call</Link> and we will walk you through it live.
          </div>
        )}

        {services !== null && services.length > 0 && (
          <Stagger className="v1-grid v1-grid--3">
            {services.map((service) => (
              <Item key={service.id}>
                <ServiceCard service={service} />
              </Item>
            ))}
          </Stagger>
        )}
      </Section>

      <CTABanner
        title="Not sure which system fits?"
        body="A short consultation maps your workflow to the right build — fixed scope, fixed price, verified before launch."
        primaryCta={{ label: 'Book a Call', href: '/contact' }}
      />
    </>
  );
}
