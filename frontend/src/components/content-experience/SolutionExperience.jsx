import { FAQAccordion } from '../v1/index.js';
import RelatedEducation from '../RelatedEducation.jsx';
import { FAMPage, FAMPageHero, FAMSection, FAMSurface, FAMFeatureGrid, FAMFinale } from './index.jsx';

/** Existing service data, expressed as a system brief. No inferred outcomes. */
export default function SolutionExperience({ service, slug, cta, children }) {
  const sections = [
    (service.painPointsTitle || service.painPoints.length > 0) && ['problem', 'The challenge'],
    (service.solutionTitle || service.solutionBullets.length > 0) && ['system', 'The system'],
    (service.processTitle || service.processSteps.length > 0) && ['process', 'How it works'],
    (service.featuresTitle || service.features.length > 0) && ['deliverables', 'Deliverables'],
    service.faqs.length > 0 && ['faq', 'Your questions'],
  ].filter(Boolean);
  return <FAMPage id={`solution-${slug}`} recipe="solution-detail">
    <FAMPageHero id="intro" eyebrow="Solution / System brief" title={service.headline} lede={service.subheadline}
      breadcrumbs={[{ href: '/services', label: 'Solutions' }]} primaryCta={cta}
      secondaryCta={{ label: 'All services', href: '/services' }}>
      {sections.length > 0 && <FAMSurface material="technical" className="fam-ce-system-index">
        <p className="fam-ce-eyebrow">Explore this system</p>
        <nav aria-label="On this page"><ol>{sections.map(([id, label], index) => <li key={id}>
          <a href={`#${id}`}><span aria-hidden="true">{String(index + 1).padStart(2, '0')}</span>{label}<span aria-hidden="true">↓</span></a>
        </li>)}</ol></nav>
      </FAMSurface>}
    </FAMPageHero>

    {(service.painPointsTitle || service.painPoints.length > 0) && <FAMSection id="problem" eyebrow="The challenge" title={service.painPointsTitle || 'The Challenge'}>
      <ol className="fam-ce-challenges">{service.painPoints.map((point, index) => <li key={index}>
        <span aria-hidden="true">{String(index + 1).padStart(2, '0')}</span><p>{point}</p>
      </li>)}</ol>
    </FAMSection>}

    {(service.solutionTitle || service.solutionBullets.length > 0) && <FAMSection id="system" eyebrow="The solution" title={service.solutionTitle || "Here's What Changes"} className="fam-ce-system-section">
      <FAMSurface material="glass" className="fam-ce-system-body"><ul className="fam-ce-list">{service.solutionBullets.map((item, index) => <li key={index}>{item}</li>)}</ul></FAMSurface>
    </FAMSection>}

    {(service.processTitle || service.processSteps.length > 0) && <FAMSection id="process" eyebrow="Process" title={service.processTitle || 'How It Works'}>
      <ol className="fam-ce-process">{service.processSteps.map(step => <li key={step.id ?? step.number}>
        <span className="fam-ce-process__number">{step.number}</span><h3>{step.title}</h3>{step.body && <p>{step.body}</p>}
      </li>)}</ol>
    </FAMSection>}

    {service.testimonial.quote && <FAMSection id="proof" eyebrow="Client perspective" title="In their words.">
      <blockquote className="fam-ce-quote"><p>{service.testimonial.quote}</p>{service.testimonial.attribution && <cite>{service.testimonial.attribution}</cite>}</blockquote>
    </FAMSection>}

    {(service.featuresTitle || service.features.length > 0) && <FAMSection id="deliverables" eyebrow="Deliverables" title={service.featuresTitle || "What's Included"}>
      <FAMSurface material="technical"><FAMFeatureGrid items={service.features.map((text, index) => ({ id: `${service.id}-feature-${index + 1}`, text }))} /></FAMSurface>
    </FAMSection>}

    {service.faqs.length > 0 && <FAMSection id="faq" eyebrow="FAQ" title={service.faqTitle || 'Frequently Asked Questions'}>
      <FAQAccordion items={service.faqs} respectReducedMotion />
    </FAMSection>}
    <RelatedEducation kind="service" slug={slug} presentation="content-experience" />
    <FAMSection id="start" eyebrow="Start" title="Start with this service" signature="this service"
      intro="Answer a few practical questions, then save a research summary once the server confirms it. Your account is where the full brief and next eligible step continue.">
      {children}
    </FAMSection>
    <FAMFinale id="finale" title="Ready to put this system to work?" signature="to work?"
      primaryCta={cta} secondaryCta={{ label: 'Compare website options', href: '/website-options' }} />
  </FAMPage>;
}
