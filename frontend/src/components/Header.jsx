import { useEffect, useState } from 'react';
import { NavLink, useNavigate } from 'react-router';
import { useUser } from '../auth/UserContext.jsx';
import { getMenus, getNodesRaw } from '../api/drupal.js';
import { transformServiceNode, transformPackageNode } from '../lib/drupalAdapter.js';
import { SiteNavbar } from './v1/index.js';

/**
 * Site header — rebased on the v1 SiteNavbar (Services/Packages dropdowns).
 * Keeps the original data sources: top-level links come from the Drupal main
 * menu via getMenus() (with its stub fallback), dropdown items are live
 * service_page / package_page nodes, and the auth block is unchanged
 * (Client Portal for guests; email + Admin/Logout for authenticated users).
 */
export default function Header() {
  const { user, isAuthenticated, logout } = useUser();
  const navigate = useNavigate();
  const [menuItems, setMenuItems] = useState([]);
  const [services, setServices] = useState([]);
  const [packages, setPackages] = useState([]);

  useEffect(() => {
    let cancelled = false;
    getMenus().then((items) => {
      if (!cancelled) setMenuItems(items);
    });
    getNodesRaw('service_page').then(({ data }) => {
      if (cancelled) return;
      setServices(
        data
          .map((node) => transformServiceNode(node))
          .filter(Boolean)
          .sort((a, b) => a.sortOrder - b.sortOrder)
          .map((s) => ({ slug: s.slug, title: s.title, tagline: s.subheadline })),
      );
    });
    getNodesRaw('package_page').then(({ data }) => {
      if (cancelled) return;
      setPackages(
        data
          .map((node) => transformPackageNode(node))
          .filter(Boolean)
          .sort((a, b) => a.sortOrder - b.sortOrder)
          .map((p) => ({ slug: p.slug, title: p.title, tagline: p.price })),
      );
    });
    return () => {
      cancelled = true;
    };
  }, []);

  function handleLogout() {
    logout();
    navigate('/', { replace: true });
  }

  const authSlot = (
    <>
      {isAuthenticated ? (
        <>
          <NavLink to="/admin" className="v1-nav__link">
            Admin
          </NavLink>
          <span className="v1-meta" title={user?.email ?? ''}>
            {user?.email}
          </span>
          <button type="button" className="v1-btn v1-btn--ghost v1-btn--sm" onClick={handleLogout}>
            Logout
          </button>
        </>
      ) : (
        <NavLink to="/login" className="v1-nav__link">
          Client Portal
        </NavLink>
      )}
    </>
  );

  return <SiteNavbar menuItems={menuItems} services={services} packages={packages} authSlot={authSlot} />;
}
