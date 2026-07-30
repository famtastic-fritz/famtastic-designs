import { CtaLink } from './Hero.jsx';
import { FadeUp } from './motion.jsx';

/**
 * v1 CTA banner — lime-tinted rounded panel, centered headline + pill CTAs
 * (port of the v1 final-CTA section: border-[#79FF00]/18 bg-[#79FF00]/10).
 */
export default function CTABanner({ title, bodyHtml, body, primaryCta, secondaryCta }) {
  return (
    <section className="v1-section">
      <div className="v1-container">
        <FadeUp className="v1-cta-banner">
          <h2 className="v1-cta-banner__title">{title}</h2>
          {bodyHtml && (
            <div className="v1-cta-banner__body" dangerouslySetInnerHTML={{ __html: bodyHtml }} />
          )}
          {!bodyHtml && body && <p className="v1-cta-banner__body">{body}</p>}
          {(primaryCta || secondaryCta) && (
            <div className="v1-cta-banner__actions">
              {primaryCta && <CtaLink kind="primary" href={primaryCta.href} label={primaryCta.label} />}
              {secondaryCta && <CtaLink kind="ghost" href={secondaryCta.href} label={secondaryCta.label} />}
            </div>
          )}
        </FadeUp>
      </div>
    </section>
  );
}
