import { useEffect, useState } from 'react';
import { Outlet } from 'react-router';
import Header from './Header.jsx';
import { getNodesRaw } from '../api/drupal.js';
import { transformServiceNode, transformPackageNode } from '../lib/drupalAdapter.js';
import { SiteFooter } from './v1/index.js';

/**
 * App shell: v1 header (Services/Packages dropdowns + auth block) and the
 * v1 link-column SiteFooter around the routed content outlet. The main area
 * is full-width — each page centers its own v1-container sections.
 */
export default function Layout() {
  const [services, setServices] = useState([]);
  const [packages, setPackages] = useState([]);

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('service_page').then(({ data }) => {
      if (cancelled) return;
      setServices(
        data
          .map((node) => transformServiceNode(node))
          .filter(Boolean)
          .sort((a, b) => a.sortOrder - b.sortOrder)
          .map((s) => ({ slug: s.slug, title: s.title })),
      );
    });
    getNodesRaw('package_page').then(({ data }) => {
      if (cancelled) return;
      setPackages(
        data
          .map((node) => transformPackageNode(node))
          .filter(Boolean)
          .sort((a, b) => a.sortOrder - b.sortOrder)
          .map((p) => ({ slug: p.slug, title: p.title })),
      );
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="layout">
      <a className="skip-link" href="#main-content">Skip to main content</a>
      <Header />

      <main id="main-content" className="layout__main layout__main--v1" tabIndex="-1">
        <Outlet />
      </main>

      <SiteFooter services={services} packages={packages} />
    </div>
  );
}
