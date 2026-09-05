/**
 * The declaration surface.
 *
 * A campaign video is DATA. Adding a drop is adding one object to
 * src/drops/index.ts — no new components, no new layout code, no per-drop
 * timing arithmetic. If a drop needs a new component, that is a signal the kit
 * is missing an archetype, not that this drop is special.
 */
import type { FormatKey } from './formats';
import type { PaletteName } from './palettes';

/**
 * A plate binding. A single filename uses the same art in every aspect; the
 * object form binds different art per aspect, which is the thing a crop-based
 * auto-reframe cannot do.
 *
 * `focus` is the objectPosition used when the plate has to be cropped — 0..1 in
 * each axis. A 16:9 plate cropped to 9:16 loses its sides, so a subject sitting
 * at x=0.3 must say so or it gets cropped out of its own frame.
 */
export type PlateBinding =
  | string
  | ({ default: string } & Partial<Record<FormatKey, string>>);

export type Plate = {
  src: PlateBinding;
  focus?: Partial<Record<FormatKey, [number, number]>> & { default?: [number, number] };
  /** Ken Burns direction for this beat. */
  pan?: 'in' | 'out';
};

type Base = {
  /** Seconds this scene holds. Total drop duration is the sum. */
  seconds: number;
  /** Small-caps label above the composition. */
  eyebrow?: string;
};

/**
 * The archetypes, ported from the still system's FAM_LAYOUTS plus the two
 * motion-only ones (plate, outro) proven by drop-06.
 *
 * Rotate them across a campaign. A drop that uses the same layout five times is
 * the cookie-cutter failure one level up — see CAMPAIGN_ART_DIRECTION_V1 Rule 3.
 */
export type Scene =
  /** Photographic plate carrying a short headline. The hook. */
  | (Base & { kind: 'plate'; plate: Plate; head: string[]; accentFrom?: number })
  /** Full-bleed band of held colour with a diagonal cut; explanation below. */
  | (Base & { kind: 'split'; head: string[]; body?: string[]; cta?: string })
  /** One number very large plus the sentence that gives it meaning. */
  | (Base & { kind: 'stat'; stat: string; body: string[]; cta?: string })
  /** Chip, headline, enormous price, terms, CTA pill, on an elevated panel. */
  | (Base & {
      kind: 'offer-card';
      chip?: string;
      head: string;
      price: string;
      terms: string;
      cta?: string;
      disclosure?: string;
    })
  /** Marker rows with hairlines. Answers "what do I actually get". */
  | (Base & { kind: 'checklist'; head: string[]; items: string[]; note?: string })
  /** Type alone on the ground, or over a plate. The turn of the argument. */
  | (Base & { kind: 'statement'; head: string[]; plate?: Plate })
  /** Mark, promise, CTA pill, terms. Always last. */
  | (Base & { kind: 'outro'; head: string[]; cta: string; terms?: string });

export type SceneKind = Scene['kind'];

export type DropConfig = {
  /** Filename stem and composition id stem. Kebab case. */
  slug: string;
  /** Human title, for the render receipt and the studio sidebar. */
  title: string;
  /** Where the argument comes from. A drop with no source is not a drop. */
  source: string;
  palette: PaletteName;
  /** Why this palette, argued from the subject. Not a preference. */
  paletteArgument: string;
  fps?: number;
  /** Aspects to build. Defaults to all three. */
  formats?: FormatKey[];
  /** Verified HTTP 200 before it is burned into pixels. Never contains /web/. */
  ctaUrl: string;
  /** Optional voice-over / music bed in public/. Omit for a silent typographic cut. */
  audio?: string;
  scenes: Scene[];
};
