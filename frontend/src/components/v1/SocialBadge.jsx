import { SOCIAL_PROFILES, isEnabledSocialProfile, trackSocialClick } from '../../lib/socialProfiles.js';
import './social-footer.css';

/** Independent housing and official mark: effects never touch the platform artwork. */
export default function SocialBadge({ profile, preview = false, size }) {
  const canonical = SOCIAL_PROFILES.find(item => item.id === profile?.id);
  if (!canonical || (!preview && !isEnabledSocialProfile(profile))) return null;
  const artwork = <>
    <span className="fam-social-badge__art" aria-hidden="true">
      <span className="fam-social-badge__housing" />
      <svg className="fam-social-badge__brush" viewBox="0 0 64 64" focusable="false">
        <path fill="#ed2024" d="M8 15 29 5 19 12 31 7 15 17 21 13 7 25 12 16 5 22Z" />
        <path fill="#e4b52b" d="m5 41 6 13 13 5-9-7 6 3-10-13 4 10-6-15Z" />
        <path fill="#0965ff" d="m58 32-5 17-14 10 9-10-10 7 14-17-4 11 8-22Z" />
        <path fill="none" stroke="#ffffff" strokeOpacity=".4" strokeWidth=".5" d="m9 16 13-7M8 20l12-8m-9 36 7 8m31-7 7-13m-10 17 7-12" />
      </svg>
      <span className="fam-social-badge__edge" />
      {canonical.asset ? <img className={`fam-social-badge__mark fam-social-badge__mark--${canonical.id}`} src={`/brand/social/${canonical.asset}`} width="32" height="32" alt="" decoding="async" />
        : <svg className="fam-social-badge__mark" viewBox="0 0 32 32" fill="none" stroke="#f7f7f4" strokeWidth="2" focusable="false">
          {canonical.id === 'email' ? <><rect x="3" y="6" width="26" height="20" rx="2"/><path d="m4 8 12 10L28 8"/></>
            : <><circle cx="7" cy="25" r="2" fill="#f7f7f4" stroke="none"/><path d="M5 14a13 13 0 0 1 13 13M5 5a22 22 0 0 1 22 22"/></>}
        </svg>}
    </span>
    <span className="fam-social-badge__label">{canonical.label}</span>
    {canonical.id === 'x' && !preview && <small className="fam-social-badge__account">Fritz’s channel</small>}
    {preview && <small className="fam-social-badge__account">Preview only</small>}
  </>;
  const style = [48, 56, 64, 128].includes(size) ? { '--badge-size': `${size}px` } : undefined;
  if (preview) return <span className="fam-social-badge fam-social-badge--preview" style={style}>{artwork}</span>;
  const external = profile.id !== 'email';
  return <a className="fam-social-badge" style={style} href={profile.href}
    target={external ? '_blank' : undefined} rel={external ? 'noopener noreferrer' : undefined}
    aria-label={`${canonical.label} — ${profile.accountLabel || canonical.label}${external ? ' (opens in a new tab)' : ''}`}
    onClick={() => trackSocialClick(profile)} onAuxClick={event => { if (event.button === 1) trackSocialClick(profile); }}>
    {artwork}
  </a>;
}
