/**
 * FORMATS — one master composition, every platform.
 *
 * This is the load-bearing claim that Premiere's `auto_reframe_sequence` was
 * supposed to prove and could not (docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md,
 * "Known gaps": *"Premiere unproven; auto-reframe is the load-bearing claim of
 * this whole model and has not yet been demonstrated."*).
 *
 * Auto-reframe is a *crop* with a moving window. It cannot re-flow type, cannot
 * re-break a headline, and cannot swap the artwork for one that was shot in the
 * target aspect. This module does all three, because the layout is resolved from
 * the frame size at render time rather than baked into a 16:9 master.
 *
 * The type scale is a direct port of FAM_FORMATS in
 * marketing/creative/templates/famtastic-social-frame.jsx (line 124), so a still
 * and a video from the same campaign share a type system, not just a palette.
 *
 *   JSX row order: [W, H, margin, eyebrow, head, headLead, body, footer, headTop]
 *     story-9x16   [1080, 1920, 90, 30, 118, 138, 40, 38, 640]
 *     square-1x1   [1080, 1080, 80, 27,  96, 114, 36, 32, 400]
 *     wide-16x9    [1280,  720, 76, 24,  84, 100, 32, 28, 300]
 *
 * `wide-16x9` is authored at 1280x720 in Photoshop and rendered at 1920x1080
 * here, so its type row is scaled by exactly 1.5. That is the only deviation
 * from the still system, and it is arithmetic rather than taste.
 */

export type FormatKey = '9x16' | '1x1' | '16x9';

export type Format = {
  key: FormatKey;
  width: number;
  height: number;
  /** Left/right margin. */
  margin: number;
  /** Clear of the platform's own top chrome (handles, follow button). */
  safeTop: number;
  /** Clear of the platform's own bottom chrome (caption, CTA bar, progress). */
  safeBottom: number;
  type: {
    eyebrow: number;
    /** Display face, the headline size ceiling before fitting. */
    head: number;
    /** Line height for the display face, in px. */
    headLead: number;
    body: number;
    footer: number;
  };
  /** Portrait/square stack vertically; landscape can afford two columns. */
  columns: 1 | 2;
};

const F = (
  key: FormatKey,
  width: number,
  height: number,
  margin: number,
  safeTop: number,
  safeBottom: number,
  eyebrow: number,
  head: number,
  headLead: number,
  body: number,
  footer: number,
  columns: 1 | 2,
): Format => ({
  key,
  width,
  height,
  margin,
  safeTop,
  safeBottom,
  type: { eyebrow, head, headLead, body, footer },
  columns,
});

export const FORMATS: Record<FormatKey, Format> = {
  // TikTok, Reels, Shorts, Stories. Bottom 300px belongs to the platform.
  '9x16': F('9x16', 1080, 1920, 90, 150, 300, 30, 118, 138, 44, 38, 1),
  // Instagram, Facebook, X, LinkedIn in-feed.
  '1x1': F('1x1', 1080, 1080, 80, 96, 118, 27, 96, 114, 38, 32, 1),
  // YouTube, X inline, site embeds. 1280x720 Photoshop row x 1.5.
  '16x9': F('16x9', 1920, 1080, 114, 84, 96, 36, 126, 150, 48, 42, 2),
};

export const FORMAT_ORDER: FormatKey[] = ['9x16', '1x1', '16x9'];

/** Resolve the format from the frame the renderer actually gave us. */
export const formatFor = (width: number, height: number): Format => {
  const r = width / height;
  if (r < 0.85) return FORMATS['9x16'];
  if (r > 1.2) return FORMATS['16x9'];
  return FORMATS['1x1'];
};

/** The box every scene composes inside. */
export const safeBox = (f: Format) => ({
  x: f.margin,
  y: f.safeTop,
  w: f.width - f.margin * 2,
  h: f.height - f.safeTop - f.safeBottom,
});

/*
 * Fit a headline to a column — the port of famFitSize.
 *
 * The still template's constants (display 0.42) were measured against
 * HelveticaNeue-CondensedBold, which is condensed. Space Grotesk Bold and Inter
 * are not, so the constants below were re-measured for these faces by rendering
 * stills and inspecting them, not carried over. See VIDEO_SYSTEM.md, "Type
 * fitting", for the measurement and its limits.
 *
 * Without this, a long headline overflows its column — which is exactly what
 * happened on the first ghost-town still and had to be caught by looking at the
 * render rather than by trusting a success message.
 */
export const CHAR_W = { display: 0.545, body: 0.515, bodyBold: 0.535 } as const;

export const fitSize = (
  text: string,
  maxWidth: number,
  base: number,
  kind: keyof typeof CHAR_W = 'body',
): number => {
  if (!text || !text.length) return base;
  const fits = Math.floor(maxWidth / (text.length * CHAR_W[kind]));
  return Math.max(12, Math.min(base, fits));
};

/** Fit a multi-line headline: every line must fit, so the shortest wins. */
export const fitLines = (
  lines: string[],
  maxWidth: number,
  base: number,
  kind: keyof typeof CHAR_W = 'display',
): number => lines.reduce((acc, l) => Math.min(acc, fitSize(l, maxWidth, base, kind)), base);
