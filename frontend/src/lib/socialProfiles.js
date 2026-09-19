/** One reviewed destination source for the public footer and local badge gallery. */
export const SOCIAL_PROFILES = [
  { id: 'facebook', label: 'Facebook', href: 'https://www.facebook.com/people/FAMTastic-Designs/100038380452647/', accountLabel: 'FAMtastic Designs', asset: 'facebook.png', enabled: true },
  { id: 'instagram', label: 'Instagram', href: 'https://www.instagram.com/famtasticdesigns/', accountLabel: '@famtasticdesigns', asset: 'instagram.png', enabled: true },
  // Owner-confirmed Brand Account supersedes @nineoo1; see scoped source manifest.
  { id: 'youtube', label: 'YouTube', href: 'https://youtube.com/@FAMtastic-Designs', accountLabel: 'FAMtastic Designs brand channel', asset: 'youtube.png', enabled: true },
  { id: 'x', label: 'X', href: 'https://x.com/FritzMedine', accountLabel: "Fritz Medine’s personal channel", asset: 'x.png', enabled: true },
  { id: 'tiktok', label: 'TikTok', href: 'https://tiktok.com/@famtasticdesigns8', accountLabel: '@famtasticdesigns8', asset: 'tiktok.png', enabled: true },
  { id: 'email', label: 'Email', href: 'mailto:hello@famtasticdesigns.com', accountLabel: 'Contact FAMtastic Designs', enabled: true },
  { id: 'linkedin', label: 'LinkedIn', href: null, asset: 'linkedin.png', enabled: false },
  { id: 'pinterest', label: 'Pinterest', href: null, asset: 'pinterest.jpg', enabled: false },
  { id: 'discord', label: 'Discord', href: null, asset: 'discord.svg', enabled: false },
  { id: 'rss', label: 'RSS', href: null, enabled: false },
];
const hosts = {
  facebook: ['facebook.com', 'www.facebook.com'], instagram: ['instagram.com', 'www.instagram.com'],
  youtube: ['youtube.com', 'www.youtube.com'], x: ['x.com'], tiktok: ['tiktok.com', 'www.tiktok.com'],
  linkedin: ['linkedin.com', 'www.linkedin.com'], pinterest: ['pinterest.com', 'www.pinterest.com'],
  discord: ['discord.com', 'discord.gg'], rss: ['famtasticdesigns.com', 'www.famtasticdesigns.com'],
};
export function isEnabledSocialProfile(profile) {
  if (!profile || profile.enabled !== true || !SOCIAL_PROFILES.some(p => p.id === profile.id)) return false;
  if (typeof profile.href !== 'string' || profile.href.trim() !== profile.href || /[\s\\]/.test(profile.href)) return false;
  if (profile.id === 'email') return profile.href === 'mailto:hello@famtasticdesigns.com';
  try {
    const url = new URL(profile.href);
    return url.protocol === 'https:' && !url.username && !url.password && !url.port && !url.search && !url.hash
      && hosts[profile.id]?.includes(url.hostname) && url.pathname !== '/'
      && !/(?:placeholder|example|undefined|null)/i.test(url.pathname);
  } catch { return false; }
}
export function enabledSocialProfiles(profiles = SOCIAL_PROFILES) {
  const seen = new Set();
  return profiles.filter(profile => {
    if (!isEnabledSocialProfile(profile) || seen.has(profile.id)) return false;
    seen.add(profile.id);
    return true;
  });
}

/** Preserve the existing event contract and existing gtag availability/consent gate.
 * Never initialize a tracker here or interfere with native anchor activation. */
export function trackSocialClick(profile) {
  if (typeof window === 'undefined') return;
  try {
    window.dispatchEvent(new CustomEvent('famtastic:social-click', {
      detail: { platform: profile.id, destination: profile.href },
    }));
  } catch { /* An unavailable observer cannot block navigation or the other event. */ }
  try {
    if (typeof window.gtag === 'function') window.gtag('event', 'social_profile_click', {
      social_platform: profile.id, social_destination: profile.href,
    });
  } catch { /* Navigation remains native even if analytics throws. */ }
}
