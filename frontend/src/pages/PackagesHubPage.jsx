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
        primaryCta={{ label: 'See the $199 website offer', href: '/55-cents-a-day-website' }}
        secondaryCta={{ label: 'Find Your Fit', href: '/start' }}
      />

      <Section>
        {packages === null && <div className="v1-loading" role="status">Loading packages…</div>}

        {packages !== null && packages.length === 0 && (
          <div className="v1-empty">
            <strong>Packages are being finalized.</strong>
            <br />
            Pricing details are being published right now — meanwhile,{' '}
            <Link to="/contact#contact-form">send us a message</Link> for a scoped quote.
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
        title="Need a focused first website? Start at $199."
        body="Web Basics is a defined one-page website offer—not the default price for every project. Learn what it includes, then use the assessment when your business needs more."
        primaryCta={{ label: 'Understand the $199 offer', href: '/55-cents-a-day-website' }}
        secondaryCta={{ label: 'Find the right package', href: '/start' }}
      />
    </>
  );
}
