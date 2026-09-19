import { useState } from 'react';
import { Link } from 'react-router';
import SocialSignal from './SocialSignal.jsx';
import BrandLogo from '../BrandLogo.jsx';
import './footer-atmosphere.css';

const CONTACT_EMAIL = 'hello@famtasticdesigns.com';

/**
 * Existing public footer: canonical brand, compact social badges, CMS navigation.
 * Service/package columns are populated live from JSON:API when available.
 */
export default function SiteFooter({ services = [], packages = [] }) {
  const year = new Date().getFullYear();
  const [motionPaused, setMotionPaused] = useState(false);

  return (
    <footer className="v1-footer" data-motion-paused={motionPaused}>
      <div className="v1-footer__atmosphere" aria-hidden="true">
        <span>More than a website.</span>
        <span>Always FAMtastic.</span>
      </div>
      <div className="v1-container v1-footer__grid">
        <div className="v1-footer__brand">
          <Link to="/" className="v1-footer__logo" aria-label="FAMtastic Designs — home">
            <BrandLogo placement="footer" decorative />
          </Link>
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

        <div className="v1-footer__content">
          <SocialSignal />
          <div className="v1-footer__links">
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
              {packages.slice(0, 7).map((item) => (
                <Link key={item.slug} to={`/packages/${item.slug}`}>
                  {item.title}
                </Link>
              ))}
              <Link to="/packages">All packages →</Link>
            </nav>

            <nav className="v1-footer__col v1-footer__company" aria-label="Company and contact">
              <p className="v1-footer__heading">Company</p>
              <Link to="/about">About</Link>
              <Link to="/work">Work</Link>
              <Link to="/blog">Blogs</Link>
              <Link to="/faq">FAQ</Link>
              <Link to="/contact">Contact</Link>
              <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>
            </nav>
          </div>
        </div>
      </div>

      <div className="v1-container v1-footer__bottom">
        <p>© {year} FAMtastic Designs. All rights reserved.</p>
        <nav className="v1-footer__legal" aria-label="Legal">
          <Link to="/privacy-policy">Privacy Policy</Link>
          <Link to="/terms-of-service">Terms of Service</Link>
        </nav>
        <p className="v1-footer__stack"><span className="fam-signature">Design that glows</span> in the dark.</p>
        <button className="v1-footer__motion-toggle" type="button" aria-pressed={motionPaused} onClick={() => setMotionPaused(paused => !paused)}>
          {motionPaused ? 'Resume background motion' : 'Pause background motion'}
        </button>
      </div>
    </footer>
  );
}
