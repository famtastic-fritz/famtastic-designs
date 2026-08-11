import { useEffect, useRef, useState } from 'react';
import { Link, NavLink } from 'react-router';

/**
 * v1 site navbar — sticky blurred bar, FAM/tastic Designs wordmark, top-level
 * links from the Drupal main menu, and hover/focus dropdowns for Services and
 * Packages (populated live from JSON:API). The auth area is supplied by the
 * caller (`authSlot`) so Header keeps ownership of the session UI.
 */

function Dropdown({ label, to, items, basePath }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const closeTimer = useRef(null);

  function show() {
    window.clearTimeout(closeTimer.current);
    setOpen(true);
  }
  function hide() {
    closeTimer.current = window.setTimeout(() => setOpen(false), 120);
  }

  useEffect(() => {
    function onKey(event) {
      if (event.key === 'Escape') setOpen(false);
    }
    function onClickOutside(event) {
      if (ref.current && !ref.current.contains(event.target)) setOpen(false);
    }
    document.addEventListener('keydown', onKey);
    document.addEventListener('pointerdown', onClickOutside);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('pointerdown', onClickOutside);
      window.clearTimeout(closeTimer.current);
    };
  }, []);

  return (
    <div
      ref={ref}
      className={`v1-nav__dropdown${open ? ' v1-nav__dropdown--open' : ''}`}
      onPointerEnter={show}
      onPointerLeave={hide}
    >
      <button
        type="button"
        className="v1-nav__link v1-nav__dropdown-toggle"
        aria-expanded={open}
        aria-haspopup="true"
        onClick={() => setOpen((prev) => !prev)}
      >
        {label}
        <span className="v1-nav__caret" aria-hidden="true">
          ▾
        </span>
      </button>
      {open && (
        <div className="v1-nav__menu" role="menu">
          <NavLink to={to} className="v1-nav__menu-item v1-nav__menu-item--all" role="menuitem" onClick={() => setOpen(false)}>
            All {label}
          </NavLink>
          {items.map((item) => (
            <NavLink
              key={item.slug}
              to={`${basePath}/${item.slug}`}
              className="v1-nav__menu-item"
              role="menuitem"
              onClick={() => setOpen(false)}
            >
              <span className="v1-nav__menu-title">{item.title}</span>
              {item.tagline && <span className="v1-nav__menu-tagline">{item.tagline}</span>}
            </NavLink>
          ))}
        </div>
      )}
    </div>
  );
}

export default function SiteNavbar({ menuItems = [], services = [], packages = [], authSlot = null }) {
  const [mobileOpen, setMobileOpen] = useState(false);

  // Normalize menu items from any source (Drupal menu or stub fallback):
  // drop "Pages" entries, rename "Articles" → "Blog", and map legacy
  // /content/* URLs to their clean routes.
  const normalized = menuItems
    .map((item) => {
      if (item.title === 'Pages' || item.url === '/content/page') return null;
      if (item.title === 'Articles' || item.url === '/content/article') {
        return { ...item, title: 'Blog', url: '/blog' };
      }
      return item;
    })
    .filter(Boolean);

  // Drupal owns top-level presence, label, and order. Service/package child
  // links are represented by the richer dropdown data and are not duplicated.
  const primaryItems = normalized.filter((item) => {
    if (item.parent) return false;
    if (item.url.startsWith('/services/') || item.url.startsWith('/packages/')) return false;
    return !['/login', '/admin'].includes(item.url);
  });

  function renderPrimaryItem(item, mobile = false) {
    if (item.url === '/services') {
      if (mobile) return <NavLink key={item.id} to="/services" className="v1-nav__link">{item.title}</NavLink>;
      return <Dropdown key={item.id} label={item.title} to="/services" basePath="/services" items={services} />;
    }
    if (item.url === '/packages') {
      if (mobile) return <NavLink key={item.id} to="/packages" className="v1-nav__link">{item.title}</NavLink>;
      return <Dropdown key={item.id} label={item.title} to="/packages" basePath="/packages" items={packages} />;
    }
    if (/^https?:\/\//i.test(item.url)) {
      return <a key={item.id} className="v1-nav__link" href={item.url}>{item.title}</a>;
    }
    const target = item.url === '/contact' ? '/contact#contact-form' : item.url;
    return <NavLink key={item.id} to={target} end={item.url === '/'} className="v1-nav__link">{item.title}</NavLink>;
  }

  return (
    <header className="v1-header">
      <div className="v1-header__inner">
        <Link to="/" className="v1-brand" aria-label="FAMtastic Designs — home">
          <span className="v1-brand__mark">FAM</span>
          <span className="v1-brand__rest">tastic&nbsp;Designs</span>
        </Link>

        <nav className="v1-nav" aria-label="Main navigation">
          {primaryItems.map((item) => renderPrimaryItem(item))}
        </nav>

        <div className="v1-header__actions">
          {authSlot}
          <NavLink to="/contact#project-fit" className="v1-btn v1-btn--primary v1-btn--sm v1-header__primary-cta">
            Start a Project
          </NavLink>
          <button
            type="button"
            className="v1-header__burger"
            aria-label="Toggle navigation"
            aria-expanded={mobileOpen}
            onClick={() => setMobileOpen((prev) => !prev)}
          >
            {mobileOpen ? '✕' : '☰'}
          </button>
        </div>
      </div>

      {mobileOpen && (
        <nav className="v1-nav-mobile" aria-label="Mobile navigation" onClick={() => setMobileOpen(false)}>
          <NavLink to="/contact#project-fit" className="v1-btn v1-btn--primary v1-nav-mobile__primary">
            Start a Project
          </NavLink>
          {primaryItems.flatMap((item) => {
            const links = [renderPrimaryItem(item, true)];
            if (item.url === '/services') {
              links.push(...services.map((service) => (
                <NavLink key={`service-${service.slug}`} to={`/services/${service.slug}`} className="v1-nav__link v1-nav__link--sub">
                  {service.title}
                </NavLink>
              )));
            }
            if (item.url === '/packages') {
              links.push(...packages.map((pkg) => (
                <NavLink key={`package-${pkg.slug}`} to={`/packages/${pkg.slug}`} className="v1-nav__link v1-nav__link--sub">
                  {pkg.title}
                </NavLink>
              )));
            }
            return links;
          })}
        </nav>
      )}
    </header>
  );
}
