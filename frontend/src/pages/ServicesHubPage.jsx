import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { transformServiceNode } from '../lib/drupalAdapter.js';
import { FAMPage, FAMPageHero, FAMSection, FAMFinale } from '../components/content-experience/index.jsx';
import { CapabilityCard } from '../components/content-experience/CatalogCards.jsx';

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
    <FAMPage id="services" recipe="services-hub">
      <FAMPageHero id="intro"
        eyebrow="Services"
        title="Systems that capture, answer, and grow."
        signature="grow."
        lede="Agentic AI systems engineered for your specific business challenge — websites, chatbots, lead capture, and client systems built to support growth."
        primaryCta={{ label: 'Start Your Project', href: '/contact' }}
        secondaryCta={{ label: 'See packages', href: '/packages' }}
      />

      <FAMSection id="capabilities" eyebrow="The capability lineup" title="What are we building?" signature="building?">
        {services === null && <div className="v1-loading" role="status">Loading services…</div>}

        {services !== null && services.length === 0 && (
          <div className="v1-empty">
            <strong>Solutions are on the way.</strong>
            <br />
            We are publishing our service lineup right now — check back shortly, or{' '}
            <Link to="/contact#contact-form">send us your questions</Link> and we will reply with clear next steps.
          </div>
        )}

        {services !== null && services.length > 0 && (
          <div className="fam-ce-capability-catalog">
            {services.map((service, index) => (
              <CapabilityCard key={service.id} service={service} number={index + 1} />
            ))}
          </div>
        )}
      </FAMSection>

      <FAMFinale id="finale"
        title="Not sure which system fits?"
        body="A short consultation maps your workflow to the right build — fixed scope, fixed price, verified before launch."
        primaryCta={{ label: 'Start Your Project', href: '/contact#project-fit' }}
      />
    </FAMPage>
  );
}
