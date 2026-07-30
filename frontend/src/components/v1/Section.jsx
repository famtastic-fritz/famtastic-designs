import { Link } from 'react-router-dom';
import { FadeUp } from './motion.jsx';

/**
 * v1 section scaffolding: a max-width container with the signature
 * eyebrow → headline → intro header, optionally with an "see all →" link
 * on the right (v1's flex items-end justify-between pattern).
 */
export function Section({ id, eyebrow, title, intro, link, children, className = '' }) {
  return (
    <section id={id} className={`v1-section ${className}`.trim()}>
      <div className="v1-container">
        {(eyebrow || title || intro || link) && (
          <FadeUp className="v1-section__head">
            <div>
              {eyebrow && <p className="v1-eyebrow">{eyebrow}</p>}
              {title && <h2 className="v1-section__title">{title}</h2>}
              {intro && <p className="v1-section__intro">{intro}</p>}
            </div>
            {link && (
              <Link to={link.href} className="v1-section__link">
                {link.label} →
              </Link>
            )}
          </FadeUp>
        )}
        {children}
      </div>
    </section>
  );
}

/** Slim strip section (stats bar, platform strip) with a tinted border band. */
export function Strip({ children, label }) {
  return (
    <section className="v1-strip" aria-label={label}>
      <div className="v1-container v1-strip__inner">{children}</div>
    </section>
  );
}
