import { BRAND_LOGO } from '../lib/brand.js';

/** Preserve the complete master and aspect ratio; no invented compact variants. */
export default function BrandLogo({ placement = 'header', decorative = false }) {
  return (
    <img
      className={`fam-brand-logo fam-brand-logo--${placement}`}
      src={BRAND_LOGO.src}
      alt={decorative ? '' : BRAND_LOGO.alt}
      width={BRAND_LOGO.width}
      height={BRAND_LOGO.height}
      loading={placement === 'footer' ? 'lazy' : 'eager'}
      decoding="async"
    />
  );
}
