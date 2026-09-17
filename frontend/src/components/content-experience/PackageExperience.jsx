import RelatedEducation from '../RelatedEducation.jsx';
import { FAMPage, FAMPageHero, FAMSection, FAMSurface, FAMStatement, FAMFeatureGrid, FAMFinale, FAMBrush } from './index.jsx';

/** Non-Web-Basics packages keep their own CMS scope, price and billing language. */
export default function PackageExperience({ plan, cta, slug }) {
  const included = plan.whatsIncluded.length ? plan.whatsIncluded : plan.features;
  const title = plan.price && plan.title.endsWith(` — ${plan.price}`)
    ? plan.title.slice(0, -(plan.price.length + 3)) : plan.title;
  return <FAMPage id={`package-${slug}`} recipe="package-detail">
    <FAMPageHero id="offer" eyebrow="Package / Your next step" title={title}
      breadcrumbs={[{ href: '/packages', label: 'Packages' }]}
      lede={plan.subheadline || plan.bestFor} primaryCta={cta}
      secondaryCta={{ label: 'All packages', href: '/packages' }} note={plan.timeline}>
      <FAMSurface material="metal" className="fam-ce-offer fam-ce-offer--scope">
        <p className="fam-ce-eyebrow">The defined scope</p>
        {plan.price && <p className={`fam-ce-offer__price${plan.price.length > 8 ? ' fam-ce-offer__price--long' : ''}`}>{plan.price}</p>}
        <FAMBrush tone="lime" />
        <p className="fam-ce-offer__terms">The listed package price applies to its defined scope.</p>
        <p className="fam-ce-offer__stamp">Final scope confirmed after consultation.</p>
      </FAMSurface>
    </FAMPageHero>
    {included.length > 0 && <FAMSection id="features" number="01" eyebrow="Deliverables" title="What's included.">
      <FAMSurface material="glass"><FAMFeatureGrid items={included.map((text, index) => ({ id: `${plan.id}-included-${index + 1}`, text }))} /></FAMSurface>
    </FAMSection>}
    {plan.bestFor && <FAMStatement id="fit" eyebrow="Best for" title="Find your next step." signature="next step."><p>{plan.bestFor}</p></FAMStatement>}
    {plan.addons.length > 0 && <FAMSection id="addons" eyebrow="Add-ons" title="Extra support when the project needs more.">
      <div className="fam-ce-addon-grid">{plan.addons.map(addon => <FAMSurface material="glass" key={addon.id}>
        <h3>{addon.name}</h3>{addon.description && <p>{addon.description}</p>}{addon.price && <strong>{addon.price}</strong>}
      </FAMSurface>)}</div>
    </FAMSection>}
    <RelatedEducation kind="package" slug={slug} presentation="content-experience" />
    <FAMFinale id="finale" title="Ready to get started?" signature="get started?"
      body="The listed package price applies to its defined scope. Start with research and a saved brief; website payment is available only from the eligible request in your account."
      primaryCta={cta} secondaryCta={{ label: 'Compare website options', href: '/website-options' }} />
  </FAMPage>;
}
