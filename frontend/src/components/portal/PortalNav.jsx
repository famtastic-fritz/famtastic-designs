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
  return (
    <>
      <button
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

      <aside id="portal-drawer" className="portal-nav">
        <div className="portal-nav-head">
          <Link className="portal-logo" to="/">
            FAM<span>tastic</span>
          </Link>
          <button
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
    </>
  );
}
