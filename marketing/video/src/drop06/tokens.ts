/**
 * FAMtastic brand tokens for motion work.
 * Mirrors the live site's v1 token block (frontend/src/index.css) and BRAND.md.
 * Single accent. At most one glow per screen.
 */
export const T = {
  ground: '#070907',
  groundLift: '#0d120c',
  panel: '#101310',
  panelHi: '#141814',
  border: '#252b25',
  borderSoft: 'rgba(255,255,255,0.08)',
  lime: '#7cfc00',
  ink: '#050807',
  text: '#ffffff',
  text72: 'rgba(255,255,255,0.72)',
  text55: 'rgba(255,255,255,0.55)',
  text38: 'rgba(255,255,255,0.38)',
  glow: '0 0 24px rgba(124,252,0,.35)',
  radius: 28,
} as const;

/** 1080x1920 layout guides. Bottom 340px stays clear of platform chrome. */
export const L = {
  W: 1080,
  H: 1920,
  gutter: 72,
  topChromeY: 92,
  captionBaseline: 1520,
  safeBottom: 340,
} as const;
