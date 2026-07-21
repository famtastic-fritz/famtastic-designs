import { Link } from 'react-router-dom';

const CONTACT_EMAIL = 'hello@famtasticdesigns.com';

/**
 * v1 site footer — three link columns (Explore / Company / Contact) over a
 * slightly lifted dark surface, brand blurb + pill tags, copyright line.
 * Service/package columns are populated live from JSON:API when available.
 */
export default function SiteFooter({ services = [], packages = [] }) {
  const year = new Date().getFullYear();

  return (
    <footer className="v1-footer">
      <div className="v1-container v1-footer__grid">
        <div className="v1-footer__brand">
          <p className="v1-footer__name">
            <span className="v1-brand__mark">FAM</span>tastic Designs
          </p>
          <p className="v1-footer__blurb">
            Intelligent websites, AI systems, lead capture, and automation for growing businesses —
            engineered for your business, not a template.
          </p>
          <div className="v1-footer__tags">
            <span>Mobile-first</span>
            <span>Lead-focused</span>
            <span>AI-ready</span>
          </div>
        </div>

        <nav className="v1-footer__col" aria-label="Services">
          <p className="v1-footer__heading">Services</p>
          {services.slice(0, 6).map((item) => (
            <Link key={item.slug} to={`/services/${item.slug}`}>
              {item.title}
            </Link>
          ))}
          <Link to="/services">All services →</Link>
        </nav>

        <nav className="v1-footer__col" aria-label="Packages">
          <p className="v1-footer__heading">Packages</p>
          {packages.slice(0, 5).map((item) => (
            <Link key={item.slug} to={`/packages/${item.slug}`}>
              {item.title}
            </Link>
          ))}
          <Link to="/packages">All packages →</Link>
        </nav>

        <nav className="v1-footer__col" aria-label="Company and contact">
          <p className="v1-footer__heading">Company</p>
          <Link to="/about">About</Link>
          <Link to="/work">Work</Link>
          <Link to="/blog">Blog</Link>
          <Link to="/faq">FAQ</Link>
          <Link to="/contact">Contact</Link>
          <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>
        </nav>
      </div>

      <div className="v1-container v1-footer__bottom">
        <p>© {year} FAMtastic Designs. All rights reserved.</p>
        <p className="v1-footer__stack">Headless Drupal 11 + React 18</p>
      </div>
    </footer>
  );
}
