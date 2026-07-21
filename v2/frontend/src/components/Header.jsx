import { useEffect, useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useUser } from '../auth/UserContext.jsx';
import { getMenus, STUB_FLAG } from '../api/drupal.js';

/**
 * Site header with FAMtastic branding. The main nav is fetched dynamically
 * from the Drupal main menu via getMenus() (with a stub fallback while the
 * backend is unreachable). A persistent "Book a Call" CTA links to /contact,
 * and the right-hand auth area shows Login for guests or the signed-in email
 * plus Admin/Logout actions for authenticated users.
 */
export default function Header() {
  const { user, isAuthenticated, logout } = useUser();
  const navigate = useNavigate();
  const [menuItems, setMenuItems] = useState([]);

  useEffect(() => {
    let cancelled = false;
    getMenus().then((items) => {
      if (!cancelled) setMenuItems(items);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  const isStub = menuItems.some((item) => item[STUB_FLAG]);

  function handleLogout() {
    logout();
    navigate('/', { replace: true });
  }

  return (
    <header className="site-header">
      <div className="site-header__inner">
        <Link to="/" className="brand" aria-label="FAMtastic Designs — home">
          <span className="brand__mark">FAM</span>
          <span>tastic</span>
          <span className="brand__tag">Designs</span>
        </Link>

        <nav className="site-nav" aria-label="Main navigation">
          {menuItems.map((item) =>
            /^https?:\/\//i.test(item.url) ? (
              <a key={item.id} className="site-nav__link" href={item.url}>
                {item.title}
              </a>
            ) : (
              <NavLink
                key={item.id}
                className="site-nav__link"
                to={item.url}
                end={item.url === '/'}
              >
                {item.title}
              </NavLink>
            ),
          )}
          {isStub && <span className="stub-badge">stub nav</span>}
        </nav>

        <div className="auth-area">
          <NavLink to="/contact" className="btn btn--lime btn--sm header-cta">
            Book a Call
          </NavLink>
          {isAuthenticated ? (
            <>
              <NavLink to="/admin" className="site-nav__link">
                Admin
              </NavLink>
              <span className="auth-area__user" title={user?.email ?? ''}>
                {user?.email}
              </span>
              <button type="button" className="btn btn--ghost btn--sm" onClick={handleLogout}>
                Logout
              </button>
            </>
          ) : (
            <NavLink to="/login" className="site-nav__link">
              Login
            </NavLink>
          )}
        </div>
      </div>
    </header>
  );
}
