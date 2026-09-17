import RelatedEducation from '../RelatedEducation.jsx';
import SignatureHeading from '../SignatureHeading.jsx';
import { FAMPage, FAMPageHero, FAMSection, FAMSurface, FAMStatement, FAMFeatureGrid, FAMCTA, FAMFinale, FAMBrush } from './index.jsx';

/** Read-only projection of the existing package node. No second offer/checkout. */
export default function WebBasicsExperience({ plan, cta, slug }) {
  const included = plan.whatsIncluded.length ? plan.whatsIncluded : plan.features;
  const title = plan.price && plan.title.endsWith(` — ${plan.price}`)
    ? plan.title.slice(0, -(plan.price.length + 3)) : plan.title;
  const price = /^\$\d+(?:\.\d{1,2})?$/.test(plan.price) ? Number(plan.price.slice(1)) : null;
  const dailyCents = price === null ? null : Math.round(price * 100 / 365);
  return <FAMPage id="web-basics" recipe="package-detail">
    <FAMPageHero id="offer" eyebrow="Package / Get online" title={title}
      breadcrumbs={[{ href: '/packages', label: 'Packages' }]}
      lede={plan.subheadline || plan.bestFor} primaryCta={cta}
      secondaryCta={{ label: 'All packages', href: '/packages' }}
      note={plan.timeline}>
      <FAMSurface material="metal" className="fam-ce-offer">
        <p className="fam-ce-eyebrow">A focused first step</p>
        {plan.price && <p className="fam-ce-offer__price">{plan.price}</p>}
        <p className="fam-ce-offer__terms">One-time build. First-year basic hosting included.</p>
        <FAMBrush tone="lime" />
        <h2><SignatureHeading text="Give your business a home." phrases={['Give your business a home.']} /></h2>
        <p className="fam-ce-offer__stamp">More than a profile. A place of your own.</p>
      </FAMSurface>
    </FAMPageHero>

    {included.length > 0 && <FAMSection id="features" number="01" eyebrow="What you get"
      title="Everything you need to get online." signature="get online.">
      <FAMSurface material="glass"><FAMFeatureGrid items={included.map((text, index) => ({ id: `${plan.id}-included-${index + 1}`, text }))} /></FAMSurface>
    </FAMSection>}

    {plan.bestFor && <FAMStatement id="fit" eyebrow="Built for" title="Real work. A real home online." signature="A real home online.">
      <p>{plan.bestFor}</p>
    </FAMStatement>}

    {plan.addons.length > 0 && <FAMSection id="addons" eyebrow="Add-ons" title="Extra support when the project needs more.">
      <div className="fam-ce-addon-grid">{plan.addons.map(addon => <FAMSurface material="glass" key={addon.id}>
        <h3>{addon.name}</h3>{addon.description && <p>{addon.description}</p>}{addon.price && <strong>{addon.price}</strong>}
      </FAMSurface>)}</div>
    </FAMSection>}

    <FAMSection id="pricing" number="02" eyebrow="The price, in perspective" title="A small start. Your next chapter." signature="Your next chapter.">
      <FAMSurface material="spotlight" className="fam-ce-pricing">
        <div className="fam-ce-pricing__comparison">
          {dailyCents !== null ? <><span className="fam-ce-eyebrow">About</span><p className="fam-ce-price-figure">{dailyCents}<span>¢</span></p><span className="fam-ce-eyebrow">a day, averaged over one year</span></> : <p className="fam-ce-price-figure">{plan.price}</p>}
          <p className="fam-ce-note">A cost comparison. Not daily billing.</p>
        </div>
        <div className="fam-ce-pricing__details">
          <h3>{plan.price} for the defined scope.</h3>
          <p>First-year basic managed hosting is included. After that, optional hosting is $9.99/month, only with separate recurring authorization.</p>
          <p>New-domain renewal is a separate annual prepaid charge at the disclosed registrar price. Existing-domain customers receive no new-domain renewal charge.</p>
          <FAMCTA {...cta} />
          <p className="fam-ce-note">Review first. Payment follows your final approval of the completed staging site.</p>
        </div>
      </FAMSurface>
    </FAMSection>

    <RelatedEducation kind="package" slug={slug} presentation="content-experience" />

    <FAMFinale id="finale" title="More than a website… A future." signature="A future."
      body="The listed package price applies to its defined scope. Start with research and a saved brief; website payment is available only from the eligible request in your account."
      primaryCta={cta} secondaryCta={{ label: 'Compare website options', href: '/website-options' }} />
  </FAMPage>;
}
