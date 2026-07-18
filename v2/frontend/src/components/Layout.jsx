import { useEffect, useState } from 'react';
import { Outlet, NavLink } from 'react-router-dom';
import Header from './Header.jsx';
import { getMenus, STUB_FLAG } from '../api/drupal.js';

/**
 * App shell: header + dynamic nav (from the Drupal main menu, with a stub
 * fallback when the backend is unreachable) + routed content outlet.
 */
export default function Layout() {
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

  return (
    <div className="layout">
      <Header>
        <nav className="site-nav" aria-label="Main navigation">
          {menuItems.map((item) =>
            /^https?:\/\//i.test(item.url) ? (
              <a key={item.id} className="site-nav__link" href={item.url}>
                {item.title}
              </a>
            ) : (
              <NavLink key={item.id} className="site-nav__link" to={item.url} end={item.url === '/'}>
                {item.title}
              </NavLink>
            ),
          )}
          {isStub && <span className="stub-badge">stub nav</span>}
        </nav>
      </Header>

      <main className="layout__main">
        <Outlet />
      </main>

      <footer className="layout__footer">
        FAMtastic Designs — headless Drupal 11 + React 18
      </footer>
    </div>
  );
}
