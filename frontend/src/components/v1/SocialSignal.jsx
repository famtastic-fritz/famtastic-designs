import { useId } from 'react';

const SOCIAL_PROFILES = [
  {
    id: 'instagram',
    label: 'Instagram',
    handle: '@famtasticdesigns',
    href: 'https://www.instagram.com/famtasticdesigns/',
    accent: '#ff4ecd',
    icon: (
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <rect x="3.2" y="3.2" width="17.6" height="17.6" rx="5" />
        <circle cx="12" cy="12" r="4.1" />
        <circle cx="17.4" cy="6.7" r="1" className="v1-social-signal__icon-fill" />
      </svg>
    ),
  },
  {
    id: 'facebook',
    label: 'Facebook',
    handle: 'FAMtastic Designs',
    href: 'https://www.facebook.com/1718361399453192',
    accent: '#49a4ff',
    icon: (
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M13.6 21v-8h2.8l.4-3.1h-3.2v-2c0-.9.3-1.5 1.6-1.5h1.7V3.6c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1v2.3H8v3.1h2.5v8h3.1Z" className="v1-social-signal__icon-fill" />
      </svg>
    ),
  },
];

function trackSocialClick(profile) {
  const detail = { platform: profile.id, destination: profile.href };
  window.dispatchEvent(new CustomEvent('famtastic:social-click', { detail }));
  if (typeof window.gtag === 'function') {
    window.gtag('event', 'social_profile_click', {
      social_platform: profile.id,
      social_destination: profile.href,
    });
  }
}

export default function SocialSignal() {
  const orbitId = useId();

  return (
    <section className="v1-social-signal" aria-labelledby={`${orbitId}-title`}>
      <div className="v1-social-signal__copy">
        <p className="v1-footer__heading">Follow the signal</p>
        <h2 id={`${orbitId}-title`}>See what we are building next.</h2>
        <p>Ideas, experiments, and real business systems—out in the open.</p>
      </div>

      <div className="v1-social-signal__orbit" aria-label="FAMtastic Designs social profiles">
        <span className="v1-social-signal__core" aria-hidden="true">
          <span>FAM</span>
          <small>LIVE</small>
        </span>
        <span className="v1-social-signal__ring v1-social-signal__ring--one" aria-hidden="true" />
        <span className="v1-social-signal__ring v1-social-signal__ring--two" aria-hidden="true" />
        {SOCIAL_PROFILES.map((profile, index) => (
          <a
            className={`v1-social-signal__node v1-social-signal__node--${profile.id}`}
            href={profile.href}
            key={profile.id}
            target="_blank"
            rel="noreferrer"
            style={{ '--social-accent': profile.accent, '--social-delay': `${index * 1.5}s` }}
            onClick={() => trackSocialClick(profile)}
            aria-label={`Follow FAMtastic Designs on ${profile.label}, ${profile.handle}`}
          >
            <span className="v1-social-signal__icon">{profile.icon}</span>
            <span className="v1-social-signal__node-label">
              <strong>{profile.label}</strong>
              <small>{profile.handle}</small>
            </span>
          </a>
        ))}
      </div>
    </section>
  );
}
