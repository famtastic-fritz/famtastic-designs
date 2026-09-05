/**
 * THE COMPONENT KIT.
 *
 * Ported from marketing/creative/templates/famtastic-social-frame.jsx
 * ("Components", line 598 onward). Same objects, same physics, same names —
 * famPanel/famChip/famPill/famElevate/famText3D become Panel/Chip/Pill/Elevate/
 * Text3D here.
 *
 * Cox's lesson, recorded in CAMPAIGN_ART_DIRECTION_V1 Rule 3: variety comes from
 * a small kit of reusable objects recombined, not from bespoke art each time.
 * That is reproducible; taste is not.
 */
import React, { createContext, useContext } from 'react';
import { AbsoluteFill, Img, interpolate, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { loadFont as loadDisplay } from '@remotion/google-fonts/SpaceGrotesk';
import { loadFont as loadBody } from '@remotion/google-fonts/Inter';
import { formatFor, type Format, type FormatKey } from './formats';
import { theme, type Theme, type PaletteName } from './palettes';
import { easeIn, fadeIn, kenBurns, rise, wipe } from './motion';
import type { Plate } from './types';

/**
 * Brand faces. Inter and Space Grotesk are the site's real faces (BRAND.md and
 * the HeyGen brand kit both resolve to them). The Photoshop template cannot use
 * them because they are not installed as system fonts on this machine and
 * substitutes HelveticaNeue-Condensed / AvenirNext — so the video tier is
 * currently the ONLY tier rendering the correct typography. That is a known
 * inconsistency, recorded in VIDEO_SYSTEM.md, not a silent divergence.
 */
const display = loadDisplay().fontFamily;
const body = loadBody().fontFamily;
export const FONTS = { display, body };

/* ------------------------------------------------------------------ context */

type Ctx = { t: Theme; f: Format; glowKey: string | null };
const KitCtx = createContext<Ctx | null>(null);

export const useKit = (): Ctx => {
  const c = useContext(KitCtx);
  if (!c) throw new Error('kit component rendered outside <KitProvider>');
  return c;
};

export const KitProvider: React.FC<{
  palette: PaletteName;
  children: React.ReactNode;
}> = ({ palette, children }) => {
  const { width, height } = useVideoConfig();
  return (
    <KitCtx.Provider value={{ t: theme(palette), f: formatFor(width, height), glowKey: null }}>
      {children}
    </KitCtx.Provider>
  );
};

/**
 * THE ONE GLOW, enforced structurally.
 *
 * Design DNA v1 allows exactly one glow per screen. Rather than trusting every
 * archetype to remember that, each scene names the single element permitted to
 * carry it. `useGlow(key)` returns the shadow only for that key and 'none' for
 * everything else, so a second glowing element is not a review finding — it is
 * unrepresentable.
 */
export const GlowScope: React.FC<{ on: string; children: React.ReactNode }> = ({ on, children }) => {
  const c = useKit();
  return <KitCtx.Provider value={{ ...c, glowKey: on }}>{children}</KitCtx.Provider>;
};

export const useGlow = (key: string): string => {
  const { t, glowKey } = useKit();
  return glowKey === key ? t.glow : 'none';
};

/* ------------------------------------------------------------------- ground */

export const Ground: React.FC = () => {
  const { t } = useKit();
  return <AbsoluteFill style={{ background: t.groundGradient }} />;
};

/**
 * Very low-amplitude film grain, so flat panels never band after the platform
 * re-compresses the upload. Overlay on dark, soft-light on paper — a dark grain
 * over a light ground reads as dirt.
 */
export const Grain: React.FC = () => {
  const { t, f } = useKit();
  return (
    <AbsoluteFill
      style={{
        opacity: t.light ? 0.03 : 0.055,
        mixBlendMode: t.light ? 'soft-light' : 'overlay',
        pointerEvents: 'none',
      }}
    >
      <svg width={f.width} height={f.height}>
        <filter id="famgrain">
          <feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves={3} stitchTiles="stitch" />
          <feColorMatrix type="saturate" values="0" />
        </filter>
        <rect width={f.width} height={f.height} filter="url(#famgrain)" />
      </svg>
    </AbsoluteFill>
  );
};

/* --------------------------------------------------------------------- mark */

export const Mark: React.FC<{ size: number }> = ({ size }) => {
  const { t } = useKit();
  return (
    <svg width={size} height={size} viewBox="0 0 64 64">
      <rect width="64" height="64" rx="14" fill={t.panel(0.06)} />
      <rect x="1.5" y="1.5" width="61" height="61" rx="12.5" fill="none" stroke={t.hair} strokeWidth="3" />
      <path d="M20 48V16h24v7H28v5.5h13V35H28v13h-8z" fill={t.accent} />
      <circle cx="47" cy="46" r="3.5" fill={t.accent} />
    </svg>
  );
};

/**
 * Persistent brand lock-up plus a progress rail. Never carries the screen's
 * glow — the chrome is the frame, not the subject.
 */
export const Chrome: React.FC<{ totalFrames: number }> = ({ totalFrames }) => {
  const { t, f } = useKit();
  const frame = useCurrentFrame();
  const progress = Math.min(1, frame / totalFrames);
  const markSize = Math.round(f.type.eyebrow * 1.8);
  return (
    <>
      <div
        style={{
          position: 'absolute',
          top: Math.round(f.safeTop * 0.55),
          left: f.margin,
          display: 'flex',
          alignItems: 'center',
          gap: Math.round(markSize * 0.38),
        }}
      >
        <Mark size={markSize} />
        <div
          style={{
            fontFamily: display,
            fontWeight: 700,
            fontSize: Math.round(f.type.eyebrow * 0.9),
            letterSpacing: '0.2em',
            color: t.body,
            textTransform: 'uppercase',
          }}
        >
          FAMtastic Designs
        </div>
      </div>
      <div
        style={{
          position: 'absolute',
          left: 0,
          right: 0,
          bottom: 0,
          height: 6,
          background: t.light ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.07)',
        }}
      >
        <div style={{ width: `${progress * 100}%`, height: '100%', background: t.accent }} />
      </div>
    </>
  );
};

/* ------------------------------------------------------------------ eyebrow */

export const Eyebrow: React.FC<{ text: string; at?: number }> = ({ text, at = 3 }) => {
  const { t, f } = useKit();
  const frame = useCurrentFrame();
  const size = f.type.eyebrow;
  return (
    <div
      style={{
        opacity: fadeIn(frame, at, 8),
        display: 'flex',
        alignItems: 'center',
        gap: Math.round(size * 0.72),
      }}
    >
      <div
        style={{
          width: wipe(frame, at, 18, Math.round(size * 2.8)),
          height: Math.max(4, Math.round(size * 0.16)),
          background: t.accent,
          borderRadius: 3,
        }}
      />
      <div
        style={{
          fontFamily: display,
          fontWeight: 700,
          fontSize: size,
          letterSpacing: '0.22em',
          textTransform: 'uppercase',
          color: t.accent,
          whiteSpace: 'nowrap',
        }}
      >
        {text}
      </div>
    </div>
  );
};

/* ---------------------------------------------------------------- primitives */

/**
 * Depth on a 2D surface — the port of famElevate, physics intact.
 *
 * A dark ground cannot take a cast shadow (black on near-black is nothing), so
 * it gets a lit edge; a light ground gets a real shadow. Same intent, opposite
 * physics. This is the only place either is allowed to come from.
 */
export const Elevate: React.FC<{
  radius?: number;
  glowKey?: string;
  style?: React.CSSProperties;
  children: React.ReactNode;
}> = ({ radius = 28, glowKey = 'panel', style, children }) => {
  const { t } = useKit();
  const shadow = useGlow(glowKey);
  return (
    <div
      style={{
        position: 'relative',
        borderRadius: radius,
        boxShadow: shadow,
        border: `1px solid ${t.hair}`,
        overflow: 'hidden',
        ...style,
      }}
    >
      {children}
    </div>
  );
};

/**
 * Tinted panel — the single most useful thing borrowed from Cox: a block of
 * held colour a section sits inside, instead of everything floating on one flat
 * ground. `cutCorner` clips the lower-right corner so a frame stops reading as
 * a stack of rectangles.
 */
export const Panel: React.FC<{
  mixT?: number;
  cutCorner?: boolean;
  radius?: number;
  glowKey?: string;
  style?: React.CSSProperties;
  children?: React.ReactNode;
}> = ({ mixT = 0.09, cutCorner = false, radius = 28, glowKey = 'panel', style, children }) => {
  const { t } = useKit();
  const shadow = useGlow(glowKey);
  return (
    <div
      style={{
        position: 'relative',
        background: t.panel(mixT),
        border: `1px solid ${t.hair}`,
        borderRadius: cutCorner ? 0 : radius,
        boxShadow: shadow,
        // The angled corner. A non-rectangular edge, so the composition is not
        // four stacked boxes.
        clipPath: cutCorner ? 'polygon(0 0, 100% 0, 100% 84%, 92% 100%, 0 100%)' : undefined,
        ...style,
      }}
    >
      {children}
    </div>
  );
};

/** Badge chip: small-caps label in a solid block. Cox's "SPECIAL OFFER". */
export const Chip: React.FC<{ text: string; size: number; at?: number }> = ({ text, size, at = 6 }) => {
  const { t } = useKit();
  const frame = useCurrentFrame();
  return (
    <div
      style={{
        display: 'inline-block',
        background: t.accent,
        padding: `${Math.round(size * 0.5)}px ${Math.round(size * 0.85)}px`,
        opacity: fadeIn(frame, at, 8),
        transform: rise(frame, at, 14, 10),
      }}
    >
      <div
        style={{
          fontFamily: body,
          fontWeight: 700,
          fontSize: size,
          letterSpacing: '0.14em',
          textTransform: 'uppercase',
          color: t.onAccent,
        }}
      >
        {text}
      </div>
    </div>
  );
};

/** CTA pill. An actual button object, not a bare URL in the footer. */
export const Pill: React.FC<{
  text: string;
  size: number;
  at?: number;
  variant?: 'solid' | 'outline';
  glowKey?: string;
}> = ({ text, size, at = 20, variant = 'solid', glowKey = 'pill' }) => {
  const { t } = useKit();
  const frame = useCurrentFrame();
  const shadow = useGlow(glowKey);
  const solid = variant === 'solid';
  return (
    <div
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        background: solid ? t.accent : 'transparent',
        border: solid ? 'none' : `2px solid ${t.accent}`,
        borderRadius: 999,
        boxShadow: shadow,
        padding: `${Math.round(size * 0.66)}px ${Math.round(size * 1.5)}px`,
        opacity: fadeIn(frame, at, 10),
        transform: rise(frame, at, 16, 16),
      }}
    >
      <div
        style={{
          fontFamily: body,
          fontWeight: 700,
          fontSize: size,
          letterSpacing: '-0.005em',
          color: solid ? t.onAccent : t.accent,
          whiteSpace: 'nowrap',
        }}
      >
        {text}
      </div>
    </div>
  );
};

/**
 * Extruded display type — the port of famText3D. Depth without a bevel filter:
 * a copy behind, offset, in accent. Reads dimensional on both grounds.
 * Owner note 2026-09-04: "start thinking in 3D effect on 2D surfaces."
 */
export const Text3D: React.FC<{
  text: string;
  size: number;
  depth?: number;
  at?: number;
  style?: React.CSSProperties;
}> = ({ text, size, depth, at = 4, style }) => {
  const { t } = useKit();
  const frame = useCurrentFrame();
  const d = depth ?? Math.max(3, Math.round(size * 0.045));
  const e = easeIn(frame, at, 18);
  const common: React.CSSProperties = {
    fontFamily: display,
    fontWeight: 700,
    fontSize: size,
    lineHeight: 0.88,
    letterSpacing: '-0.05em',
    whiteSpace: 'nowrap',
    ...style,
  };
  return (
    <div style={{ position: 'relative', opacity: fadeIn(frame, at, 10), transform: rise(frame, at, 18, 24) }}>
      {/* Extrusion travels out as the type settles, so the depth is earned. */}
      <div
        style={{
          ...common,
          position: 'absolute',
          top: d * e,
          left: d * e,
          color: t.accent,
        }}
      >
        {text}
      </div>
      <div style={{ ...common, position: 'relative', color: t.head }}>{text}</div>
    </div>
  );
};

/** Shared signature block, so every archetype closes the same way. */
export const Signature: React.FC<{ url: string; at?: number }> = ({ url, at = 24 }) => {
  const { t, f } = useKit();
  const frame = useCurrentFrame();
  const fs = f.type.footer;
  // The actual URL remains the verified CTA destination. Render only its
  // human-readable origin and path; UTM parameters are useful to the browser,
  // not to the viewer, and overflow every safe area on a portrait screen.
  const displayUrl = (() => {
    try {
      const parsed = new URL(url);
      return `${parsed.hostname.replace(/^www\./, '')}${parsed.pathname}`;
    } catch {
      return url;
    }
  })();
  return (
    <div style={{ opacity: fadeIn(frame, at, 12) }}>
      <div style={{ height: 2, background: t.hair, marginBottom: Math.round(fs * 0.72) }} />
      <div
        style={{
          fontFamily: body,
          fontWeight: 700,
          fontSize: Math.max(14, Math.round(fs * 0.62)),
          letterSpacing: '0.12em',
          textTransform: 'uppercase',
          color: t.body,
          marginBottom: Math.round(fs * 0.42),
        }}
      >
        FAMtastic Designs
      </div>
      <div
        style={{
          fontFamily: body,
          fontWeight: 700,
          fontSize: fs,
          letterSpacing: '0.04em',
          color: t.accent,
        }}
      >
        {displayUrl}
      </div>
    </div>
  );
};

/* --------------------------------------------------------------------- art */

export const resolvePlate = (p: Plate, key: FormatKey): { src: string; focus: [number, number] } => {
  const src = typeof p.src === 'string' ? p.src : p.src[key] ?? p.src.default;
  const focus = p.focus?.[key] ?? p.focus?.default ?? [0.5, 0.5];
  return { src, focus };
};

/**
 * A photographic plate, cropped to the current aspect with an authored focal
 * point, drifting under a scrim.
 *
 * The scrim is a gradient rather than a filled box, so the plate stays visible
 * under the type instead of being covered by it. `weight` raises the scrim for
 * scenes carrying more words.
 */
export const PlateArt: React.FC<{ plate: Plate; length: number; weight?: number }> = ({
  plate,
  length,
  weight = 1,
}) => {
  const { t, f } = useKit();
  const frame = useCurrentFrame();
  const { src, focus } = resolvePlate(plate, f.key);
  const { scale, drift } = kenBurns(frame, length, plate.pan ?? 'in');
  const g = t.p.ground;
  const a = (v: number) => `rgba(${g[0]},${g[1]},${g[2]},${Math.min(0.97, v * weight)})`;
  return (
    <AbsoluteFill>
      <AbsoluteFill style={{ transform: `scale(${scale}) translateY(${drift}px)` }}>
        <Img
          src={staticFile(`plates/${src}`)}
          style={{
            width: '100%',
            height: '100%',
            objectFit: 'cover',
            objectPosition: `${focus[0] * 100}% ${focus[1] * 100}%`,
          }}
        />
      </AbsoluteFill>
      {/* Top scrim: holds the eyebrow and the brand lock-up. */}
      <AbsoluteFill
        style={{
          background: `linear-gradient(180deg, ${a(0.86)} 0%, ${a(0.3)} 26%, ${a(0.08)} 46%)`,
        }}
      />
      {/* Bottom scrim: holds the headline. */}
      <AbsoluteFill
        style={{
          background: `linear-gradient(180deg, ${a(0)} 46%, ${a(0.72)} 72%, ${a(0.95)} 100%)`,
        }}
      />
    </AbsoluteFill>
  );
};

/* --------------------------------------------------------------- type atoms */

/** Display headline, one line per array entry, accent on the chosen lines. */
export const Headline: React.FC<{
  lines: string[];
  size: number;
  lead?: number;
  accentFrom?: number;
  at?: number;
  step?: number;
  align?: 'left' | 'center';
  shadow?: boolean;
}> = ({ lines, size, lead, accentFrom, at = 4, step = 8, align = 'left', shadow = false }) => {
  const { t } = useKit();
  const frame = useCurrentFrame();
  return (
    <div style={{ textAlign: align }}>
      {lines.map((line, i) => {
        const start = at + i * step;
        const accent = accentFrom !== undefined && i >= accentFrom;
        return (
          <div
            key={i}
            style={{
              fontFamily: display,
              fontWeight: 700,
              fontSize: size,
              lineHeight: (lead ?? size * 1.02) / size,
              letterSpacing: '-0.045em',
              color: accent ? t.accent : t.head,
              opacity: fadeIn(frame, start, 12),
              transform: rise(frame, start, 18, 22),
              textShadow: shadow ? '0 3px 26px rgba(0,0,0,0.88), 0 1px 3px rgba(0,0,0,0.85)' : undefined,
            }}
          >
            {line}
          </div>
        );
      })}
    </div>
  );
};

/** Body copy, one paragraph per array entry. */
export const Body: React.FC<{
  lines: string[];
  size: number;
  at?: number;
  step?: number;
  color?: string;
  maxWidth?: number;
  shadow?: boolean;
}> = ({ lines, size, at = 16, step = 7, color, maxWidth, shadow = false }) => {
  const { t } = useKit();
  const frame = useCurrentFrame();
  return (
    <div style={{ maxWidth }}>
      {lines.map((line, i) => (
        <div
          key={i}
          style={{
            fontFamily: body,
            fontWeight: 500,
            fontSize: size,
            lineHeight: 1.42,
            color: color ?? t.body,
            marginTop: i === 0 ? 0 : Math.round(size * 0.55),
            opacity: fadeIn(frame, at + i * step, 12),
            transform: rise(frame, at + i * step, 16, 14),
            textShadow: shadow ? '0 2px 18px rgba(0,0,0,0.85)' : undefined,
          }}
        >
          {line}
        </div>
      ))}
    </div>
  );
};

/** Checklist marker — a square in accent, matching the still template's rows. */
export const Marker: React.FC<{ size: number }> = ({ size }) => {
  const { t } = useKit();
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
      <path
        d="M4 12.5l5 5L20 6.5"
        stroke={t.accent}
        strokeWidth="3"
        strokeLinecap="round"
        strokeLinejoin="round"
        fill="none"
      />
    </svg>
  );
};

/**
 * Diagonal accent cut along a band's lower edge. Non-standard shape, so the
 * composition is not four stacked rectangles. Ported from famLayoutSplit.
 */
export const BandCut: React.FC<{ height: number; at?: number }> = ({ height, at = 2 }) => {
  const { t } = useKit();
  const frame = useCurrentFrame();
  return (
    <div
      style={{
        position: 'absolute',
        left: 0,
        right: 0,
        bottom: 0,
        height,
        background: t.accent,
        opacity: interpolate(frame, [at, at + 14], [0, 0.7], {
          extrapolateLeft: 'clamp',
          extrapolateRight: 'clamp',
        }),
        clipPath: 'polygon(0 100%, 100% 0, 100% 100%, 0 100%)',
      }}
    />
  );
};
