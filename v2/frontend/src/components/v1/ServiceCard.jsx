import { Link } from 'react-router-dom';

/**
 * v1 service card — dark #111 card, lime dot deliverables, "Learn more →"
 * (port of the v1 homepage services teaser + /services listing cards).
 */
export default function ServiceCard({ service, ctaLabel = 'Learn More' }) {
  const deliverables = (service.features?.length ? service.features : service.solutionBullets) ?? [];
  return (
    <article className="v1-card v1-service-card">
      <span className="v1-card__kicker">Service</span>
      <h3 className="v1-card__title">{service.title}</h3>
      {service.subheadline && <p className="v1-card__text">{service.subheadline}</p>}
      {deliverables.length > 0 && (
        <ul className="v1-dot-list">
          {deliverables.slice(0, 4).map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      )}
      <Link to={`/services/${service.slug}`} className="v1-card__cta">
        {service.ctaText || ctaLabel} →
      </Link>
    </article>
  );
}
