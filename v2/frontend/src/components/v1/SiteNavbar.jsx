import { useEffect, useRef, useState } from 'react';
import { Link, NavLink } from 'react-router-dom';

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

  // Only render menu links that are not already covered by the dropdowns or
  // the auth area, so the Drupal menu stays the source of truth without
  // duplicating Services/Packages entries.
  const covered = new Set(['/services', '/packages', '/contact', '/login', '/admin']);
  const extraLinks = menuItems.filter((item) => !covered.has(item.url));

  return (
    <header className="v1-header">
      <div className="v1-header__inner">
        <Link to="/" className="v1-brand" aria-label="FAMtastic Designs — home">
          <span className="v1-brand__mark">FAM</span>
          <span className="v1-brand__rest">tastic&nbsp;Designs</span>
        </Link>

        <nav className="v1-nav" aria-label="Main navigation">
          {extraLinks
            .filter((item) => item.url === '/')
            .map((item) => (
              <NavLink key={item.id} to="/" end className="v1-nav__link">
                {item.title}
              </NavLink>
            ))}
          <Dropdown label="Services" to="/services" basePath="/services" items={services} />
          <Dropdown label="Packages" to="/packages" basePath="/packages" items={packages} />
          {extraLinks
            .filter((item) => item.url !== '/')
            .map((item) =>
              /^https?:\/\//i.test(item.url) ? (
                <a key={item.id} className="v1-nav__link" href={item.url}>
                  {item.title}
                </a>
              ) : (
                <NavLink key={item.id} className="v1-nav__link" to={item.url} end={item.url === '/'}>
                  {item.title}
                </NavLink>
              ),
            )}
          <NavLink to="/work" className="v1-nav__link">
            Work
          </NavLink>
          <NavLink to="/faq" className="v1-nav__link">
            FAQ
          </NavLink>
        </nav>

        <div className="v1-header__actions">
          {authSlot}
          <NavLink to="/contact" className="v1-btn v1-btn--primary v1-btn--sm">
            Book a Call
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
          <NavLink to="/" end className="v1-nav__link">
            Home
          </NavLink>
          <NavLink to="/services" className="v1-nav__link">
            Services
          </NavLink>
          {services.map((item) => (
            <NavLink key={item.slug} to={`/services/${item.slug}`} className="v1-nav__link v1-nav__link--sub">
              {item.title}
            </NavLink>
          ))}
          <NavLink to="/packages" className="v1-nav__link">
            Packages
          </NavLink>
          {packages.map((item) => (
            <NavLink key={item.slug} to={`/packages/${item.slug}`} className="v1-nav__link v1-nav__link--sub">
              {item.title}
            </NavLink>
          ))}
          <NavLink to="/work" className="v1-nav__link">
            Work
          </NavLink>
          <NavLink to="/blog" className="v1-nav__link">
            Blog
          </NavLink>
          <NavLink to="/faq" className="v1-nav__link">
            FAQ
          </NavLink>
          <NavLink to="/about" className="v1-nav__link">
            About
          </NavLink>
          <NavLink to="/contact" className="v1-nav__link">
            Contact
          </NavLink>
        </nav>
      )}
    </header>
  );
}
