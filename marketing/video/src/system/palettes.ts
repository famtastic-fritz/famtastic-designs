/**
 * PALETTES — a direct port of the Photoshop design system's palette block.
 *
 * Source of truth: marketing/creative/templates/famtastic-social-frame.jsx (lines 75-115).
 * The RGB triples below are copied verbatim so a still and a video from the same
 * campaign are provably the same colours, not "close enough".
 *
 * Owner directive 2026-09-04: "I don't want to get stuck in this damn black and
 * green." Black-and-lime is the FAMtastic *site* identity, not an instruction to
 * make every campaign look like the site.
 *
 * Adding a palette is an argument, not a preference. Say what in the subject
 * produced it. See docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md Rule 1.
 */

export type RGB = readonly [number, number, number];

/**
 * The anchor's measured darkest decile, from
 * marketing/creative/heygen/reference-tokens.json (`ground_darkest_decile`,
 * "#33272e"). Used to grade the `paper` palette's edge falloff toward the
 * actual anchor shadow rather than an invented vignette colour — see the
 * grading fix recorded in VIDEO_SYSTEM.md.
 */
export const ANCHOR_SHADOW: RGB = [0x33, 0x27, 0x2e];

export type Palette = {
  /** Canvas ground. */
  ground: RGB;
  /** The single accent. There is exactly one. */
  accent: RGB;
  /** Headline / high-emphasis text. */
  head: RGB;
  /** Body / low-emphasis text. */
  body: RGB;
  /** Hairline rules and dividers. */
  hair: RGB;
  /** Why this palette exists. Not decoration — the argument. */
  note: string;
};

export const PALETTES = {
  // The house signature. Use when FAMtastic is talking about FAMtastic.
  famtastic: {
    ground: [7, 9, 7],
    accent: [124, 252, 0],
    head: [255, 255, 255],
    body: [150, 163, 150],
    hair: [38, 48, 38],
    note: 'Site identity. #070907 / #7cfc00.',
  },
  // Sun-bleached, dusty, abandoned main street. A business that exists but
  // cannot be found.
  'ghost-town': {
    ground: [23, 18, 13],
    accent: [217, 164, 65],
    head: [242, 233, 216],
    body: [168, 152, 128],
    hair: [58, 47, 34],
    note: 'Amber dust on dark earth. Absence, heat, weathering.',
  },
  // Warm, low, intimate. Salon and personal-service work.
  salon: {
    ground: [26, 16, 19],
    accent: [232, 180, 184],
    head: [250, 242, 240],
    body: [193, 170, 170],
    hair: [61, 40, 45],
    note: 'Rose on plum. Skin-adjacent warmth, never clinical.',
  },
  // Industrial, high-visibility. Trades, automotive, contractors.
  trades: {
    ground: [13, 17, 23],
    accent: [255, 122, 26],
    head: [240, 246, 252],
    body: [139, 152, 165],
    hair: [32, 43, 56],
    note: 'Safety orange on blue-black. Work, not lifestyle.',
  },
  // Daylight. Documents, proposals, anything that must read sober rather than
  // as an ad. Proves the system is not a dark-mode trick.
  paper: {
    ground: [216, 209, 194],
    accent: [31, 111, 74],
    head: [20, 18, 15],
    body: [90, 86, 78],
    hair: [214, 208, 196],
    note: 'Ink on warm paper. Light ground; depth inverts from lit edge to cast shadow.',
  },
} as const satisfies Record<string, Palette>;

export type PaletteName = keyof typeof PALETTES;

/* ------------------------------------------------------------------ colour */

export const rgb = (c: RGB, alpha = 1): string =>
  alpha >= 1 ? `rgb(${c[0]}, ${c[1]}, ${c[2]})` : `rgba(${c[0]}, ${c[1]}, ${c[2]}, ${alpha})`;

export const mix = (a: RGB, b: RGB, t: number): RGB => [
  Math.round(a[0] + (b[0] - a[0]) * t),
  Math.round(a[1] + (b[1] - a[1]) * t),
  Math.round(a[2] + (b[2] - a[2]) * t),
];

/** A light ground needs the art to darken rather than glow, or it disappears. */
export const isLightGround = (p: Palette): boolean =>
  (p.ground[0] + p.ground[1] + p.ground[2]) / 3 > 128;

/**
 * Resolved per-drop theme. Everything a component needs, derived once, so no
 * component ever reaches for a raw colour or invents a tint of its own.
 */
export type Theme = {
  name: PaletteName;
  p: Palette;
  light: boolean;
  ground: string;
  /** Slight lift at the top of the frame so a flat ground is not literally flat. */
  groundGradient: string;
  accent: string;
  head: string;
  body: string;
  hair: string;
  /** Panel face — ported from famPanel: lift toward head, then a whisper of accent. */
  panel: (mixT?: number) => string;
  /**
   * THE ONE GLOW. Design DNA v1 allows exactly one per screen.
   * On the famtastic palette this is the literal DNA string
   * `0 0 24px rgba(124,252,0,.35)`. On a light ground a glow is physically
   * wrong, so the same intent renders as a real cast shadow (famElevate's
   * inversion, ported).
   */
  glow: string;
  /** Text colour that reads on top of the accent (chips, pills). */
  onAccent: string;
};

export const theme = (name: PaletteName): Theme => {
  const p = PALETTES[name] as Palette;
  const light = isLightGround(p);
  return {
    name,
    p,
    light,
    ground: rgb(p.ground),
    // A light ground is a physical paper surface, not a glowing panel: it
    // should fall off toward a cast shadow at the edges, not brighten toward
    // white at the centre. The centre (where headlines and body copy sit)
    // stays at the base ground tone for text contrast; the outer field falls
    // off toward the anchor's own measured shadow value (ANCHOR_SHADOW,
    // #33272e) so a flat-colour scene reads as lit paper rather than a
    // blown-out card. This replaced a brightening gradient that was the
    // primary cause of the 212.1 YAVG overshoot documented in
    // VIDEO_SYSTEM.md — grading directive: mean luminance 150-175.
    groundGradient: light
      ? `radial-gradient(104% 80% at 50% 40%, ${rgb(p.ground)} 0%, ${rgb(p.ground)} 26%, ${rgb(mix(p.ground, ANCHOR_SHADOW, 0.30))} 62%, ${rgb(mix(p.ground, ANCHOR_SHADOW, 0.78))} 100%)`
      : `radial-gradient(125% 72% at 50% 10%, ${rgb(mix(p.ground, p.head, 0.045))} 0%, ${rgb(p.ground)} 62%)`,
    accent: rgb(p.accent),
    head: rgb(p.head),
    body: rgb(p.body),
    hair: rgb(p.hair),
    panel: (mixT = 0.09) => rgb(mix(mix(p.ground, p.head, mixT), p.accent, 0.06)),
    glow: light
      ? '0 18px 44px rgba(0,0,0,0.18), 0 2px 6px rgba(0,0,0,0.10)'
      : `0 0 24px rgba(${p.accent[0]},${p.accent[1]},${p.accent[2]},.35)`,
    onAccent: rgb(p.ground),
  };
};
