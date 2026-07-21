import { Link } from 'react-router-dom';
import ParticleField from './ParticleField.jsx';
import { FadeUp } from './motion.jsx';

/** Internal links use <Link>, external ones a plain anchor. */
export function CtaLink({ href = '/contact', label, kind = 'primary', className = '' }) {
  const cls = `v1-btn v1-btn--${kind} ${className}`.trim();
  if (/^(https?:|mailto:|tel:)/i.test(href)) {
    return (
      <a className={cls} href={href}>
        {label}
      </a>
    );
  }
  return (
    <Link className={cls} to={href}>
      {label}
    </Link>
  );
}

/**
 * v1 hero: eyebrow pill → oversized Space Grotesk headline → lede → pill CTAs
 * → optional proof-bullet grid. `particles` layers the canvas neural-network
 * field behind the content (homepage hero), matching the v1 radial-glow
 * backdrop with extra depth.
 */
export default function Hero({
  eyebrow,
  title,
  accent,
  lede,
  primaryCta,
  secondaryCta,
  bullets = [],
  particles = false,
  children,
}) {
  return (
    <section className={`v1-hero${particles ? ' v1-hero--particles' : ''}`}>
      {particles && <ParticleField className="v1-hero__particles" />}
      <div className="v1-container v1-hero__inner">
        {eyebrow && (
          <FadeUp>
            <span className="v1-eyebrow v1-eyebrow--pill">{eyebrow}</span>
          </FadeUp>
        )}
        <FadeUp delay={0.08}>
          <h1 className="v1-hero__title">
            {title} {accent && <span className="v1-accent">{accent}</span>}
          </h1>
        </FadeUp>
        {lede && (
          <FadeUp delay={0.16}>
            <p className="v1-hero__lede">{lede}</p>
          </FadeUp>
        )}
        {(primaryCta || secondaryCta) && (
          <FadeUp delay={0.24} className="v1-hero__actions">
            {primaryCta && <CtaLink kind="primary" href={primaryCta.href} label={primaryCta.label} />}
            {secondaryCta && <CtaLink kind="ghost" href={secondaryCta.href} label={secondaryCta.label} />}
          </FadeUp>
        )}
        {bullets.length > 0 && (
          <FadeUp delay={0.32} className="v1-hero__bullets">
            {bullets.map((bullet) => (
              <div key={bullet} className="v1-hero__bullet">
                {bullet}
              </div>
            ))}
          </FadeUp>
        )}
        {children}
      </div>
    </section>
  );
}
