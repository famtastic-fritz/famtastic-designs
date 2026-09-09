import { useEffect, useRef } from 'react';
import { Link } from 'react-router';
import { GROUPS } from './PortalShared.jsx';

export default function PortalNav({
  section,
  go,
  menu,
  setMenu,
  org,
  customer,
  openThreadsCount,
  onSignOut,
}) {
  const toggleRef = useRef(null);
  const drawerRef = useRef(null);
  const closeRef = useRef(null);
  const mobileItems = [
    ['home', 'Home', '⌂'],
    ['projects', 'Projects', '▦'],
    ['messages', 'Messages', '✉'],
    ['billing', 'Billing', '$'],
    ['account', 'Account', '○'],
  ];

  useEffect(() => {
    if (!menu) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    window.requestAnimationFrame(() => closeRef.current?.focus());
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setMenu(false);
        return;
      }
      if (event.key !== 'Tab' || !drawerRef.current) return;
      const focusable = Array.from(
        drawerRef.current.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
      );
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      }
      else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handleKeyDown);
      toggleRef.current?.focus();
    };
  }, [menu, setMenu]);

  return (
    <>
      <button
        ref={toggleRef}
        className="portal-menu-toggle"
        type="button"
        aria-expanded={menu}
        aria-controls="portal-drawer"
        onClick={() => setMenu(!menu)}
      >
        <span aria-hidden="true">☰</span>
        <span>Menu</span>
      </button>

      {menu && (
        <button
          className="portal-scrim"
          type="button"
          aria-label="Close menu"
          onClick={() => setMenu(false)}
        />
      )}

      <aside id="portal-drawer" className="portal-nav" ref={drawerRef}>
        <div className="portal-nav-head">
          <Link className="portal-logo" to="/">
            FAM<span>tastic</span>
          </Link>
          <button
            ref={closeRef}
            type="button"
            aria-label="Close menu"
            onClick={() => setMenu(false)}
          >
            ×
          </button>
        </div>

        <div className="portal-workspace">
          <small>Customer workspace</small>
          <strong>{org?.name || 'Your Workspace'}</strong>
          <span>{customer?.email}</span>
          <em>{org?.role || 'Member'}</em>
        </div>

        <nav aria-label="Customer portal">
          {GROUPS.map(([group, items]) => (
            <section key={group}>
              <h2>{group}</h2>
              {items.map(([id, label]) => {
                const isActive = section === id;
                return (
                  <button
                    key={id}
                    type="button"
                    aria-current={isActive ? 'page' : undefined}
                    className={isActive ? 'active' : ''}
                    onClick={() => go(id)}
                  >
                    <span>{label}</span>
                    {id === 'messages' && openThreadsCount > 0 && (
                      <b aria-label={`${openThreadsCount} open messages`}>
                        {openThreadsCount}
                      </b>
                    )}
                  </button>
                );
              })}
            </section>
          ))}
        </nav>

        <button
          className="portal-signout"
          type="button"
          onClick={onSignOut}
        >
          Sign out
        </button>
      </aside>

      <nav className="portal-mobile-nav" aria-label="Primary customer navigation">
        {mobileItems.map(([id, label, icon]) => (
          <button
            key={id}
            type="button"
            aria-current={section === id ? 'page' : undefined}
            className={section === id ? 'active' : ''}
            onClick={() => go(id)}
          >
            <span aria-hidden="true">{icon}</span>
            <small>{label}</small>
            {id === 'messages' && openThreadsCount > 0 && (
              <b aria-label={`${openThreadsCount} open messages`}>{openThreadsCount}</b>
            )}
          </button>
        ))}
      </nav>
    </>
  );
}
