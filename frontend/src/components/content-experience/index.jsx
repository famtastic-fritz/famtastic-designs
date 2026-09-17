import { Link } from 'react-router';
import SignatureHeading from '../SignatureHeading.jsx';
import FAMCrown from '../FAMCrown.jsx';
import { CONTENT_MATERIALS, CONTENT_RECIPES, CONTENT_SYSTEM_VERSION, safeContentHref } from './recipes.js';
import './content-experience.css';

export { FAMCrown };

/** @param {{id:string,recipe:string,children:React.ReactNode}} props */
export function FAMPage({ id, recipe, children }) {
  const definition = CONTENT_RECIPES[recipe];
  return <article id={id} className="fam-page" data-fam-recipe={recipe}
    data-fam-system={CONTENT_SYSTEM_VERSION} data-fam-intensity={definition?.intensity ?? 0}>{children}</article>;
}

/** Decorative CSS stroke, never independent logo artwork. */
export function FAMBrush({ tone = 'identity' }) {
  const safeTone = ['identity', 'lime', 'white'].includes(tone) ? tone : 'identity';
  return <span className={`fam-ce-brush fam-ce-brush--${safeTone}`} aria-hidden="true" />;
}

/** Navigation only; preserves link semantics and the caller's approved destination. */
export function FAMCTA({ href, children, label, variant = 'primary', className = '' }) {
  const destination = safeContentHref(href);
  const kind = ['primary', 'secondary', 'editorial'].includes(variant) ? variant : 'secondary';
  const contents = <><span>{children || label}</span><span className="fam-ce-cta__arrow" aria-hidden="true">↗</span></>;
  if (!destination) return <span>{children || label}</span>;
  const props = { className: `fam-ce-cta fam-ce-cta--${kind} ${className}`.trim() };
  return destination.startsWith('/') ? <Link {...props} to={destination}>{contents}</Link> : <a {...props} href={destination}>{contents}</a>;
}

export function FAMSurface({ material = 'glass', className = '', children }) {
  const safeMaterial = CONTENT_MATERIALS.includes(material) ? material : 'obsidian';
  return <div className={`fam-ce-surface fam-ce-surface--${safeMaterial} ${className}`.trim()} data-fam-material={safeMaterial}>{children}</div>;
}

export function FAMSectionHeader({ id, eyebrow, title, signature, number }) {
  return <header className="fam-ce-section-header">
    {eyebrow && <p className="fam-ce-eyebrow">{number && <span className="fam-ce-index" aria-hidden="true">{number} / </span>}{eyebrow}</p>}
    <h2 id={id}><SignatureHeading text={title} phrases={signature ? [signature] : []} /></h2>
  </header>;
}

export function FAMSection({ id, eyebrow, title, signature, number, className = '', children }) {
  return <section id={id} className={`fam-ce-section ${className}`.trim()} aria-labelledby={`${id}-title`}>
    <div className="fam-ce-container">
      <FAMSectionHeader id={`${id}-title`} eyebrow={eyebrow} title={title} signature={signature} number={number} />
      {children}
    </div>
  </section>;
}

/** Native text hero; complementary slot is supplied by the page recipe. */
export function FAMPageHero({ id, eyebrow, title, lede, primaryCta, secondaryCta, note, breadcrumbs = [], children }) {
  return <header id={id} className="fam-ce-hero">
    <div className="fam-ce-hero__atmosphere" aria-hidden="true"><FAMBrush /></div>
    <div className="fam-ce-container">
      {breadcrumbs.length > 0 && <nav className="fam-ce-breadcrumb" aria-label="Breadcrumb">{breadcrumbs.map(crumb => <span key={crumb.href}><Link to={crumb.href}>{crumb.label}</Link><span aria-hidden="true"> / </span></span>)}<span aria-current="page">{title}</span></nav>}
      <div className="fam-ce-hero__layout">
        <div className="fam-ce-hero__copy">
          <p className="fam-ce-eyebrow">{eyebrow}</p>
          <h1>{title}</h1>
          <p className="fam-ce-lede">{lede}</p>
          <div className="fam-ce-actions"><FAMCTA {...primaryCta} /><FAMCTA {...secondaryCta} variant="secondary" /></div>
          {note && <p className="fam-ce-note">{note}</p>}
        </div>
        {children}
      </div>
    </div>
  </header>;
}

export function FAMFeatureGrid({ items }) {
  return <ol className="fam-ce-features">
    {items.map((item, index) => <li key={item.id} data-fam-instance={item.id}>
      <span className="fam-ce-feature-number" aria-hidden="true">{String(index + 1).padStart(2, '0')}</span>
      <h3>{item.text}</h3>
    </li>)}
  </ol>;
}

export function FAMStatement({ id, eyebrow, title, signature, children }) {
  return <section id={id} className="fam-ce-statement" aria-labelledby={`${id}-title`}>
    <div className="fam-ce-container fam-ce-statement__layout">
      <div className="fam-ce-statement__mark"><FAMCrown intensity="hero" /><span className="fam-ce-eyebrow">{eyebrow}</span></div>
      <div><h2 id={`${id}-title`}><SignatureHeading text={title} phrases={signature ? [signature] : []} /></h2>{children}</div>
    </div>
  </section>;
}

/** Existing Drupal guide content only; no invented proof or metrics. */
export function FAMInsightCard({ post, number }) {
  return <article className="fam-ce-insight" data-fam-instance={`guide-${post.id}`}>
    <div className="fam-ce-insight__meta"><span>{post.series || 'FAMtastic guide'}</span><span aria-hidden="true">{number}</span></div>
    <h3>{post.title}</h3><p>{post.summary}</p>
    <FAMCTA href={`/blog/${post.slug}`} variant="editorial">Read the guide<span className="fam-ce-sr-only">: {post.title}</span></FAMCTA>
  </article>;
}

export function FAMFinale({ id, title, signature, body, primaryCta, secondaryCta }) {
  return <section id={id} className="fam-ce-finale" aria-labelledby={`${id}-title`}>
    <div className="fam-ce-container">
      <FAMCrown intensity="hero" />
      <h2 id={`${id}-title`}><SignatureHeading text={title} phrases={signature ? [signature] : []} /></h2>
      <p>{body}</p>
      <div className="fam-ce-actions"><FAMCTA {...primaryCta} /><FAMCTA {...secondaryCta} variant="editorial" /></div>
    </div>
  </section>;
}
