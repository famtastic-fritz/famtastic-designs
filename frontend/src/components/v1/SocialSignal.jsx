import { useId } from 'react';
import SocialBadge from './SocialBadge.jsx';
import { enabledSocialProfiles } from '../../lib/socialProfiles.js';

/** Existing footer seam; profile truth lives in one shared configuration. */
export default function SocialSignal() {
  const id = useId();
  const profiles = enabledSocialProfiles();
  if (!profiles.length) return null;
  return <section className="v1-social-signal" aria-labelledby={`${id}-title`}>
    <div className="v1-social-signal__heading">
      <h2 id={`${id}-title`}>Stay connected</h2>
      <span>Follow the vision.</span>
    </div>
    <ul className="v1-social-signal__list">
      {profiles.map(profile => <li key={profile.id}><SocialBadge profile={profile} /></li>)}
    </ul>
  </section>;
}
