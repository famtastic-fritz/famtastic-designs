import { Link } from 'react-router';

/**
 * v1 pricing card — package name, big price, description, "Best for",
 * lime-dot feature list, white pill CTA. Highlighted packages get the
 * lime-tinted border/background treatment from the v1 offer ladder.
 */
export default function PricingCard({ plan }) {
  const badgeLabel = (plan.badge || '').replace(/_/g, ' ').trim();
  const showBadge = badgeLabel && badgeLabel.toLowerCase() !== 'none';
  const features = plan.features ?? [];

  return (
    <article className={`v1-card v1-pricing-card${plan.highlighted ? ' v1-pricing-card--highlight' : ''}`}>
      {showBadge && <span className="v1-badge">{badgeLabel}</span>}
      <p className="v1-pricing-card__name">{plan.title}</p>
      {plan.price && <p className="v1-pricing-card__price">{plan.price}</p>}
      {plan.timeline && <p className="v1-pricing-card__timeline">{plan.timeline}</p>}
      {plan.bestFor && (
        <>
          <p className="v1-pricing-card__label">Best for</p>
          <p className="v1-card__text">{plan.bestFor}</p>
        </>
      )}
      {features.length > 0 && (
        <ul className="v1-dot-list">
          {features.slice(0, 7).map((feature) => (
            <li key={feature}>{feature}</li>
          ))}
        </ul>
      )}
      <p className="v1-pricing-card__fine">Final scope confirmed after consultation.</p>
      <Link to={`/packages/${plan.slug}`} className="v1-btn v1-btn--light v1-pricing-card__cta">
        {plan.ctaText || 'View Package'}
      </Link>
    </article>
  );
}
