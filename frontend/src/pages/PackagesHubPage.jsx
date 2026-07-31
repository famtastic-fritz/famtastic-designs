import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { transformPackageNode } from '../lib/drupalAdapter.js';
import { Hero, Section, PricingCard, CTABanner, Stagger, Item } from '../components/v1/index.js';

/**
 * /packages — pricing hub listing every package_page as a v1 PricingCard,
 * sorted by field_sort_order (the offer ladder: $199 entry point first).
 */
export default function PackagesHubPage() {
  const [packages, setPackages] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('package_page').then(({ data }) => {
      if (!cancelled) {
        setPackages(
          data
            .map((node) => transformPackageNode(node))
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
        eyebrow="Packages"
        title="Simple, honest"
        accent="pricing"
        lede="Fixed-scope packages engineered to ship — pick a starting lane and final scope is confirmed after a short consultation so the project fits your goals, timeline, and budget."
        primaryCta={{ label: 'Start — $199', href: '/packages/199-quick-start' }}
        secondaryCta={{ label: 'Book a Call', href: '/contact' }}
      />

      <Section>
        {packages === null && <div className="v1-loading" role="status">Loading packages…</div>}

        {packages !== null && packages.length === 0 && (
          <div className="v1-empty">
            <strong>Packages are being finalized.</strong>
            <br />
            Pricing details are being published right now — meanwhile,{' '}
            <Link to="/contact">book a call</Link> for a same-day quote.
          </div>
        )}

        {packages !== null && packages.length > 0 && (
          <Stagger className="v1-grid v1-grid--3">
            {packages.map((plan) => (
              <Item key={plan.id}>
                <PricingCard plan={plan} />
              </Item>
            ))}
          </Stagger>
        )}
      </Section>

      <CTABanner
        title="Every engagement starts at $199."
        body="A fixed-price discovery build: a working proof of your system, verified before you commit to the full build."
        primaryCta={{ label: 'Start Your Project — $199', href: '/packages/199-quick-start' }}
        secondaryCta={{ label: 'Contact', href: '/contact' }}
      />
    </>
  );
}
