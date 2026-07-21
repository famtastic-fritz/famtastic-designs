import { Outlet } from 'react-router-dom';
import Header from './Header.jsx';

/**
 * App shell: header (self-contained — it fetches the Drupal main menu and
 * renders the nav + Book a Call CTA itself) plus the routed content outlet.
 */
export default function Layout() {
  return (
    <div className="layout">
      <Header />

      <main className="layout__main">
        <Outlet />
      </main>

      <footer className="layout__footer">
        FAMtastic Designs — headless Drupal 11 + React 18
      </footer>
    </div>
  );
}
