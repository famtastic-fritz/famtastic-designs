import React from 'react';
import { AbsoluteFill, Img, interpolate, OffthreadVideo, staticFile, useCurrentFrame } from 'remotion';
import { loadFont as loadDisplay } from '@remotion/google-fonts/SpaceGrotesk';
import { loadFont as loadBody } from '@remotion/google-fonts/Inter';
import { T, L } from './tokens';
import { CAPTIONS, OFFER, CTA_URL, TOTAL_FRAMES } from './script';

const display = loadDisplay().fontFamily;
const body = loadBody().fontFamily;

export const FONTS = { display, body };

/* ------------------------------------------------------------------ ground */

export const Ground: React.FC = () => (
  <AbsoluteFill
    style={{
      background: `radial-gradient(120% 70% at 50% 12%, ${T.groundLift} 0%, ${T.ground} 62%)`,
    }}
  />
);

/** Very low-amplitude film grain so flat panels never band on re-compression. */
export const Grain: React.FC = () => (
  <AbsoluteFill style={{ opacity: 0.055, mixBlendMode: 'overlay', pointerEvents: 'none' }}>
    <svg width={L.W} height={L.H}>
      <filter id="g06n">
        <feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves={3} stitchTiles="stitch" />
        <feColorMatrix type="saturate" values="0" />
      </filter>
      <rect width={L.W} height={L.H} filter="url(#g06n)" />
    </svg>
  </AbsoluteFill>
);

/* -------------------------------------------------------------------- mark */

export const Mark: React.FC<{ size: number }> = ({ size }) => (
  <svg width={size} height={size} viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill={T.panel} />
    <rect x="1.5" y="1.5" width="61" height="61" rx="12.5" fill="none" stroke={T.border} strokeWidth="3" />
    <path d="M20 48V16h24v7H28v5.5h13V35H28v13h-8z" fill={T.lime} />
    <circle cx="47" cy="46" r="3.5" fill={T.lime} />
  </svg>
);

/** Persistent brand lock-up + progress rail. Never carries the screen's glow. */
export const Chrome: React.FC = () => {
  const frame = useCurrentFrame();
  const progress = Math.min(1, frame / TOTAL_FRAMES);
  return (
    <>
      <div
        style={{
          position: 'absolute',
          top: L.topChromeY,
          left: L.gutter,
          display: 'flex',
          alignItems: 'center',
          gap: 20,
        }}
      >
        <Mark size={54} />
        <div
          style={{
            fontFamily: display,
            fontWeight: 700,
            fontSize: 27,
            letterSpacing: '0.2em',
            color: T.text72,
            textTransform: 'uppercase',
          }}
        >
          FAMtastic Designs
        </div>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, height: 6, background: 'rgba(255,255,255,0.07)' }}>
        <div style={{ width: `${progress * 100}%`, height: '100%', background: T.lime }} />
      </div>
    </>
  );
};

/* ----------------------------------------------------------------- eyebrow */

export const Eyebrow: React.FC<{ text: string; delay?: number }> = ({ text, delay = 0 }) => {
  const frame = useCurrentFrame();
  const o = interpolate(frame, [delay, delay + 8], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const w = interpolate(frame, [delay, delay + 18], [0, 84], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  return (
    <div style={{ opacity: o, display: 'flex', alignItems: 'center', gap: 22 }}>
      <div style={{ width: w, height: 5, background: T.lime, borderRadius: 3 }} />
      <div
        style={{
          fontFamily: display,
          fontWeight: 700,
          fontSize: 30,
          letterSpacing: '0.22em',
          textTransform: 'uppercase',
          color: T.lime,
        }}
      >
        {text}
      </div>
    </div>
  );
};

/* ---------------------------------------------------------------- captions */

const CaptionLine: React.FC<{ text: string; hi?: string[] }> = ({ text, hi }) => {
  const set = new Set((hi ?? []).map(w => w.toLowerCase()));
  return (
    <>
      {text.split(' ').map((word, i) => {
        const bare = word.toLowerCase();
        const on = set.has(bare);
        return (
          <span key={i} style={{ color: on ? T.lime : T.text }}>
            {word}
            {i < text.split(' ').length - 1 ? ' ' : ''}
          </span>
        );
      })}
    </>
  );
};

/**
 * Bottom caption band. No filled box — a soft scrim plus a heavy text shadow,
 * so the cinematic plate stays visible underneath.
 */
export const Captions: React.FC = () => {
  const frame = useCurrentFrame();
  const cue = CAPTIONS.find(c => frame >= c.from && frame < c.to);
  if (!cue) return null;
  const local = frame - cue.from;
  const o = interpolate(local, [0, 5], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const y = interpolate(local, [0, 9], [16, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  return (
    <div
      style={{
        position: 'absolute',
        left: L.gutter,
        right: 150,
        bottom: L.H - L.captionBaseline,
        opacity: o,
        transform: `translateY(${y}px)`,
      }}
    >
      <div
        style={{
          fontFamily: body,
          fontWeight: 700,
          fontSize: 54,
          lineHeight: 1.18,
          letterSpacing: '-0.015em',
          textShadow: '0 3px 22px rgba(0,0,0,0.92), 0 1px 3px rgba(0,0,0,0.9)',
        }}
      >
        <CaptionLine text={cue.text} hi={cue.hi} />
      </div>
    </div>
  );
};

/** Legibility scrim behind the caption band, only over photographic beats. */
export const CaptionScrim: React.FC = () => (
  <AbsoluteFill
    style={{
      background: `linear-gradient(180deg, rgba(7,9,7,0) 55%, rgba(7,9,7,0.78) 74%, rgba(7,9,7,0.96) 100%)`,
    }}
  />
);

/* -------------------------------------------------------------------- beats */

export const PresenterBeat: React.FC<{ eyebrow: string; trimBefore?: number }> = ({ eyebrow, trimBefore }) => {
  const frame = useCurrentFrame();
  const rise = interpolate(frame, [0, 16], [26, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const o = interpolate(frame, [0, 12], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  return (
    <AbsoluteFill>
      <div style={{ position: 'absolute', top: 268, left: L.gutter }}>
        <Eyebrow text={eyebrow} delay={4} />
      </div>
      <div
        style={{
          position: 'absolute',
          top: 340,
          left: 80,
          width: 920,
          height: 900,
          borderRadius: T.radius,
          overflow: 'hidden',
          border: `1px solid rgba(255,255,255,0.10)`,
          boxShadow: T.glow,
          background: T.panel,
          opacity: o,
          transform: `translateY(${rise}px)`,
        }}
      >
        <AbsoluteFill style={{ transform: 'scale(1.02)' }}>
          <OffthreadVideo
            src={staticFile('drop06/presenter.mp4')}
            muted
            trimBefore={trimBefore}
            style={{
              width: '100%',
              height: '100%',
              objectFit: 'cover',
              filter: 'saturate(0.68) brightness(0.60) contrast(1.16) hue-rotate(-10deg)',
            }}
          />
        </AbsoluteFill>
        {/* Grade the illustrated set toward the brand ground. */}
        <AbsoluteFill style={{ background: '#16210f', mixBlendMode: 'multiply', opacity: 0.5 }} />
        <AbsoluteFill
          style={{ background: 'linear-gradient(180deg, rgba(7,9,7,0.28) 0%, rgba(7,9,7,0.05) 40%, rgba(7,9,7,0.62) 100%)' }}
        />
      </div>
      <CaptionScrim />
    </AbsoluteFill>
  );
};

export const PlateBeat: React.FC<{ eyebrow: string; plate: string; pan: 'in' | 'out'; length: number }> = ({
  eyebrow,
  plate,
  pan,
  length,
}) => {
  const frame = useCurrentFrame();
  const a = pan === 'in' ? 1.04 : 1.15;
  const b = pan === 'in' ? 1.15 : 1.04;
  const scale = interpolate(frame, [0, length], [a, b], { extrapolateRight: 'clamp' });
  const drift = interpolate(frame, [0, length], [pan === 'in' ? 10 : -10, pan === 'in' ? -10 : 10], {
    extrapolateRight: 'clamp',
  });
  const o = interpolate(frame, [0, 8], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  return (
    <AbsoluteFill style={{ opacity: o }}>
      <AbsoluteFill style={{ transform: `scale(${scale}) translateY(${drift}px)` }}>
        <Img src={staticFile(`drop06/plates/${plate}`)} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
      </AbsoluteFill>
      <AbsoluteFill
        style={{ background: 'linear-gradient(180deg, rgba(7,9,7,0.86) 0%, rgba(7,9,7,0.22) 30%, rgba(7,9,7,0.12) 52%)' }}
      />
      <CaptionScrim />
      <div style={{ position: 'absolute', top: 300, left: L.gutter }}>
        <Eyebrow text={eyebrow} delay={3} />
      </div>
    </AbsoluteFill>
  );
};

export const OfferBeat: React.FC<{ length: number }> = ({ length }) => {
  const frame = useCurrentFrame();
  const o = interpolate(frame, [0, 10], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const rise = interpolate(frame, [0, 20], [30, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  // The renewal disclosure lands exactly when the voice-over states it (29.65s).
  const discFrom = Math.round((29.65 - 22.4) * 30);
  const dO = interpolate(frame, [discFrom, discFrom + 10], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  return (
    <AbsoluteFill style={{ opacity: o }}>
      <div style={{ position: 'absolute', top: 300, left: L.gutter }}>
        <Eyebrow text="What it costs" delay={3} />
      </div>
      <div
        style={{
          position: 'absolute',
          top: 420,
          left: L.gutter,
          width: L.W - L.gutter * 2,
          borderRadius: T.radius,
          background: `linear-gradient(180deg, ${T.panelHi} 0%, ${T.panel} 100%)`,
          border: `1px solid ${T.border}`,
          boxShadow: T.glow,
          padding: '54px 56px 48px',
          transform: `translateY(${rise}px)`,
        }}
      >
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 26 }}>
          <div style={{ fontFamily: FONTS.display, fontWeight: 700, fontSize: 190, lineHeight: 0.86, color: T.lime, letterSpacing: '-0.05em' }}>
            {OFFER.price}
          </div>
        </div>
        <div style={{ fontFamily: FONTS.body, fontWeight: 600, fontSize: 40, color: T.text72, marginTop: 22 }}>
          {OFFER.priceSub}
        </div>
        <div style={{ height: 1, background: T.border, margin: '40px 0 34px' }} />
        {OFFER.includes.map((line, i) => {
          const at = 24 + i * 9;
          const lo = interpolate(frame, [at, at + 10], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
          const lx = interpolate(frame, [at, at + 14], [-14, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
          return (
            <div
              key={i}
              style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 22,
                marginBottom: 24,
                opacity: lo,
                transform: `translateX(${lx}px)`,
              }}
            >
              <svg width="34" height="34" viewBox="0 0 24 24" fill="none" style={{ flexShrink: 0, marginTop: 6 }}>
                <path d="M4 12.5l5 5L20 6.5" stroke={T.lime} strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
              <div style={{ fontFamily: FONTS.body, fontWeight: 500, fontSize: 38, color: T.text }}>{line}</div>
            </div>
          );
        })}
        <div
          style={{
            marginTop: 22,
            opacity: dO,
            fontFamily: FONTS.body,
            fontWeight: 500,
            fontSize: 28,
            lineHeight: 1.4,
            color: T.text55,
          }}
        >
          {OFFER.disclosure}
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const StatementBeat: React.FC<{ plate: string; length: number }> = ({ plate, length }) => {
  const frame = useCurrentFrame();
  const scale = interpolate(frame, [0, length], [1.16, 1.04], { extrapolateRight: 'clamp' });
  const o = interpolate(frame, [0, 8], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const t1 = interpolate(frame, [4, 18], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const t2 = interpolate(frame, [16, 30], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const rise = (v: number) => `translateY(${(1 - v) * 28}px)`;
  return (
    <AbsoluteFill style={{ opacity: o }}>
      <AbsoluteFill style={{ transform: `scale(${scale})` }}>
        <Img src={staticFile(`drop06/plates/${plate}`)} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
      </AbsoluteFill>
      <AbsoluteFill style={{ background: 'linear-gradient(180deg, rgba(7,9,7,0.90) 0%, rgba(7,9,7,0.42) 46%, rgba(7,9,7,0.88) 100%)' }} />
      <div style={{ position: 'absolute', top: 470, left: L.gutter, width: L.W - L.gutter * 2 }}>
        <div
          style={{
            fontFamily: FONTS.display,
            fontWeight: 700,
            fontSize: 118,
            lineHeight: 0.94,
            letterSpacing: '-0.045em',
            color: T.text,
            opacity: t1,
            transform: rise(t1),
          }}
        >
          Cost is not
        </div>
        <div
          style={{
            fontFamily: FONTS.display,
            fontWeight: 700,
            fontSize: 118,
            lineHeight: 0.94,
            letterSpacing: '-0.045em',
            color: T.lime,
            opacity: t2,
            transform: rise(t2),
            marginTop: 10,
          }}
        >
          the reason.
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const OutroBeat: React.FC<{ length: number }> = ({ length }) => {
  const frame = useCurrentFrame();
  const o = interpolate(frame, [0, 10], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const out = interpolate(frame, [length - 12, length], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const markIn = interpolate(frame, [2, 20], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const pillIn = interpolate(frame, [18, 34], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  return (
    <AbsoluteFill style={{ opacity: o * out, alignItems: 'center', justifyContent: 'center' }}>
      <div style={{ opacity: markIn, transform: `scale(${0.9 + markIn * 0.1})` }}>
        <Mark size={132} />
      </div>
      <div
        style={{
          fontFamily: FONTS.display,
          fontWeight: 700,
          fontSize: 96,
          lineHeight: 1.0,
          letterSpacing: '-0.04em',
          color: T.text,
          marginTop: 52,
          textAlign: 'center',
          opacity: markIn,
        }}
      >
        See what&rsquo;s
        <br />
        <span style={{ color: T.lime }}>actually included.</span>
      </div>
      <div
        style={{
          marginTop: 60,
          opacity: pillIn,
          transform: `translateY(${(1 - pillIn) * 18}px)`,
          border: `2px solid ${T.lime}`,
          borderRadius: 999,
          padding: '24px 38px',
          boxShadow: T.glow,
          background: 'rgba(124,252,0,0.06)',
        }}
      >
        <div style={{ fontFamily: FONTS.body, fontWeight: 700, fontSize: 33, color: T.lime, letterSpacing: '-0.005em' }}>
          {CTA_URL}
        </div>
      </div>
      <div
        style={{
          marginTop: 40,
          opacity: pillIn,
          fontFamily: FONTS.body,
          fontWeight: 500,
          fontSize: 26,
          color: T.text38,
          textAlign: 'center',
          maxWidth: 760,
          lineHeight: 1.45,
        }}
      >
        $199 first year, then $9.99/mo managed hosting. Domain renews separately.
      </div>
    </AbsoluteFill>
  );
};
