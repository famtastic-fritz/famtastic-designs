import { FAMCTA } from './index.jsx';

export function PackageCard({ plan, number, onSelect }) {
  const badge = (plan.badge || '').replace(/_/g, ' ').trim();
  return <article className={`fam-ce-package-card${plan.highlighted ? ' fam-ce-package-card--featured' : ''}`} data-fam-instance={plan.id}>
    <div className="fam-ce-catalog-meta"><span aria-hidden="true">{String(number).padStart(2, '0')}</span>{badge && badge.toLowerCase() !== 'none' && <span>{badge}</span>}</div>
    <h3>{plan.title}</h3>
    {plan.price && <p className="fam-ce-catalog-price">{plan.price}</p>}
    {plan.timeline && <p className="fam-ce-note">{plan.timeline}</p>}
    {plan.bestFor && <div className="fam-ce-package-fit"><p className="fam-ce-eyebrow">Best for</p><p>{plan.bestFor}</p></div>}
    {plan.features.length > 0 && <ul className="fam-ce-list">{plan.features.slice(0, 7).map((feature, index) => <li key={index}>{feature}</li>)}</ul>}
    <div className="fam-ce-package-card__action"><p className="fam-ce-note">Final scope confirmed after consultation.</p>
      <FAMCTA href={`/packages/${plan.slug}`} variant={number === 1 ? 'primary' : 'secondary'} onClick={onSelect}>{plan.ctaText || 'View Package'}</FAMCTA>
    </div>
  </article>;
}

export function CapabilityCard({ service, number }) {
  const items = service.features.length ? service.features : service.solutionBullets;
  return <article className="fam-ce-capability" data-fam-instance={service.id}>
    <div className="fam-ce-capability__heading"><span className="fam-ce-capability__number" aria-hidden="true">{String(number).padStart(2, '0')}</span><h3>{service.title}</h3></div>
    <div className="fam-ce-capability__body">
      {service.subheadline && <p>{service.subheadline}</p>}
      {items.length > 0 && <ul className="fam-ce-list">{items.slice(0, 4).map((item, index) => <li key={index}>{item}</li>)}</ul>}
      <FAMCTA href={`/services/${service.slug}`} variant="editorial">{service.ctaText || 'Learn More'}</FAMCTA>
    </div>
  </article>;
}
