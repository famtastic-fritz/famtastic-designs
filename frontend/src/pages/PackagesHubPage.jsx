import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { transformPackageNode } from '../lib/drupalAdapter.js';
import { FAMPage, FAMPageHero, FAMSection, FAMFinale } from '../components/content-experience/index.jsx';
import { PackageCard } from '../components/content-experience/CatalogCards.jsx';
import { trackEvent } from '../lib/googleAnalytics.js';
import { WEB_BASICS } from '../lib/webBasicsOffer.js';

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
    <FAMPage id="packages" recipe="packages-hub">
      <FAMPageHero id="intro"
        eyebrow="Packages"
        title="One clear starting point for each kind of need."
        signature="starting point"
        lede={`${WEB_BASICS.shortLabel} gets a business online. Business Website adds standard pages. Custom Website adds original discovery and design. Growth, campaign, AI, and care systems solve distinct operational needs. Intake confirms the right fit.`}
        primaryCta={{ label: 'See the $199 website offer', href: '/55-cents-a-day-website' }}
        secondaryCta={{ label: 'Find Your Fit', href: '/start' }}
      />

      <FAMSection id="comparison" eyebrow="Compare the scope" title="A starting point. Not a one-size-fits-all." signature="A starting point.">
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
          <div className="fam-ce-package-catalog">
            {packages.map((plan, index) => (
                <PackageCard key={plan.id} plan={plan} number={index + 1}
                  onSelect={() =>
                    trackEvent('select_item', {
                      item_id: plan.slug || plan.id,
                      item_name: plan.title,
                      item_category: 'package',
                      value: Number(String(plan.price).replace(/[^0-9.]/g, '')) || undefined,
                    })
                  }
                />
            ))}
          </div>
        )}
      </FAMSection>

      <FAMFinale id="finale"
        title="Need a focused first website? Start at $199."
        body={`The ${WEB_BASICS.title} is a defined one-page website offer - not the default price for every project. Learn what it includes, then use the assessment when your business needs more.`}
        primaryCta={{ label: 'Understand the $199 offer', href: '/55-cents-a-day-website' }}
        secondaryCta={{ label: 'Find the right package', href: '/start' }}
      />
    </FAMPage>
  );
}
